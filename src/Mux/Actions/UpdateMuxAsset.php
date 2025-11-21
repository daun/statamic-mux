<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Concerns\GeneratesAssetData;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use MuxPhp\Models\UpdateAssetRequest;
use Statamic\Assets\Asset;

class UpdateMuxAsset
{
    use GeneratesAssetData;

    public function __construct(
        protected MuxService $service,
        protected MuxApi $api,
    ) {}

    /**
     * Update an asset's existing Mux metadata.
     */
    public function handle(Asset $asset): bool
    {
        if (! $this->shouldHandle($asset)) {
            return false;
        }

        try {
            $muxId = $this->service->getMuxId($asset);
            $data = $this->getAssetData($asset);
            $this->updateMuxData($muxId, $data);

            Log::info(
                'Updated asset data on Mux',
                ['asset' => $asset->id(), 'data' => $data],
            );
        } catch (\Throwable $th) {
            Log::error(
                "Failed to update asset data on Mux: {$th->getMessage()}",
                ['asset' => $asset->id(), 'exception' => $th],
            );

            throw new \Exception("Failed to update asset data on Mux: {$th->getMessage()}", previous: $th);
        }

        return true;
    }

    /**
     * Determine if the action should handle the asset.
     */
    protected function shouldHandle(Asset $asset): bool
    {
        if (! $asset->isVideo()) {
            return false;
        }

        $muxId = $this->service->getMuxId($asset);

        if (! $this->service->hasExistingMuxAsset($asset)) {
            Log::debug(
                'Skipping update of asset data on Mux: asset does not exist on Mux',
                ['asset' => $asset->id(), 'mux_id' => $muxId],
            );

            return false;
        }

        return true;
    }

    /**
     * Update data on Mux for an existing asset.
     */
    protected function updateMuxData(string $muxId, array $data): void
    {
        $request = new UpdateAssetRequest($data);

        $this->api->assets()->updateAsset($muxId, $request);
    }
}
