<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
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
        if (! $this->canHandle($asset)) {
            return null;
        }

        if (! $this->isReady($asset)) {
            return null;
        }

        try {
            return $this->createClipFromAsset($asset, $this->start, $this->getLength());
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to generate proxy from Mux asset: {$th->getMessage()}");
        }
    }

    /**
     * Whether a proxy can be created for this asset.
     */
    public function canHandle(Asset $asset): bool
    {
        return $asset->isVideo()
            && $asset->extension() === 'mp4'
            && ($asset->duration() ?? 0) > $this->getLength()
            && $this->service->hasExistingMuxAsset($asset);
    }

    /**
     * Whether the proxy can already be created.
     */
    public function isReady(Asset $asset): bool
    {
        return ($muxId = $this->service->getMuxId($asset))
            && $this->api->assetIsReady($muxId);
    }

    /**
     * Create a new clipped video from an existing Mux asset.
     */
    protected function createClipFromAsset(Asset $asset, float $start, float $length): ?string
    {
        $muxId = $this->service->getMuxId($asset);

        $request = $this->api->createAssetRequest([
            'playback_policy' => MuxPlaybackPolicy::Public->value,
            // 'video_quality' => \MuxPhp\Models\Asset::VIDEO_QUALITY_BASIC,
            'input' => $this->api->input([
                'url' => "mux://assets/{$muxId}",
                'start_time' => $start,
                'end_time' => $start + $length,
            ]),
            'static_renditions' => [
                ['resolution' => \MuxPhp\Models\StaticRendition::RESOLUTION_HIGHEST],
            ],
            'passthrough' => $this->getPassthroughData($muxId),
        ]);

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
