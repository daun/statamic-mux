<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;

class MuxPlaybackPolicies
{
    public static function sanitize(MuxPlaybackPolicy|string|null|array $policy): array
    {
        if (is_string($policy)) {
            $policy = preg_split('/\s*,\s*/', $policy);
        }

        return collect($policy)
            ->map(fn ($item) => MuxPlaybackPolicy::make($item))
            ->filter()
            ->all();
    }

    public static function isValid(MuxPlaybackPolicy|string|null $policy): bool
    {
        return MuxPlaybackPolicy::valid($policy);
    }

    public static function hasPublicPolicy(mixed $item): bool
    {
        return static::hasPolicy($item, MuxPlaybackPolicy::Public);
    }

    public function hasSignedPlaybackPolicy(mixed $item): bool
    {
        return static::hasPolicy($item, MuxPlaybackPolicy::Signed);
    }

    public function hasPolicy(mixed $item, MuxPlaybackPolicy $policy): bool
    {
        if (! $item) {
            return false;
        }

        return
            $item === $policy ||
            (is_array($item) && in_array($policy, $item)) ||
            (is_object($item) && method_exists($item, 'getPolicy') && $item->getPolicy() === $policy);
    }
}
