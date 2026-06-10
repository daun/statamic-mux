<?php

namespace Daun\StatamicMux\Data\Actions;

use MuxPhp\Models\Asset as MuxApiAsset;

/**
 * Lightweight wrapper around a remote Mux asset for use with the Statamic Action system.
 */
class MuxLibraryItem
{
    public function __construct(
        protected string $muxId,
        protected ?MuxApiAsset $apiAsset = null,
        protected ?string $dashboardBaseUrl = null,
    ) {}

    public function id(): string
    {
        return $this->muxId;
    }

    public function apiAsset(): ?MuxApiAsset
    {
        return $this->apiAsset;
    }

    public function dashboardUrl(): ?string
    {
        if (! $this->dashboardBaseUrl) {
            return null;
        }

        return rtrim($this->dashboardBaseUrl, '/').'/video/assets/'.rawurlencode($this->muxId).'/monitor';
    }

    public function primaryPlaybackId(): ?string
    {
        if (! $this->apiAsset) {
            return null;
        }

        $playbackIds = collect($this->apiAsset->getPlaybackIds() ?? []);

        $public = $playbackIds->first(function ($pid) {
            $policy = $pid->getPolicy();

            return $this->normalizePolicy($policy) === 'public';
        });

        return $public?->getId() ?? $playbackIds->first()?->getId();
    }

    public function playerUrl(): ?string
    {
        $id = $this->primaryPlaybackId();

        return $id ? "https://player.mux.com/{$id}" : null;
    }

    public function embedCode(): ?string
    {
        $url = $this->playerUrl();

        if (! $url) {
            return null;
        }

        return '<iframe src="'.$url.'" style="width: 100%; border: none; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen ></iframe>';
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
