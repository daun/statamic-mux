<?php

namespace Daun\StatamicMux\Mux;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the full list of remote Mux assets.
 *
 * The list is stored forever and a separate short-lived validity key decides
 * when to refetch, so a cold validity key still serves stale data rather than
 * blocking a request on a full paginated API walk.
 */
class RemoteAssetCache
{
    protected const CACHE_KEY = 'mux.remote_assets';

    protected const CACHE_VALIDITY_KEY = 'mux.remote_assets.valid';

    protected const CACHE_TTL = 3600;

    public function __construct(
        protected MuxApi $api,
    ) {}

    /**
     * Get remote Mux assets, fetching from the API if stale.
     */
    public function get(): Collection
    {
        if (! Cache::has(self::CACHE_VALIDITY_KEY)) {
            return $this->seed($this->fetchAll());
        }

        return Cache::get(self::CACHE_KEY) ?? $this->fetchAll();
    }

    /**
     * Get cached remote assets without triggering a fetch.
     * Returns whatever is cached, even if stale, or an empty collection.
     */
    public function getIfAvailable(): Collection
    {
        return Cache::get(self::CACHE_KEY) ?? collect();
    }

    /**
     * Seed the cache from a complete, freshly fetched list.
     */
    public function seed(Collection $assets): Collection
    {
        $assets = $assets->values();

        Cache::forever(self::CACHE_KEY, $assets);
        Cache::put(self::CACHE_VALIDITY_KEY, true, self::CACHE_TTL);

        return $assets;
    }

    /**
     * Refresh the cache from the API and return the fresh list.
     */
    public function refresh(): Collection
    {
        $this->invalidate();

        return $this->get();
    }

    /**
     * Mark the cache stale without refetching. The next get() fetches fresh data.
     */
    public function invalidate(): void
    {
        Cache::forget(self::CACHE_VALIDITY_KEY);
    }

    /**
     * Drop a single asset from the cached list by Mux ID.
     */
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

    /**
     * Look up individual Mux assets by ID, keyed by Mux ID.
     * Only calls the API for IDs missing from the cached list.
     */
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

    /**
     * Fetches every page with no cap. May time out for very large Mux accounts.
     */
    protected function fetchAll(): Collection
    {
        return $this->api->listAllAssets();
    }
}
