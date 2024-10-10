<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Mux\MuxApi;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use MuxPhp\Models\Upload;
use Psr\Http\Message\ResponseInterface;
use Statamic\Assets\Asset;
use Statamic\Support\Traits\Hookable;

class CreateMuxAsset
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
    ) {}

    /**
     * Upload a video asset to Mux.
     */
    public function handle(Asset $asset, bool $force = false): ?string
    {
        if (! $asset->isVideo()) {
            return null;
        }

        $existingMuxAsset = MuxAsset::fromAsset($asset);
        if (! $force && $existingMuxAsset->existsOnMux()) {
            return null;
        }

        if (AssetUploadingToMux::dispatch($asset) === false) {
            return null;
        }

        try {
            if ($this->app->isLocal() || $asset->container()->private()) {
                $muxId = $this->uploadAssetToMux($asset);
            } else {
                $muxId = $this->ingestAssetToMux($asset);
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to upload video to Mux: {$th->getMessage()}");
        }

        if ($muxId) {
            AssetUploadedToMux::dispatch($asset, $muxId);
        }

        return $muxId;
    }

    /**
     * Upload a video asset to Mux using a direct upload link.
     */
    protected function uploadAssetToMux(Asset $asset): ?string
    {
        $request = $this->api->createUploadRequest([
            'passthrough' => $this->getAssetPassthroughData($asset),
        ]);
        $muxUpload = $this->api->directUploads()->createDirectUpload($request)->getData();
        $uploadId = $muxUpload->getId();

        $this->api->handleDirectUpload($muxUpload, $asset->contents());

        $muxUpload = $this->api->directUploads()->getDirectUpload($uploadId)->getData();
        $muxId = $muxUpload?->getAssetId();

        return $muxId;
    }

    /**
     * Upload a video asset to Mux using ingestion from a public url.
     */
    protected function ingestAssetToMux(Asset $asset): ?string
    {
        $request = $this->api->createAssetRequest([
            'input' => $this->api->input(['url' => $asset->absoluteUrl()]),
            'passthrough' => $this->getAssetPassthroughData($asset),
        ]);
        $muxAssetResponse = $this->api->assets()->createAsset($request)->getData();
        $muxId = $muxAssetResponse?->getId();

        return $muxId;
    }

    /**
     * Send direct upload request to Mux.
     */
    protected function handleDirectUpload(Upload $upload, string $contents): ResponseInterface
    {
        return $this->api->client()->put($upload->getUrl(), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $contents,
        ]);
    }

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getAssetPassthroughData(Asset $asset): string
    {
        return $this->getAssetIdentifier($asset);
    }

    /**
     * Get unique asset identifier.
     */
    protected function getAssetIdentifier(Asset $asset): string
    {
        return "statamic::{$asset->id()}";
    }
}
