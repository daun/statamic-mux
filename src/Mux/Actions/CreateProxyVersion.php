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
    public function __construct(
        protected Application $app,
        protected MuxService $service,
        protected MuxApi $api,
    ) {}

    /**
     * Generate a short proxy version of an existing Mux asset.
     */
    public function handle(Asset $asset, float $start = 0, float $length = 5): ?string
    {
        if (! $asset->isVideo()) {
            return null;
        }

        if (! $this->service->hasExistingMuxAsset($asset)) {
            return null;
        }

        try {
            return $this->createClipFromAsset($asset, $start, $length);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to generate proxy from Mux asset: {$th->getMessage()}");
        }

        return null;
    }

    /**
     * Create a new clipped video from an existing Mux asset.
     */
    protected function createClipFromAsset(Asset $asset, float $start, float $length): ?string
    {
        $muxId = $this->service->getMuxId($asset);

        $request = $this->api->createAssetRequest([
            'playback_policy' => MuxPlaybackPolicy::Public->value,
            'video_quality' => \MuxPhp\Models\Asset::VIDEO_QUALITY_BASIC,
            'mp4_support' => \MuxPhp\Models\Asset::MP4_SUPPORT_STANDARD,
            'input' => $this->api->input([
                'url' => "mux://assets/{$muxId}",
                'start_time' => $start,
                'end_time' => $start + $length,
            ]),
            'passthrough' => $this->getPassthroughData($muxId),
        ]);

        return $this->api->assets()
            ->createAsset($request)
            ->getData()
            ?->getId();
    }

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getPassthroughData(string $muxId): string
    {
        return "proxy::{$muxId}";
    }
}
