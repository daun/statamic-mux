<?php

namespace Daun\StatamicMux\Support;

use Illuminate\Support\Str;

class Attribution
{
    public const PREFIX = 'statamic::';

    public const PROXY_PREFIX = 'statamic-proxy::';

    public const LEGACY_PROXY_PREFIX = 'proxy::';

    /**
     * Check if a passthrough identifier was created by this addon.
     */
    public static function createdByAddon(?string $passthrough): bool
    {
        return is_string($passthrough) && Str::startsWith($passthrough, [
            self::PREFIX,
            self::PROXY_PREFIX,
            self::LEGACY_PROXY_PREFIX,
        ]);
    }

    /**
     * Check if a passthrough identifier is a proxy version.
     */
    public static function isProxy(?string $passthrough): bool
    {
        return is_string($passthrough) && Str::startsWith($passthrough, [
            self::PROXY_PREFIX,
            self::LEGACY_PROXY_PREFIX,
        ]);
    }
}
