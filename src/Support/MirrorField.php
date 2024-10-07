<?php

namespace Daun\StatamicMux\Support;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Fields\Field;

class MirrorField
{
    public static function configured(): bool
    {
        return Mux::configured();
    }

    public static function enabled(): bool
    {
        return config('mux.mirror.enabled', true);
    }

    protected static function enabledForAsset(Asset $asset): bool
    {
        return static::supportsAssetType($asset) && static::existsInBlueprint($asset);
    }

    protected static function supportsAssetType(Asset $asset): bool
    {
        return $asset->isVideo();
    }

    public static function shouldMirror(Asset $asset): bool
    {
        return static::configured() && static::enabled() && static::enabledForAsset($asset);
    }

    public static function existsInBlueprint(Asset|AssetContainer $asset): bool
    {
        return (bool) static::getFromBlueprint($asset);
    }

    public static function assertExistsInBlueprint(Asset $asset): bool
    {
        if (static::existsInBlueprint($asset)) {
            return true;
        } else {
            throw new \Exception('This asset does not have a mux mirror field in its blueprint.');
        }
    }

    public static function getFromBlueprint(Asset|AssetContainer|null $asset): ?Field
    {
        return $asset?->blueprint()->fields()->all()->first(
            fn (Field $field) => $field->type() === MuxMirrorFieldtype::handle()
        );
    }

    public static function containers(): Collection
    {
        return AssetContainerFacade::all()->filter(
            fn (AssetContainer $container) => static::existsInBlueprint($container)
        );
    }

    public static function assets(): Collection
    {
        return static::containers()->flatMap(
            fn ($container) => AssetFacade::whereContainer($container->handle())->filter(
                fn ($asset) => MirrorField::enabledForAsset($asset)
            )
        );
    }
}
