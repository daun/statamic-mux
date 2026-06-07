<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Assets\Asset;
use Statamic\Facades\User;

class ListingReconciler
{
    protected const CACHE_KEY = 'mux.remote_assets';

    protected const CACHE_TTL = 600; // 10 minutes

    public function __construct(
        protected MuxApi $api,
    ) {}

    /**
     * Get local video assets with Mux data, enriched with remote state.
     *
     * Builds rows from local data first, paginates, then batch-fetches
     * only the Mux IDs on the current page for enrichment.
     */
    public function getLocalVideos(array $params = []): array
    {
        $items = $this->buildLocalRows();

        $items = $this->applySearch($items, $params['search'] ?? null);
        $items = $this->applySort($items, $params['sort'] ?? 'title', $params['order'] ?? 'asc');
        $result = $this->paginate($items, $params['page'] ?? 1, $params['perPage'] ?? 25);

        $result['data'] = $this->enrichLocalRowsWithRemoteData($result['data']);

        return $result;
    }

    /**
     * Get remote Mux assets, enriched with local state.
     */
    public function getRemoteVideos(array $params = []): array
    {
        $localIndex = $this->getLocalAssetsIndex();
        $items = $this->buildRemoteRows($localIndex);

        $items = $this->applySearch($items, $params['search'] ?? null);
        $items = $this->applyRemoteFilters($items, $params['filters'] ?? []);
        $items = $this->applySort($items, $params['sort'] ?? 'created_at', $params['order'] ?? 'desc');

        return $this->paginate($items, $params['page'] ?? 1, $params['perPage'] ?? 25);
    }

    /**
     * Refresh cached remote assets. Returns fresh list.
     */
    public function refreshRemoteAssets(): Collection
    {
        Cache::forget(self::CACHE_KEY);

        return $this->getCachedRemoteAssets();
    }

    /**
     * Get all remote Mux assets, cached for 10 minutes.
     */
    public function getCachedRemoteAssets(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchAllRemoteAssets();
        });
    }

    /**
     * Fetch all remote Mux assets across all pages.
     *
     * Note: fetches all pages with no cap. May time out for very large Mux accounts.
     */
    protected function fetchAllRemoteAssets(): Collection
    {
        return $this->api->listAllAssets();
    }

    /**
     * Fetch individual Mux assets by their IDs. Returns collection keyed by Mux ID.
     * Only makes API calls for IDs not found in the remote cache.
     */
    protected function fetchMuxAssetsByIds(Collection $muxIds): Collection
    {
        if ($muxIds->isEmpty()) {
            return collect();
        }

        // Check the remote cache first to avoid unnecessary API calls
        $cached = Cache::get(self::CACHE_KEY);
        $index = collect();
        $uncached = $muxIds;

        if ($cached) {
            $cachedIndex = $cached->keyBy(fn ($asset) => $asset->getId());
            $index = $cachedIndex->only($muxIds->all());
            $uncached = $muxIds->diff($index->keys());
        }

        $index = $index->merge($this->api->getAssets($uncached));

        return $index;
    }

    /**
     * Enrich paginated local rows with remote Mux data.
     */
    protected function enrichLocalRowsWithRemoteData(array $rows): array
    {
        $muxIds = collect($rows)->pluck('mux_id')->filter()->unique()->values();
        $remoteIndex = $this->fetchMuxAssetsByIds($muxIds);

        return collect($rows)->map(function (array $row) use ($remoteIndex) {
            $muxId = $row['mux_id'];
            $remote = $muxId ? $remoteIndex->get($muxId) : null;
            $existsRemotely = $remote !== null;

            $row['exists_remotely'] = $existsRemotely;
            $row['is_stale'] = $row['has_mux_data'] && ! $existsRemotely;
            $row['status'] = $this->getLocalMuxStatus($row['mux_asset'], $remote);
            $row['duration'] = $this->getLocalDuration($row['mux_asset'], $remote);
            $row['created_at'] = $this->getLocalMuxCreatedAt($remote);

            if ($remote && empty($row['playback_ids'])) {
                $row['playback_ids'] = $this->getRemotePlaybackIds($remote);
                $row['playback_id'] = $this->getPrimaryPlaybackId($row['playback_ids']);
            }

            unset($row['mux_asset']);

            return $row;
        })->all();
    }

    /**
     * Index remote assets by Mux ID for O(1) lookups.
     */
    protected function getRemoteAssetsIndex(): Collection
    {
        return $this->getCachedRemoteAssets()->keyBy(fn ($asset) => $asset->getId());
    }

    /**
     * Index local assets by Mux ID, grouped to detect duplicates.
     */
    protected function getLocalAssetsIndex(): Collection
    {
        return MirrorField::assets()
            ->map(fn (Asset $asset) => [
                'asset' => $asset,
                'mux' => MuxAsset::fromAsset($asset),
            ])
            ->filter(fn ($item) => $item['mux']->exists())
            ->groupBy(fn ($item) => $item['mux']->id());
    }

    /**
     * Build rows for the local tab from local data only.
     * Remote-dependent fields (exists_remotely, is_stale, status, duration, created_at)
     * are set to defaults and enriched later via enrichLocalRowsWithRemoteData().
     */
    protected function buildLocalRows(): Collection
    {
        $user = User::current();
        $dashboardUrl = $this->api->dashboardUrl();

        return MirrorField::assets()->map(function (Asset $asset) use ($user, $dashboardUrl) {
            $muxAsset = MuxAsset::fromAsset($asset);
            $muxId = $muxAsset->id();
            $canEdit = $user?->can('edit', $asset) ?? false; // @phpstan-ignore method.notFound

            $playbackIds = $this->getLocalPlaybackIds($muxAsset);

            return [
                'id' => $asset->id(),
                'title' => $asset->get('title') ?: basename($asset->path()),
                'path' => $asset->path(),
                'container' => $asset->containerHandle(),
                'edit_url' => $canEdit ? $asset->editUrl() : null,
                'can_edit' => $canEdit,
                'mux_id' => $muxId,
                'dashboard_url' => $this->dashboardAssetUrl($muxId, $dashboardUrl),
                'has_mux_data' => $muxAsset->exists(),
                'exists_remotely' => null,
                'status' => $muxAsset->exists() ? null : 'waiting',
                'is_stale' => false,
                'duration' => $muxAsset->duration(),
                'playback_policy' => $this->getLocalPlaybackPolicy($muxAsset),
                'playback_id' => $this->getPrimaryPlaybackId($playbackIds),
                'playback_ids' => $playbackIds,
                'created_at' => null,
                'thumbnail_url' => $this->getLocalThumbnailUrl($asset),
                'is_proxy' => $muxAsset->isProxy(),
                'mux_asset' => $muxAsset, // carried through for enrichment, stripped before response
            ];
        });
    }

    /**
     * Build rows for the remote tab.
     */
    protected function buildRemoteRows(Collection $localIndex): Collection
    {
        $dashboardUrl = $this->api->dashboardUrl();

        return $this->getCachedRemoteAssets()->map(function ($muxAsset) use ($localIndex, $dashboardUrl) {
            $muxId = $muxAsset->getId();
            $localMatches = $localIndex->get($muxId, collect());
            $localCount = $localMatches->count();

            $state = match (true) {
                $localCount === 0 => 'orphaned',
                $localCount > 1 => 'duplicated',
                default => 'mirrored',
            };

            $playbackIds = $this->getRemotePlaybackIds($muxAsset);
            $playbackId = $this->getPrimaryPlaybackId($playbackIds);

            return [
                'id' => $muxId,
                'title' => $muxAsset->getMeta()?->getTitle() ?: $muxId,
                'mux_id' => $muxId,
                'dashboard_url' => $this->dashboardAssetUrl($muxId, $dashboardUrl),
                'state' => $state,
                'local_matches' => $localCount,
                'status' => $muxAsset->getStatus(),
                'duration' => $muxAsset->getDuration(),
                'playback_policy' => $this->getRemotePlaybackPolicy($muxAsset),
                'playback_id' => $playbackId,
                'playback_ids' => $playbackIds,
                'resolution_tier' => $muxAsset->getResolutionTier(),
                'max_resolution_tier' => $muxAsset->getMaxResolutionTier(),
                'is_test' => (bool) $muxAsset->getTest(),
                'created_at' => $muxAsset->getCreatedAt() ? Carbon::createFromTimestamp($muxAsset->getCreatedAt())->toIso8601String() : null,
                'thumbnail_url' => $playbackId ? "https://image.mux.com/{$playbackId}/thumbnail.webp?width=120" : null,
                'aspect_ratio' => $muxAsset->getAspectRatio(),
            ];
        });
    }

    protected function dashboardAssetUrl(?string $muxId, ?string $dashboardUrl): ?string
    {
        if (! $muxId || ! $dashboardUrl) {
            return null;
        }

        $baseUrl = rtrim($dashboardUrl, '/');

        return "{$baseUrl}/video/assets/".rawurlencode($muxId).'/monitor';
    }

    protected function getLocalMuxStatus(MuxAsset $muxAsset, $remoteAsset = null): ?string
    {
        if (! $muxAsset->exists()) {
            return 'waiting';
        }

        if (! $remoteAsset) {
            return 'stale';
        }

        return $remoteAsset->getStatus();
    }

    protected function getLocalDuration(MuxAsset $muxAsset, $remoteAsset = null): ?float
    {
        if ($remoteAsset) {
            return $remoteAsset->getDuration();
        }

        return $muxAsset->duration();
    }

    protected function getLocalPlaybackPolicy(MuxAsset $muxAsset): ?string
    {
        $playbackId = $muxAsset->playbackId();

        return $playbackId?->policy();
    }

    protected function getLocalPlaybackIds(MuxAsset $muxAsset): array
    {
        return collect($muxAsset->playbackIds()->all())
            ->map(fn ($playbackId) => [
                'id' => $playbackId->id(),
                'policy' => $playbackId->policy(),
            ])
            ->values()
            ->all();
    }

    protected function getRemotePlaybackIds($muxAsset): array
    {
        return collect($muxAsset->getPlaybackIds() ?? [])
            ->map(fn ($playbackId) => [
                'id' => $playbackId->getId(),
                'policy' => $this->normalizePlaybackPolicy($playbackId->getPolicy()),
            ])
            ->filter(fn ($playbackId) => $playbackId['id'])
            ->values()
            ->all();
    }

    protected function getPrimaryPlaybackId(array $playbackIds): ?string
    {
        $playbackIds = collect($playbackIds);
        $playbackId = $playbackIds->firstWhere('policy', 'public') ?? $playbackIds->first();

        return $playbackId['id'] ?? null;
    }

    protected function normalizePlaybackPolicy(mixed $policy): ?string
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

    protected function getLocalMuxCreatedAt($remoteAsset = null): ?string
    {
        if (! $remoteAsset) {
            return null;
        }

        $timestamp = $remoteAsset->getCreatedAt();

        return $timestamp ? Carbon::createFromTimestamp($timestamp)->toIso8601String() : null;
    }

    protected function getLocalThumbnailUrl(Asset $asset): ?string
    {
        $id = base64_encode($asset->id());

        return cp_route('mux.thumbnail', $id);
    }

    protected function getRemotePlaybackPolicy($muxAsset): ?string
    {
        $playbackIds = collect($muxAsset->getPlaybackIds() ?? []);

        if ($playbackIds->isEmpty()) {
            return null;
        }

        $policies = $playbackIds->map(fn ($pid) => $this->normalizePlaybackPolicy($pid->getPolicy()))->unique()->sort()->values();

        if ($policies->count() === 1) {
            return $policies->first();
        }

        return $policies->implode(', ');
    }

    /**
     * Search across title, path, and mux_id.
     */
    protected function applySearch(Collection $items, ?string $search): Collection
    {
        if (! $search) {
            return $items;
        }

        $search = mb_strtolower($search);

        return $items->filter(function ($item) use ($search) {
            return str_contains(mb_strtolower($item['title'] ?? ''), $search)
                || str_contains(mb_strtolower($item['path'] ?? ''), $search)
                || str_contains(mb_strtolower($item['mux_id'] ?? ''), $search);
        });
    }

    /**
     * Apply filters for remote tab.
     */
    protected function applyRemoteFilters(Collection $items, array $filters): Collection
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $value = $filter['value'] ?? null;

            if (! $field || $value === null) {
                continue;
            }

            $items = match ($field) {
                'duration_range' => $this->filterDurationRange($items, $value),
                'is_test' => $items->where('is_test', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
                'status', 'state', 'playback_policy', 'resolution_tier' => is_array($value)
                    ? $items->whereIn($field, $value)
                    : $items->where($field, $value),
                default => $items,
            };
        }

        return $items;
    }

    protected function filterDurationRange(Collection $items, string $value): Collection
    {
        return match ($value) {
            'short' => $items->filter(fn ($i) => ($i['duration'] ?? 0) > 0 && $i['duration'] <= 60),
            'medium' => $items->filter(fn ($i) => ($i['duration'] ?? 0) > 60 && $i['duration'] <= 600),
            'long' => $items->filter(fn ($i) => ($i['duration'] ?? 0) > 600),
            default => $items,
        };
    }

    protected function applySort(Collection $items, string $field, string $direction): Collection
    {
        $descending = strtolower($direction) === 'desc';

        return $items->sortBy($field, SORT_REGULAR, $descending)->values();
    }

    protected function paginate(Collection $items, int $page, int $perPage): array
    {
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $items->slice($offset, $perPage)->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
        ];
    }
}
