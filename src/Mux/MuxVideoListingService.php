<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Assets\Asset;

class MuxVideoListingService
{
    protected const CACHE_KEY = 'mux.remote_assets';

    protected const CACHE_TTL = 600; // 10 minutes

    public function __construct(
        protected MuxService $muxService,
    ) {}

    /**
     * Get local video assets with Mux data, enriched with remote state.
     */
    public function getLocalVideos(array $params = []): array
    {
        $remoteIndex = $this->getRemoteAssetsIndex();
        $items = $this->buildLocalRows($remoteIndex);

        $items = $this->applySearch($items, $params['search'] ?? null);
        $items = $this->applyLocalFilters($items, $params['filters'] ?? []);
        $items = $this->applySort($items, $params['sort'] ?? 'title', $params['order'] ?? 'asc');

        return $this->paginate($items, $params['page'] ?? 1, $params['perPage'] ?? 25);
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
        return $this->muxService->listMuxAssets(0);
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
     * Build rows for the local tab.
     */
    protected function buildLocalRows(Collection $remoteIndex): Collection
    {
        return MirrorField::assets()->map(function (Asset $asset) use ($remoteIndex) {
            $muxAsset = MuxAsset::fromAsset($asset);
            $muxId = $muxAsset->id();
            $existsRemotely = $muxId && $remoteIndex->has($muxId);

            return [
                'id' => $asset->id(),
                'title' => $asset->get('title') ?: basename($asset->path()),
                'path' => $asset->path(),
                'container' => $asset->containerHandle(),
                'mux_id' => $muxId,
                'has_mux_data' => $muxAsset->exists(),
                'exists_remotely' => $existsRemotely,
                'is_stale' => $muxAsset->exists() && ! $existsRemotely,
                'status' => $this->getLocalMuxStatus($muxAsset, $existsRemotely ? $remoteIndex->get($muxId) : null),
                'duration' => $this->getLocalDuration($muxAsset, $existsRemotely ? $remoteIndex->get($muxId) : null),
                'playback_policy' => $this->getLocalPlaybackPolicy($muxAsset),
                'created_at' => $this->getLocalMuxCreatedAt($existsRemotely ? $remoteIndex->get($muxId) : null),
                'thumbnail_url' => $this->getLocalThumbnailUrl($asset),
                'is_proxy' => $muxAsset->isProxy(),
            ];
        });
    }

    /**
     * Build rows for the remote tab.
     */
    protected function buildRemoteRows(Collection $localIndex): Collection
    {
        return $this->getCachedRemoteAssets()->map(function ($muxAsset) use ($localIndex) {
            $muxId = $muxAsset->getId();
            $localMatches = $localIndex->get($muxId, collect());
            $localCount = $localMatches->count();

            $state = match (true) {
                $localCount === 0 => 'orphaned',
                $localCount > 1 => 'duplicated',
                default => 'mirrored',
            };

            $playbackIds = collect($muxAsset->getPlaybackIds() ?? []);
            $publicPlaybackId = $playbackIds->first(fn ($pid) => $pid->getPolicy() === 'public');
            $firstPlaybackId = $publicPlaybackId ?? $playbackIds->first();

            return [
                'id' => $muxId,
                'title' => $muxAsset->getMeta()?->getTitle() ?: $muxId,
                'mux_id' => $muxId,
                'state' => $state,
                'local_matches' => $localCount,
                'status' => $muxAsset->getStatus(),
                'duration' => $muxAsset->getDuration(),
                'playback_policy' => $this->getRemotePlaybackPolicy($muxAsset),
                'resolution_tier' => $muxAsset->getResolutionTier(),
                'max_resolution_tier' => $muxAsset->getMaxResolutionTier(),
                'is_test' => (bool) $muxAsset->getTest(),
                'created_at' => $muxAsset->getCreatedAt() ? Carbon::createFromTimestamp($muxAsset->getCreatedAt())->toIso8601String() : null,
                'thumbnail_url' => $firstPlaybackId ? "https://image.mux.com/{$firstPlaybackId->getId()}/thumbnail.webp?width=120" : null,
                'aspect_ratio' => $muxAsset->getAspectRatio(),
            ];
        });
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

        $policies = $playbackIds->map(fn ($pid) => $pid->getPolicy())->unique()->sort()->values();

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
     * Apply filters for local tab.
     */
    protected function applyLocalFilters(Collection $items, array $filters): Collection
    {
        return $this->applyFilters($items, $filters, ['status', 'playback_policy', 'local_state']);
    }

    /**
     * Apply filters for remote tab.
     */
    protected function applyRemoteFilters(Collection $items, array $filters): Collection
    {
        return $this->applyFilters($items, $filters, ['status', 'state', 'playback_policy', 'resolution_tier', 'is_test', 'duration_range']);
    }

    protected function applyFilters(Collection $items, array $filters, array $allowed): Collection
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $value = $filter['value'] ?? null;

            if (! $field || $value === null || ! in_array($field, $allowed)) {
                continue;
            }

            $items = match ($field) {
                'local_state' => $this->filterLocalState($items, $value),
                'duration_range' => $this->filterDurationRange($items, $value),
                'is_test' => $items->where('is_test', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
                default => is_array($value)
                    ? $items->whereIn($field, $value)
                    : $items->where($field, $value),
            };
        }

        return $items;
    }

    protected function filterLocalState(Collection $items, string $value): Collection
    {
        return match ($value) {
            'mirrored' => $items->where('has_mux_data', true)->where('exists_remotely', true),
            'stale' => $items->where('is_stale', true),
            'waiting' => $items->where('has_mux_data', false),
            default => $items,
        };
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
