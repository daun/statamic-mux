<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Illuminate\Support\Arr;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;
use Statamic\Fields\Value;

trait GetsAssetFromContext
{
    protected $assetParams = ['src', 'path', 'url', 'asset'];

    protected function getNonAssetParams(): array
    {
        return Arr::except($this->params->all(), $this->assetParams);
    }

    /**
     * Get the asset model from a path or id in the context.
     */
    protected function getAssetFromContext($asset = null): ?Asset
    {
        if (! $asset) {
            if ($this->params->hasAny($this->assetParams)) {
                $asset = $this->params->get($this->assetParams);
            } else {
                $asset = $this->context->value('asset');
            }
        }

        if (is_string($asset)) {
            $asset = Assets::find($asset);
        } elseif ($asset instanceof Value) {
            $asset = Assets::find($asset->value());
        }

        if ($asset && $asset instanceof Asset) {
            return $asset;
        } else {
            return null;
        }
    }
}
