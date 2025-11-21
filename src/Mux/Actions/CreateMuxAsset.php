<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Concerns\GeneratesAssetData;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
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
        if (! $this->shouldHandle($asset, $force)) {
            return null;
        }

        if (AssetUploadingToMux::dispatch($asset) === false) {
            Log::debug(
                'Canceled video upload to Mux via event listener',
                ['asset' => $asset->id(), 'event' => 'AssetUploadingToMux'],
            );

            return null;
        }

        try {
            if ($this->assetIsPubliclyAccessible($asset)) {
                $muxId = $this->ingestAssetToMux($asset);
            } else {
                $muxId = $this->uploadAssetToMux($asset);
            }
        } catch (\Throwable $th) {
            Log::error(
                "Error uploading video to Mux: {$th->getMessage()}",
                ['asset' => $asset->id(), 'exception' => $th],
            );

            throw new \Exception("Error uploading video to Mux: {$th->getMessage()}", previous: $th);
        }

        if ($muxId) {
            Log::info(
                'Video uploaded to Mux',
                ['asset' => $asset->id(), 'mux_id' => $muxId],
            );

            MuxAsset::fromAsset($asset)->clear()->setId($muxId)->save();
            AssetUploadedToMux::dispatch($asset, $muxId);
        }

        return $muxId;
    }

    /**
     * Determine if the action should handle the asset.
     */
    protected function shouldHandle(Asset $asset, bool $force = false): bool
    {
        if (! $asset->isVideo()) {
            return false;
        }

        if (MuxAsset::fromAsset($asset)->isProxy()) {
            Log::debug(
                'Skipping upload of asset to Mux: asset is a proxy',
                ['asset' => $asset->id()],
            );

            return false;
        }

        if (! $force && $this->service->hasExistingMuxAsset($asset)) {
            Log::debug(
                'Skipping upload of asset to Mux: already exists on Mux',
                ['asset' => $asset->id(), 'mux_id' => $this->service->getMuxId($asset)],
            );

            return false;
        }

        return true;
    }

    /**
     * Determine whether an asset can be ingested from a public url.
     */
    protected function assetIsPubliclyAccessible(Asset $asset): bool
    {
        $filesystem = $asset->container()->disk()->filesystem()->getConfig();

        $public = true;

        if (empty($filesystem['url'] ?? null)) {
            $public = false;
        }

        if (($filesystem['visibility'] ?? null) !== 'public') {
            $public = false;
        }

        if (app()->isLocal() && $filesystem['driver'] === 'local') {
            $public = false;
        }

        Log::debug(
            'Asset is publicly accessible: '.($public ? 'yes' : 'no'),
            ['asset' => $asset->id(), 'public' => $public, 'filesystem' => $filesystem],
        );

        return $public;
    }

    /**
     * Upload a video asset to Mux using a direct upload link.
     */
    protected function uploadAssetToMux(Asset $asset): ?string
    {
        $data = $this->getAssetData($asset) + $this->getAssetSettings($asset);
        $request = $this->api->createUploadRequest($data);
        $muxUpload = $this->api->directUploads()->createDirectUpload($request)->getData();

        Log::debug(
            'Uploading asset to Mux via direct upload',
            ['asset' => $asset->id(), 'upload_id' => $muxUpload->getId(), 'upload_url' => $muxUpload->getUrl()],
        );

        $this->api->client()->put($muxUpload->getUrl(), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $asset->stream(),
        ]);

        $muxUpload = $this->api->directUploads()->getDirectUpload($muxUpload->getId())->getData();
        $muxId = $muxUpload?->getAssetId();

        if (! $muxId) {
            Log::error(
                'Error retrieving Mux asset id from direct upload',
                ['asset' => $asset->id(), 'upload_id' => $muxUpload?->getId(), 'response' => $muxUpload],
            );

            return null;
        }

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

        Log::debug(
            'Uploading asset to Mux via public url',
            ['asset' => $asset->id(), 'public_url' => $asset->absoluteUrl()],
        );

        $muxAsset = $this->api->assets()->createAsset($request)->getData();
        $muxId = $muxAsset?->getId();

        if (! $muxId) {
            Log::error(
                'Error retrieving Mux asset id from public url upload',
                ['asset' => $asset->id(), 'response' => $muxAsset],
            );

            return null;
        }

        return $muxId;
    }
}
