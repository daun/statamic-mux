<?php

namespace Daun\StatamicMux\Mux\Enums;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MuxPhp\Models\PlaybackPolicy;

enum MuxPlaybackPolicy: string
{
    case Public = PlaybackPolicy::_PUBLIC;
    case Signed = PlaybackPolicy::SIGNED;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function make(object|string|null $policy): ?self
    {
        if (! $policy) {
            return null;
        }

        if ($policy instanceof self) {
            return $policy;
        }

        if (is_object($policy) && method_exists($policy, 'getPolicy')) {
            $policy = $policy->getPolicy();
        }

        return self::tryFrom($policy);
    }

    public static function makeMany(array|string|null $policy): Collection
    {
        if (is_string($policy)) {
            $policy = Str::of($policy)->split('/\s*,\s*/')->map(fn ($item) => trim($item));
        }

        return collect($policy)->map(fn ($item) => self::make($item))->filter()->unique()->values();
    }

    public static function isValid(self|string|null $policy): bool
    {
        return (bool) self::make($policy);
    }

    public function is(self $check): bool
    {
        return $this->value === $check->value;
    }

    public function isPublic(): bool
    {
        return $this->is(self::Public);
    }

    public function isSigned(): bool
    {
        return $this->is(self::Signed);
    }
}
