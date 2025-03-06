<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use MuxPhp\Models\Asset as MuxApiAssetModel;
use Statamic\Support\Traits\Hookable;

class CreateProxyVersion
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxService $service,
    ) {}

    /**
     * Create a low-fi proxy video from an existing Mux asset.
     */
    public function handle(string $muxId, float $start = 0, float $length = 5): ?string
    {
        try {
            $proxyId = $this->createClipFromAsset($muxId, $start, $length);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to generate proxy from Mux asset: {$th->getMessage()}");
        }

        return $proxyId;
    }

    /**
     * Whether the proxy can already be created.
     */
    public function ready(string $muxId): bool
    {
        return $this->service->muxAssetExists($muxId) && $this->service->isMuxAssetReady($muxId);
    }

    /**
     * Create a new clipped video from an existing Mux asset.
     */
    protected function createClipFromAsset(string $muxId, float $start, float $length): ?string
    {
        $request = $this->api->createAssetRequest([
            'playback_policy' => MuxPlaybackPolicy::Public->value,
            'video_quality' => MuxApiAssetModel::VIDEO_QUALITY_BASIC,
            'mp4_support' => MuxApiAssetModel::MP4_SUPPORT_STANDARD,
            'input' => $this->api->input([
                'url' => "mux://assets/{$muxId}",
                'start_time' => $start,
                'end_time' => $start + $length,
            ]),
            'passthrough' => $this->getAssetPassthroughData($muxId),
        ]);

        return $this->api->assets()
            ->createAsset($request)
            ->getData()
            ?->getId();
    }

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getAssetPassthroughData(string $muxId): string
    {
        return "statamic::proxy::{$muxId}";
    }
}
