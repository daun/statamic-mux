<?php

namespace Daun\StatamicMux\Concerns;

use Statamic\Assets\Asset;

trait GeneratesAssetData
{
    use ProcessesHooks;

    /**
     * Get complete data to send to Mux for asset creation.
     * The passthrough data is used to identify addon assets later, so it should not be overridden.
     */
    protected function getAssetData(Asset $asset): array
    {
        $defaults = ['passthrough' => $this->getAssetIdentifier($asset)];
        $data = ['meta' => $this->getAssetMeta($asset)];
        $result = $this->hooks('asset-data', (object) ['asset' => $asset, 'data' => $data]);

        return $defaults + $result->data ?? [];
    }

    /**
     * Get metadata to send to Mux during asset creation.
     */
    protected function getAssetMeta(Asset $asset): array
    {
        $meta = [
            'title' => $asset->title(),
            'creator_id' => 'statamic-mux',
            'external_id' => $asset->id(),
        ];

        $result = $this->hooks('asset-meta', (object) ['asset' => $asset, 'meta' => $meta]);

        return $result->meta ?? [];
    }

    /**
     * Get unique asset identifier.
     */
    protected function getAssetIdentifier(Asset $asset): string
    {
        return "statamic::{$asset->id()}";
    }
}
