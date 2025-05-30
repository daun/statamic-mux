<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Concerns\GeneratesAssetData;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;

class CreateMuxAsset
{
    use GeneratesAssetData;

    public function __construct(
        protected MuxService $service,
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

        if (! $force && $this->service->hasExistingMuxAsset($asset)) {
            return null;
        }

        if (AssetUploadingToMux::dispatch($asset) === false) {
            return null;
        }

        try {
            if (app()->isLocal() || $asset->container()->private()) {
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
        $data = $this->getAssetData($asset) + $this->getAssetSettings($asset);
        $request = $this->api->createUploadRequest($data);
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
        $input = $this->api->input(['url' => $asset->absoluteUrl()]);
        $data = ['input' => $input] + $this->getAssetData($asset) + $this->getAssetSettings($asset);
        $request = $this->api->createAssetRequest($data);
        $muxAssetResponse = $this->api->assets()->createAsset($request)->getData();
        $muxId = $muxAssetResponse?->getId();

        return $muxId;
    }
}
