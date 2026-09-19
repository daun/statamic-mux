<?php

namespace Daun\StatamicMux\Support;

use Illuminate\Support\Str;

class Attribution
{
    public const PREFIX = 'statamic::';

    public const PROXY_PREFIX = 'statamic-proxy::';

    public const LEGACY_PROXY_PREFIX = 'proxy::';

    /** Value written to Mux asset metadata as `creator_id` on every upload. */
    public const CREATOR_ID = 'statamic-mux';

    /**
     * Check if a passthrough identifier was created by this addon.
     */
    public static function createdByAddon(?string $passthrough): bool
    {
        return static::assetId($passthrough) !== null
            || static::proxyParentId($passthrough) !== null;
    }

    public static function assetId(?string $passthrough): ?string
    {
        return static::valueAfterPrefix($passthrough, self::PREFIX);
    }

    public static function proxyParentId(?string $passthrough): ?string
    {
        foreach ([self::PROXY_PREFIX, self::LEGACY_PROXY_PREFIX] as $prefix) {
            if ($id = static::valueAfterPrefix($passthrough, $prefix)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Check if a passthrough identifier is a proxy version.
     */
    public static function isProxy(?string $passthrough): bool
    {
        return static::proxyParentId($passthrough) !== null;
    }

    protected static function valueAfterPrefix(?string $value, string $prefix): ?string
    {
        if (! is_string($value) || ! Str::startsWith($value, $prefix)) {
            return null;
        }

        $id = Str::after($value, $prefix);

        return filled($id) ? $id : null;
    }
}
