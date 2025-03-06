<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Statamic\Support\Traits\Hookable;

class DownloadProxyVersion
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxService $service,
    ) {}

    /**
     * Download a low-fi proxy video of an existing Mux asset.
     */
    public function handle(string $muxId, string $path): bool
    {
        try {
            $renditionUrl = $this->getSmallestRendition($muxId);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to download proxy of Mux asset: {$th->getMessage()}");
        }

        return true;
    }

    /**
     * Whether the proxy can already be downloaded.
     */
    public function ready(string $muxId): bool
    {
        return $this->service->muxAssetExists($muxId)
            && $this->service->isMuxAssetReady($muxId)
            && $this->;
    }

    /**
     * Get the URL of the smallest MP4 rendition of an existing Mux asset.
     */
    protected function getSmallestRendition(string $muxId): ?string
    {
        $data = $this->api->assets()->getAsset($muxId)?->getData();
        $playbackId = $data?->getPlaybackIds()[0]?->getId();
        $status
        $

        ray(json_decode($data->jsonSerialize()));
    }

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getAssetPassthroughData(string $muxId): string
    {
        return "statamic::proxy::{$muxId}";
    }
}
