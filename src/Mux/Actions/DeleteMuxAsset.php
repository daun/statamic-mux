<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;

class DeleteMuxAsset
{
    public function __construct(
        protected MuxApi $api,
        protected MuxService $service,
    ) {}

    /**
     * Delete a video asset from Mux.
     */
    public function handle(Asset|string $asset): bool
    {
        if (! $asset) {
            return false;
        }

        if (is_string($asset)) {
            // Special case: delete Mux asset by its ID
            return $this->deleteOrphanedMuxAsset($asset);
        } else {
            // Delete Mux asset tied to local Statamic asset
            return $this->deleteConnectedMuxAsset($asset);
        }
    }

    /**
     * Delete a standalone Mux asset (without associated local Statamic asset) by its ID
     */
    protected function deleteOrphanedMuxAsset(string $muxId): bool
    {
        try {
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            if ($this->wasAssetCreatedByAddon($muxAssetResponse)) {
                $this->api->assets()->deleteAsset($muxId);

                return true;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }

        return false;
    }

    /**
     * Delete a remote Mux asset by its local Statamic asset.
     */
    protected function deleteConnectedMuxAsset(Asset $asset): bool
    {
        if (! $asset->isVideo()) {
            return false;
        }

        $muxId = $this->service->getMuxId($asset);
        if (! $muxId) {
            return false;
        }

        if (AssetDeletingFromMux::dispatch($asset, $muxId) === false) {
            return false;
        }

        try {
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            if ($this->wasAssetCreatedByAddon($muxAssetResponse)) {
                $this->api->assets()->deleteAsset($muxId);
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }

        AssetDeletedFromMux::dispatch($asset, $muxId);

        return true;
    }

    /**
     * Check if this asset was created by this addon.
     */
    protected function wasAssetCreatedByAddon(mixed $muxAsset): bool
    {
        $identifier = $muxAsset['passthrough'] ?? $muxAsset ?? '';

        return is_string($identifier) && str_starts_with($identifier, 'statamic::');
    }
}
