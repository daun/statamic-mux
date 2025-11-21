<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
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
            Log::notice(
                'Error deleting Mux asset: no asset provided',
                ['asset' => $asset],
            );

            return false;
        }

        // Special case: delete Mux asset by its ID
        if (is_string($asset)) {
            return $this->deleteMuxAsset($asset);
        }

        // Delete Mux asset tied to local Statamic asset
        if ($deleted = $this->deleteConnectedMuxAsset($asset)) {
            MuxAsset::fromAsset($asset)->clear()->save();
        }

        return $deleted;
    }

    /**
     * Delete a Mux asset by its ID
     */
    protected function deleteMuxAsset(string $muxId): bool
    {
        try {
            if (! $this->wasAssetCreatedByAddon($muxId)) {
                Log::notice(
                    'Cannot delete Mux asset: asset was not created by addon',
                    ['mux_id' => $muxId],
                );

                return false;
            }

            $this->api->assets()->deleteAsset($muxId);

            Log::info(
                'Deleted asset from Mux',
                ['mux_id' => $muxId],
            );

            return true;
        } catch (\Throwable $th) {
            Log::error(
                "Error deleting asset from Mux: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            throw new \Exception("Error deleting asset from Mux: {$th->getMessage()}", previous: $th);
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
            Log::debug(
                'Canceled Mux asset deletion via event listener',
                ['asset' => $asset->id(), 'mux_id' => $muxId, 'event' => 'AssetDeletingFromMux'],
            );

            return false;
        }

        $deleted = $this->deleteMuxAsset($muxId);

        if (! $deleted) {
            return false;
        }

        Log::info(
            'Deleted Mux asset connected to local asset',
            ['asset' => $asset->id(), 'mux_id' => $muxId],
        );

        AssetDeletedFromMux::dispatch($asset, $muxId);

        return true;
    }

    /**
     * Check if an asset was created by this addon.
     */
    protected function wasAssetCreatedByAddon(string $muxId): bool
    {
        $asset = $this->api->assets()->getAsset($muxId)->getData();
        $identifier = $asset?->getPassthrough() ?? null;
        $expected = ['statamic::', 'statamic-proxy::'];

        Log::debug(
            'Checking Mux asset ownership by passthrough identifier',
            ['passthrough' => $identifier, 'expected' => $expected],
        );

        return is_string($identifier) && Str::startsWith($identifier, $expected);
    }
}
