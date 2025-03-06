<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset as MuxApiAssetModel;
use MuxPhp\Models\AssetStaticRenditions;
use Statamic\Assets\Asset;
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
    public function handle(string $muxId, Asset $asset): bool
    {
        try {
            if ($renditionUrl = $this->getSmallestRendition($muxId)) {
                ray('$renditionUrl', $renditionUrl);
                // $asset->disk()->filesystem(), $asset->path()
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to download proxy of Mux asset: {$th->getMessage()}");
        }

        return true;
    }

    /**
     * Whether the proxy is ready for downloading.
     */
    public function ready(string $muxId): bool
    {
        try {
            $data = $this->api->assets()->getAsset($muxId)->getData();
            $assetStatus = $data?->getStatus();
            $renditionStatus = $data?->getStaticRenditions()?->getStatus();

            return $assetStatus === MuxApiAssetModel::STATUS_READY
                && $renditionStatus === AssetStaticRenditions::STATUS_READY;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Check if a Mux asset has static renditions.
     */
    protected function hasStaticRenditionsReady(string $muxId): bool
    {
        $data = $this->api->assets()->getAsset($muxId)->getData();
        $status = $data?->getStaticRenditions()?->getStatus();

        return $status === AssetStaticRenditions::STATUS_READY;
    }

    /**
     * Get the URL of the smallest MP4 rendition of an existing Mux asset.
     */
    protected function getSmallestRendition(string $muxId): ?string
    {
        $data = $this->api->assets()->getAsset($muxId)->getData();
        $renditions = $data?->getStaticRenditions();

        ray(json_decode($renditions->jsonSerialize()));

        return null;
    }
}
