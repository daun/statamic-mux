<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        // Special case: delete Mux asset by its ID
        if (is_string($asset)) {
            return $this->deleteOrphanedMuxAsset($asset);
        }

        // Delete Mux asset tied to local Statamic asset
        if ($deleted = $this->deleteConnectedMuxAsset($asset)) {
            MuxAsset::fromAsset($asset)->clear()->save();
        }

        return $deleted;
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
        $identifier = $muxAsset['passthrough'] ?? $muxAsset ?? null;

        return is_string($identifier)
            && Str::startsWith($identifier, ['statamic::', 'statamic-proxy::']);
    }
}
