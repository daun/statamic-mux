<?php

namespace Daun\StatamicMux\Data\Actions;

use MuxPhp\Models\Asset as MuxApiAssetModel;

/**
 * Lightweight wrapper around a remote Mux asset for use with the Statamic Action system.
 */
class MuxLibraryItem
{
    public function __construct(
        protected string $muxId,
        protected ?MuxApiAssetModel $apiAsset = null,
        protected ?string $dashboardBaseUrl = null,
    ) {}

    public function id(): string
    {
        return $this->muxId;
    }

    public function apiAsset(): ?MuxApiAssetModel
    {
        return $this->apiAsset;
    }

    protected function normalizePolicy(mixed $policy): ?string
    {
        if ($policy === null) {
            return null;
        }

        if ($policy instanceof \BackedEnum) {
            return (string) $policy->value;
        }

        if (is_object($policy) && method_exists($policy, 'getValue')) {
            return $policy->getValue();
        }

        return (string) $policy;
    }
}
