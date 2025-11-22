<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Statamic\Assets\Asset;

class CreateProxyVersion
{
    protected int $start = 0;

    public function __construct(
        protected Application $app,
        protected MuxService $service,
        protected MuxApi $api,
    ) {}

    /**
     * Generate a short proxy version of an existing Mux asset.
     */
    public function handle(Asset $asset): ?string
    {
        if (! $this->shouldHandle($asset)) {
            return null;
        }

        if (! $this->isReady($asset)) {
            return null;
        }

        try {
            $proxyId = $this->createClipFromAsset($asset, $this->start, $this->getLength());

            Log::info(
                'Created proxy version of Mux asset',
                ['asset' => $asset->id(), 'proxy_id' => $proxyId, 'start' => $this->start, 'length' => $this->getLength()],
            );

            return $proxyId;
        } catch (\Throwable $th) {
            Log::error(
                "Error generating proxy version of Mux asset: {$th->getMessage()}",
                ['asset' => $asset->id(), 'exception' => $th],
            );

            throw new \Exception("Error generating proxy version of Mux asset: {$th->getMessage()}", previous: $th);
        }
    }

    /**
     * Whether a proxy can be created for this asset.
     */
    public function shouldHandle(Asset $asset): bool
    {
        $skip = match (true) {
            ! $asset->isVideo() => 'not a video asset',
            $asset->extension() !== 'mp4' => 'not an mp4 file',
            ($asset->duration() ?? 0) <= $this->getLength() => 'shorter than configured proxy length',
            ! $this->service->hasExistingMuxAsset($asset) => 'no existing Mux asset',
            default => null,
        };

        if ($skip) {
            Log::debug(
                "Skipping creation of proxy version: {$skip}",
                ['asset' => $asset->id(), 'reason' => $skip, 'asset_duration' => $asset->duration(), 'proxy_length' => $this->getLength()],
            );
        }

        return ! $skip;
    }

    /**
     * Whether the proxy can already be created.
     */
    public function isReady(Asset $asset): bool
    {
        $muxId = $this->service->getMuxId($asset);

        $unready = match (true) {
            ! $muxId => 'no existing Mux asset',
            ! $this->api->assetIsReady($muxId) => 'Mux asset is not ready',
            default => null,
        };

        if ($unready) {
            Log::debug(
                "Delaying creation of proxy version: {$unready}",
                ['asset' => $asset->id(), 'reason' => $unready],
            );
        }

        return ! $unready;
    }

    /**
     * Create a new clipped video from an existing Mux asset.
     */
    protected function createClipFromAsset(Asset $asset, float $start, float $length): ?string
    {
        $muxId = $this->service->getMuxId($asset);

        $payload = [
            'playback_policy' => [MuxPlaybackPolicy::Public->value],
            'input' => $this->api->input([
                'url' => "mux://assets/{$muxId}",
                'start_time' => $start,
                'end_time' => $start + $length,
            ]),
            'static_renditions' => [
                ['resolution' => \MuxPhp\Models\StaticRendition::RESOLUTION_HIGHEST],
            ],
            'passthrough' => $this->getPassthroughData($muxId),
        ];

        $request = $this->api->createAssetRequest($payload);

        Log::debug(
            'Creating clip from Mux asset',
            ['asset' => $asset->id(), 'mux_id' => $muxId, 'payload' => $payload],
        );

        return $this->api->assets()
            ->createAsset($request)
            ->getData()
            ?->getId();
    }

    /**
     * Get configured length of proxy versions.
     */
    protected function getLength(): float
    {
        return (float) config('mux.storage.placeholder_length', 10);
    }

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getPassthroughData(string $muxId): string
    {
        return "statamic-proxy::{$muxId}";
    }
}
