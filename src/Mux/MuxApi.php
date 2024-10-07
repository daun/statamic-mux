<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Arr;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\DeliveryUsageApi;
use MuxPhp\Api\DirectUploadsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\Api\PlaybackIDApi;
use MuxPhp\Api\URLSigningKeysApi;
use MuxPhp\Configuration;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreatePlaybackIDRequest;
use MuxPhp\Models\CreateUploadRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackPolicy;
use MuxPhp\Models\Upload;

class MuxApi
{
    protected Client $client;

    protected Configuration $config;

    protected AssetsApi $assetsApi;

    protected DirectUploadsApi $directUploadsApi;

    protected URLSigningKeysApi $urlSigningKeysApi;

    protected LiveStreamsApi $liveStreamsApi;

    protected PlaybackIDApi $playbackIDApi;

    protected DeliveryUsageApi $deliveryUsageApi;

    protected const userAgent = 'daun/statamic-mux';

    public function __construct(
        protected ?string $tokenId,
        protected ?string $tokenSecret,
        protected bool $debug = false,
        protected bool $testMode = false,
        protected mixed $playbackPolicy = null,
        protected ?string $videoQuality = null,
    ) {
        $this->client = new Client();
        $this->config = Configuration::getDefaultConfiguration()
            ->setUsername($this->tokenId)
            ->setPassword($this->tokenSecret)
            ->setDebug($this->debug)
            ->setDebugFile(storage_path('logs/mux.log'))
            ->setUserAgent(self::userAgent);
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
        return new CreateAssetRequest([
            'test' => $this->testMode,
            'playback_policy' => $this->sanitizePlaybackPolicies($this->playbackPolicy),
            'video_quality' => $this->videoQuality,
            ...$options,
        ]);
    }

    public function createUploadRequest(array $options = []): CreateUploadRequest
    {
        return new CreateUploadRequest([
            'test' => $options['test'] ?? $this->testMode,
            'new_asset_settings' => $this->createAssetRequest($options),
            'cors_origin' => '*',
        ]);
    }

    public function handleDirectUpload(Upload $upload, string $contents): Response
    {
        return $this->client->put($upload->getUrl(), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $contents,
        ]);
    }

    public function createPlaybackIdRequest(array $options = []): CreatePlaybackIDRequest
    {
        $policy = (string) ($options['policy'] ?? '');

        return new CreatePlaybackIDRequest([
            'policy' => $policy ?: $this->playbackPolicy,
        ]);
    }

    protected function sanitizePlaybackPolicies(mixed $policy): array
    {
        if (is_string($policy)) {
            $policy = preg_split('/\s*,\s*/', $policy);
        }

        return collect($policy)
            ->filter(fn ($item) => MuxPlaybackPolicy::isValid($item))
            ->all();
    }
}
