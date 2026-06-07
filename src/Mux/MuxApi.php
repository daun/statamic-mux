<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Concerns\ProcessesHooks;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\DeliveryUsageApi;
use MuxPhp\Api\DirectUploadsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\Api\PlaybackIDApi;
use MuxPhp\Api\URLSigningKeysApi;
use MuxPhp\ApiException;
use MuxPhp\Configuration;
use MuxPhp\Models\Asset;
use MuxPhp\Models\AssetStaticRenditions;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreatePlaybackIDRequest;
use MuxPhp\Models\CreateUploadRequest;
use MuxPhp\Models\InputSettings;
use Statamic\Facades\Blink;

class MuxApi
{
    use ProcessesHooks;

    protected Configuration $config;

    protected AssetsApi $assetsApi;

    protected DirectUploadsApi $directUploadsApi;

    protected URLSigningKeysApi $urlSigningKeysApi;

    protected LiveStreamsApi $liveStreamsApi;

    protected PlaybackIDApi $playbackIDApi;

    protected DeliveryUsageApi $deliveryUsageApi;

    protected const userAgent = 'daun/statamic-mux';

    public function __construct(
        protected Client $client,
        protected ?string $tokenId,
        protected ?string $tokenSecret,
        protected bool $debug = false,
        protected bool $testMode = false,
        protected mixed $playbackPolicy = null,
        protected ?string $videoQuality = null,
    ) {
        $this->config = Configuration::getDefaultConfiguration()
            ->setUsername($this->tokenId)
            ->setPassword($this->tokenSecret)
            ->setUserAgent(self::userAgent)
            ->setDebug($this->debug)
            ->setDebugFile(storage_path('logs/mux-sdk.log'));

        // Log SDK credentials on first request
        $this->debugCredentials();
    }

    protected function debugCredentials(): void
    {
        if (! $this->debug) {
            return;
        }

        $context = [
            'token_id' => $this->tokenId,
            'token_secret' => $this->tokenSecret ? '(set)' : '(not set)',
            'test_mode' => $this->testMode,
        ];

        $this->hook('api-request', function ($payload, $next) use ($context) {
            Blink::once('debug-mux-credentials', fn () => Log::debug('Initializing Mux API', $context));

            return $next($payload);
        });
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function config(): Configuration
    {
        return $this->config;
    }

    public function configured(): bool
    {
        return $this->tokenId && $this->tokenSecret;
    }

    public function assets(): AssetsApi
    {
        $this->assetsApi ??= new AssetsApi($this->client, $this->config);

        return $this->assetsApi;
    }

    public function directUploads(): DirectUploadsApi
    {
        $this->directUploadsApi ??= new DirectUploadsApi($this->client, $this->config);

        return $this->directUploadsApi;
    }

    public function liveStreams(): LiveStreamsApi
    {
        $this->liveStreamsApi ??= new LiveStreamsApi($this->client, $this->config);

        return $this->liveStreamsApi;
    }

    public function urlSigningKeys(): URLSigningKeysApi
    {
        $this->urlSigningKeysApi ??= new URLSigningKeysApi($this->client, $this->config);

        return $this->urlSigningKeysApi;
    }

    public function playbackIDs(): PlaybackIDApi
    {
        $this->playbackIDApi ??= new PlaybackIDApi($this->client, $this->config);

        return $this->playbackIDApi;
    }

    public function deliveryUsage(): DeliveryUsageApi
    {
        $this->deliveryUsageApi ??= new DeliveryUsageApi($this->client, $this->config);

        return $this->deliveryUsageApi;
    }

    public function whoami(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $cacheKey = 'statamic-mux.whoami.'.sha1((string) $this->tokenId);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client->get('https://api.mux.com/system/v1/whoami', [
                'auth' => [$this->tokenId, $this->tokenSecret],
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => self::userAgent,
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            $data = $payload['data'] ?? null;

            if (is_array($data)) {
                Cache::put($cacheKey, $data, now()->addMonths(12));

                return $data;
            }
        } catch (GuzzleException $e) {
            Log::error("Failed to load Mux environment details: {$e->getMessage()}", ['exception' => $e]);
        }

        return null;
    }

    public function dashboardUrl(): ?string
    {
        $info = $this->whoami();
        $environmentId = $info['environment_id'] ?? null;

        return $environmentId
            ? "https://dashboard.mux.com/environments/{$environmentId}"
            : null;
    }

    public function input(array $input): InputSettings
    {
        return new InputSettings($input);
    }

    public function createAssetRequest(array $options = []): CreateAssetRequest
    {
        $data = [
            'test' => $this->testMode,
            'playback_policy' => MuxPlaybackPolicy::makeMany($this->playbackPolicy)->pluck('value')->all(),
            'video_quality' => $this->videoQuality,
            ...$options,
        ];

        try {
            return new CreateAssetRequest($data);
        } catch (\Throwable $th) {
            Log::error(
                "Failed to create Mux asset request: {$th->getMessage()}",
                ['data' => $data, 'exception' => $th],
            );

            throw $th;
        }
    }

    public function createUploadRequest(array $options = []): CreateUploadRequest
    {
        $data = [
            'test' => $options['test'] ?? $this->testMode,
            'new_asset_settings' => $this->createAssetRequest($options),
            'cors_origin' => '*',
        ];

        try {
            return new CreateUploadRequest($data);
        } catch (\Throwable $th) {
            Log::error(
                "Failed to create Mux upload request: {$th->getMessage()}",
                ['data' => $data, 'exception' => $th],
            );

            throw $th;
        }
    }

    public function createPlaybackIdRequest(array $options = []): CreatePlaybackIDRequest
    {
        $data = [
            'policy' => (string) ($options['policy'] ?? '') ?: $this->playbackPolicy,
        ];

        try {
            return new CreatePlaybackIDRequest($data);
        } catch (\Throwable $th) {
            Log::error(
                "Failed to create Mux playback ID request: {$th->getMessage()}",
                ['data' => $data, 'exception' => $th],
            );

            throw $th;
        }
    }

    /**
     * @return Collection<Asset>
     */
    public function listAssets(int $limit = 100, int $page = 1): Collection
    {
        if ($limit >= 1) {
            return collect($this->assets()->listAssets($limit, $page)->getData());
        }

        $assets = collect();
        $new = null;
        $page = 1;

        do {
            $new = $this->assets()->listAssets(100, $page)->getData();
            $assets->push(...$new);
            $page++;
        } while (count($new ?? []));

        return $assets;
    }

    public function getAsset(string $muxId): ?Asset
    {
        try {
            $response = $this->assets()->getAsset($muxId)->getData();
            Log::debug('Loading Mux asset', ['mux_id' => $response?->getId()]);

            return $response;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            } else {
                throw $e;
            }
        } catch (\Throwable $th) {
            Log::error(
                "Failed to load Mux asset: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            throw $th;
        }
    }

    /**
     * @param  Collection<int, string>|array<int, string>  $muxIds
     * @return Collection<string, Asset>
     */
    public function getAssets(Collection|array $muxIds, int $concurrency = 5): Collection
    {
        $muxIds = collect($muxIds)->filter()->unique()->values();

        if ($muxIds->isEmpty()) {
            return collect();
        }

        $assets = collect();
        $requests = function () use ($muxIds) {
            foreach ($muxIds as $muxId) {
                yield $muxId => $this->assets()->getAssetAsync($muxId);
            }
        };

        $config = [
            'fulfilled' => function ($response, string $muxId) use ($assets): void {
                $asset = $response?->getData();

                if ($asset) {
                    $assets[$asset->getId() ?? $muxId] = $asset;
                }
            },
            'rejected' => function ($reason, string $muxId): void {
                if ($reason instanceof ApiException && $reason->getCode() === 404) {
                    return;
                }

                if ($reason instanceof \Throwable) {
                    Log::error(
                        "Failed to load Mux asset: {$reason->getMessage()}",
                        ['mux_id' => $muxId, 'exception' => $reason],
                    );

                    throw $reason;
                }

                throw new \RuntimeException("Failed to load Mux asset: {$muxId}");
            },
        ];

        if ($concurrency > 0) {
            $config['concurrency'] = $concurrency;
        }

        (new EachPromise($requests(), $config))->promise()->wait();

        return $assets;
    }

    public function assetExists(string $muxId): bool
    {
        try {
            $response = $this->assets()->getAsset($muxId)->getData();
            $actualMuxId = $response?->getId();

            Log::debug(
                'Checking Mux asset existence: '.($muxId === $actualMuxId ? 'exists' : 'does not exist'),
                ['mux_id' => $muxId, 'returned_id' => $actualMuxId],
            );

            return $muxId === $actualMuxId;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        } catch (\Throwable $th) {
            Log::error(
                "Failed to check Mux asset existence: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            throw $th;
        }
    }

    public function assetIsReady(string $muxId): bool
    {
        try {
            $response = $this->assets()->getAsset($muxId)->getData();
            $status = $response?->getStatus();
            $expected = Asset::STATUS_READY;

            Log::debug(
                'Checking Mux asset status: '.($status === $expected ? 'ready' : 'not ready'),
                ['mux_id' => $muxId, 'status' => $status, 'expected' => $expected],
            );

            return $status === $expected;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        } catch (\Throwable $th) {
            Log::error(
                "Failed to check Mux asset status: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            throw $th;
        }
    }

    public function assetRenditionsAreReady(string $muxId): bool
    {
        try {
            $response = $this->assets()->getAsset($muxId)->getData();
            $files = $response?->getStaticRenditions()?->getFiles() ?? [];

            $ready = array_reduce($files, function ($carry, $file) {
                return $carry && $file->getStatus() === AssetStaticRenditions::STATUS_READY;
            }, count($files) > 0);

            Log::debug(
                'Checking Mux renditions status: '.($ready ? 'all ready' : 'not all ready'),
                ['mux_id' => $muxId, 'renditions' => $files],
            );

            return $ready;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        } catch (\Throwable $th) {
            Log::error(
                "Failed to check Mux renditions status: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            throw $th;
        }
    }
}
