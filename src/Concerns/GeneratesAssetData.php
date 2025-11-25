<?php

namespace Daun\StatamicMux\Concerns;

use Statamic\Assets\Asset;

trait GeneratesAssetData
{
    use ProcessesHooks;

    /**
     * Get settings to apply when creating a new Mux asset.
     * Video quality, playback policy, etc.
     */
    protected function getAssetSettings(Asset $asset): array
    {
        $result = $this->hooks('asset-settings', ['asset' => $asset, 'settings' => []]);

        return $result->settings ?? [];
    }

    /**
     * Get data to send when creating a new Mux asset or updating an existing one.
     * The passthrough data is used to identify addon assets later.
     */
    protected function getAssetData(Asset $asset): array
    {
        return [
            'passthrough' => $this->getAssetIdentifier($asset),
            'meta' => $this->getAssetMeta($asset),
        ];
    }

    /**
     * Get metadata to send to Mux during asset creation and updates.
     */
    protected function getAssetMeta(Asset $asset): array
    {
        $meta = [
            'title' => $asset->title(),
            'creator_id' => 'statamic-mux',
            'external_id' => $asset->id(),
        ];

        $result = $this->hooks('asset-meta', ['asset' => $asset, 'meta' => $meta]);

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
