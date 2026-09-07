<?php

namespace Daun\StatamicMux\Mux;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Stored forever with a separate short-lived validity key, so a cold cache serves stale data instead of blocking on a full paginated API walk. */
class RemoteAssetCache
{
    protected const CACHE_KEY = 'mux.remote_assets';

    protected const CACHE_VALIDITY_KEY = 'mux.remote_assets.valid';

    protected const CACHE_TTL = 3600;

    public function __construct(
        protected MuxApi $api,
    ) {}

    public function get(): Collection
    {
        if (! Cache::has(self::CACHE_VALIDITY_KEY)) {
            return $this->seed($this->fetchAll());
        }

        return Cache::get(self::CACHE_KEY) ?? $this->fetchAll();
    }

    public function getIfAvailable(): Collection
    {
        return Cache::get(self::CACHE_KEY) ?? collect();
    }

    public function seed(Collection $assets): Collection
    {
        $assets = $assets->values();

        Cache::forever(self::CACHE_KEY, $assets);
        Cache::put(self::CACHE_VALIDITY_KEY, true, self::CACHE_TTL);

        return $assets;
    }

    public function refresh(): Collection
    {
        $this->invalidate();

        return $this->get();
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_VALIDITY_KEY);
    }

    public function forget(string $muxId): void
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! $cached) {
            return;
        }

        $filtered = $cached->reject(fn ($asset) => $asset->getId() === $muxId);

        if ($filtered->count() < $cached->count()) {
            Cache::forever(self::CACHE_KEY, $filtered);
        }
    }

    public function only(Collection $muxIds): Collection
    {
        if ($muxIds->isEmpty()) {
            return collect();
        }

        $cached = Cache::get(self::CACHE_KEY);
        $index = collect();
        $uncached = $muxIds;

        if ($cached) {
            $index = $cached->keyBy(fn ($asset) => $asset->getId())->only($muxIds->all());
            $uncached = $muxIds->diff($index->keys());
        }

        if ($uncached->isNotEmpty()) {
            $index = $index->merge($this->api->getAssets($uncached));
        }

        return $index;
    }

    // Fetches every page with no cap: may time out for very large Mux accounts.
    protected function fetchAll(): Collection
    {
        return $this->api->listAllAssets();
    }
}
