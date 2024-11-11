<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use GuzzleHttp\Client;
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

class MuxApi
{
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
            ->setDebug($this->debug)
            ->setDebugFile(storage_path('logs/mux.log'))
            ->setUserAgent(self::userAgent);
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function config(): Configuration
    {
        return $this->config;
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
            'playback_policy' => MuxPlaybackPolicy::makeMany($this->playbackPolicy)->map->value()->all(),
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

    public function createPlaybackIdRequest(array $options = []): CreatePlaybackIDRequest
    {
        $policy = (string) ($options['policy'] ?? '');

        return new CreatePlaybackIDRequest([
            'policy' => $policy ?: $this->playbackPolicy,
        ]);
    }
}
