<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Mux\MuxApi;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
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
        $request = $this->api->createUploadRequest($this->getAssetData($asset));
        $muxUpload = $this->api->directUploads()->createDirectUpload($request)->getData();
        $uploadId = $muxUpload->getId();

        $this->api->client()->put($muxUpload->getUrl(), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $asset->contents(),
        ]);

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
            ...$this->getAssetData($asset),
            'input' => $this->api->input(['url' => $asset->absoluteUrl()]),
        ]);
        $muxAssetResponse = $this->api->assets()->createAsset($request)->getData();
        $muxId = $muxAssetResponse?->getId();

        return $muxId;
    }

    /**
     * Get complete data to send to Mux for asset creation.
     * The passthrough data is used to identify addon assets later, so it should not be overridden.
     */
    protected function getAssetData(Asset $asset): array
    {
        $metadata = $this->getAssetMetadata($asset);
        $data = $this->runHooksWith('asset-data', ['asset' => $asset, 'data' => []])->data;

        return [
            'meta' => $metadata,
            ...$data,
            'passthrough' => $this->getAssetIdentifier($asset)
        ];
    }

    /**
     * Get metadata to send to Mux during asset creation.
     */
    protected function getAssetMetadata(Asset $asset): array
    {
        $metadata = [
            'title' => $asset->title(),
            'external_id' => $asset->id(),
        ];

        return $this->runHooksWith('asset-metadata', ['asset' => $asset, 'metadata' => $metadata])->metadata ?? [];
    }

    /**
     * Get unique asset identifier.
     */
    protected function getAssetIdentifier(Asset $asset): string
    {
        return "statamic::{$asset->id()}";
    }
}
