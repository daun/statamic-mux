<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Concerns\GeneratesAssetData;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Log;
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
        if (! $asset->isVideo()) {
            return false;
        }

        if (! $this->service->hasExistingMuxAsset($asset)) {
            return false;
        }

        try {
            $this->updateMuxData($asset);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to update asset data on Mux: {$th->getMessage()}");
        }

        return true;
    }

    /**
     * Update data on Mux for an existing asset.
     */
    protected function updateMuxData(Asset $asset): void
    {
        $muxId = $this->service->getMuxId($asset);

        $request = new UpdateAssetRequest($this->getAssetData($asset));

        $this->api->assets()->updateAsset($muxId, $request)->getData();
    }
}
