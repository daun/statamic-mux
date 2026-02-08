<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Concerns\ProcessesHooks;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use GuzzleHttp\Client;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\DeliveryUsageApi;
use MuxPhp\Api\DirectUploadsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\Api\PlaybackIDApi;
use MuxPhp\Api\URLSigningKeysApi;
use MuxPhp\ApiException;
use MuxPhp\Configuration;
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
            'token_secret' => $this->tokenSecret,
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
            $expected = \MuxPhp\Models\Asset::STATUS_READY;

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
                return $carry && $file->getStatus() === \MuxPhp\Models\AssetStaticRenditions::STATUS_READY;
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
