<?php

namespace Daun\StatamicMux\Support;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\Asset as Assets;
use Statamic\Facades\AssetContainer as AssetContainers;
use Statamic\Fields\Field;

class MirrorField
{
    public static function configured(): bool
    {
        return Mux::configured();
    }

    public static function enabled(): bool
    {
        return (bool) config('mux.mirror.enabled', true);
    }

    protected static function enabledForAsset(Asset $asset): bool
    {
        return static::supportsAssetType($asset) && static::existsInBlueprint($asset);
    }

    public static function supportsAssetType(Asset $asset): bool
    {
        return $asset->isVideo();
    }

    public static function shouldMirror(Asset $asset): bool
    {
        return static::configured() && static::enabled() && static::enabledForAsset($asset);
    }

    public static function shouldUpdateMeta(): bool
    {
        return (bool) config('mux.mirror.sync_meta', true);
    }

    public static function existsInBlueprint(Asset|AssetContainer $asset): bool
    {
        return (bool) static::getFromBlueprint($asset);
    }

    public static function getFromBlueprint(Asset|AssetContainer|null $asset): ?Field
    {
        return $asset?->blueprint()->fields()->all()->first(
            fn (Field $field) => $field->type() === MuxMirrorFieldtype::handle()
        );
    }

    public static function getHandle(Asset|AssetContainer|null $asset): ?string
    {
        return static::getFromBlueprint($asset)?->handle();
    }

    public static function containers(): Collection
    {
        return AssetContainers::all()
            ->filter(fn (AssetContainer $container) => static::existsInBlueprint($container))
            ->values();
    }

    public static function assets(): Collection
    {
        return static::containers()->flatMap(
            fn ($container) => Assets::whereContainer($container->handle())->filter(
                fn ($asset) => MirrorField::enabledForAsset($asset)
            )
        )->values();
    }

    /**
     * Find all local assets referencing the given Mux id, optionally excluding one.
     */
    public static function assetsByMuxId(string $muxId, ?Asset $except = null): Collection
    {
        $results = static::containers()->flatMap(function (AssetContainer $container) use ($muxId) {
            $handle = static::getHandle($container);
            if (! $handle) {
                return collect();
            }

            return Assets::query()
                ->where('container', $container->handle())
                ->whereJsonContains("{$handle}.id", $muxId)
                ->get();
        });

        if ($except) {
            $results = $results->reject(fn (Asset $asset) => $asset->id() === $except->id());
        }

        return $results->values();
    }

    public static function clear(Asset $asset): void
    {
        if ($handle = static::getHandle($asset)) {
            $asset->set($handle, null);
        }
    }
}
