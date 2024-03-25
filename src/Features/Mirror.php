<?php

namespace Daun\StatamicMux\Features;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Fieldtypes\MuxMirror;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Fields\Field;

class Mirror
{
    public static function configured(): bool
    {
        return Mux::configured();
    }

    public static function enabled(): bool
    {
        return config('mux.mirror.enabled', true);
    }

    public static function enabledForAsset(Asset $asset): bool
    {
        return $asset->isVideo() && static::hasMirrorField($asset);
    }

    public static function enabledForContainer(AssetContainer $container): bool
    {
        return static::hasMirrorField($container);
    }

    public static function shouldMirror(Asset $asset): bool
    {
        return static::enabled() && static::enabledForAsset($asset);
    }

    public static function hasMirrorField(Asset|AssetContainer $asset): bool
    {
        return (bool) static::getMirrorField($asset);
    }

    public static function getMirrorField(Asset|AssetContainer|null $asset): ?string
    {
        return $asset?->blueprint()->fields()->all()->first(
            fn (Field $field) => $field->type() === MuxMirror::handle()
        )?->handle();
    }

    public static function containers(): Collection
    {
        return AssetContainerFacade::all()->filter(
            fn (AssetContainer $container) => static::enabledForContainer($container)
        );
    }
}
