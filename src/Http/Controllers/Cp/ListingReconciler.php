<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Daun\StatamicMux\Mux\RemoteVideo;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Facades\User;
use Statamic\Support\Str;

class ListingReconciler
{
    protected const ListingThumbnailSize = 100;

    protected const CopyThumbnailSize = 400;

    public function __construct(
        protected MuxApi $api,
        protected MuxService $service,
        protected ThumbnailService $thumbnails,
        protected RemoteAssetCache $cache,
        protected Reconciler $reconciler,
    ) {}

    /**
     * Get local video assets with Mux data, enriched with remote data.
     *
     * Builds rows from local data first, paginates, then batch-fetches
     * only the Mux IDs on the current page for enrichment.
     */
    public function getLocalVideos(array $params = []): array
    {
        $items = $this->buildLocalRows();

        $items = $this->applyRowsFilter($items, $params['rows'] ?? []);
        $items = $this->applySearch($items, $params['search'] ?? null);
        $items = $this->applySort($items, $params['sort'] ?? 'title', $params['order'] ?? 'asc');
        $result = $this->paginate($items, $params['page'] ?? 1, $params['perPage'] ?? 25);

        $result['data'] = $this->enrichLocalRowsWithRemoteData($result['data']);

        return $result;
    }

    protected function applyRowsFilter(Collection $items, array $rows): Collection
    {
        if (empty($rows)) {
            return $items;
        }

        return $items->filter(fn (array $item) => in_array($item['id'], $rows, true)
            || in_array($item['path'], $rows, true));
    }

    /**
     * Get remote Mux assets, enriched with local data.
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
     * Enrich paginated local rows with remote Mux data.
     *
     * Remote is authoritative: when an asset still exists on Mux, its remote
     * values override the placeholders/fallbacks set in buildLocalRows(). When
     * it doesn't (deleted on Mux, or never uploaded), the remote-only fields
     * stay empty and duration/created_at keep their local fallback.
     */
    protected function enrichLocalRowsWithRemoteData(array $rows): array
    {
        $muxIds = collect($rows)->pluck('mux_id')->filter()->unique()->values();
        $remoteIndex = $this->cache->only($muxIds);

        return collect($rows)->map(function (array $row) use ($remoteIndex) {
            $muxId = $row['mux_id'];
            $remote = $muxId ? $remoteIndex->get($muxId) : null;

            if ($remote) {
                $remoteRow = $this->normalizeRow(RemoteVideo::make($remote));
                $row = array_merge($row, Arr::only($remoteRow, [
                    'processing_status',
                    'duration',
                    'duration_formatted',
                    'playback_ids',
                    'playback_id',
                    'playback_policy',
                    'thumbnail_url',
                    'thumbnail_copy_url',
                    'player_url',
                    'stream_url',
                    'embed_code',
                    'created_at',
                ]));
            }

            $row['exists_remotely'] = $remote !== null;
            $row['mirror_status'] = match (true) {
                $remote !== null => 'uploaded',
                $muxId !== null => 'missing',
                default => 'not_uploaded',
            };

            return $row;
        })->all();
    }

    /**
     * Index remote assets by Mux ID for O(1) lookups.
     */
    protected function getRemoteAssetsIndex(): Collection
    {
        return $this->cache->get()->keyBy(fn ($asset) => $asset->getId());
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
     *
     * Most Mux fields (processing_status, playback, policy) are remote-only and
     * stay empty until enrichLocalRowsWithRemoteData() fills them in for the
     * current page. Two fields keep a local fallback so a row is never blank
     * when the asset isn't (or no longer is) on Mux:
     *   - duration   -> the duration cached on the asset
     *   - created_at -> the asset's own last-modified date
     */
    protected function buildLocalRows(): Collection
    {
        $user = User::current();
        $dashboardUrl = $user?->can('open mux dashboard') ? $this->api->dashboardUrl() : null; // @phpstan-ignore method.notFound

        return MirrorField::assets()
            ->map(function (Asset $asset) use ($user, $dashboardUrl) {
                $mux = MuxAsset::fromAsset($asset);
                $muxId = $mux->id();
                $hasMuxData = $muxId !== null;
                $duration = $mux->duration() ?? $this->assetDuration($asset);
                $playback = $mux->playbackId();
                $canEdit = $user?->can('edit', $asset) ?? false; // @phpstan-ignore method.notFound

                return [
                    'id' => $asset->id(),
                    'title' => $asset->get('title') ?: basename($asset->path()),
                    'path' => $asset->path(),
                    'container' => $asset->containerHandle(),
                    'edit_url' => $canEdit ? $asset->editUrl() : null,
                    'can_edit' => $canEdit,
                    'mux_id' => $muxId,
                    'dashboard_url' => $this->dashboardAssetUrl($muxId, $dashboardUrl),
                    'thumbnail_url' => $playback ? $this->getMuxThumbnailUrl($playback, $asset->orientation(), self::ListingThumbnailSize) : null,
                    'thumbnail_copy_url' => $playback ? $this->getMuxThumbnailUrl($playback, $asset->orientation(), self::CopyThumbnailSize) : null,
                    'player_url' => null,
                    'stream_url' => null,
                    'embed_code' => null,
                    'has_mux_data' => $hasMuxData,
                    'exists_remotely' => null,
                    'mirror_status' => $hasMuxData ? 'uploaded' : 'not_uploaded',
                    'is_proxy' => $mux->isProxy(),
                    // Remote-only display fields (filled in during enrichment).
                    'processing_status' => null,
                    'playback_ids' => [],
                    'playback_id' => null,
                    'playback_policy' => null,
                    // Local fallbacks, overridden by remote when present.
                    'duration' => $duration,
                    'duration_formatted' => $this->formatDuration($duration),
                    'created_at' => $this->assetCreatedAt($asset),
                ];
            });
    }

    /**
     * Build rows for the remote tab.
     */
    protected function buildRemoteRows(Collection $localIndex): Collection
    {
        $user = User::current();
        $dashboardUrl = $user?->can('open mux dashboard') ? $this->api->dashboardUrl() : null; // @phpstan-ignore method.notFound

        $remoteAssets = $this->cache->get();
        $records = $this->reconciler->fromRemoteAssets($remoteAssets)->remotes->keyBy->id();

        return $remoteAssets
            ->map(function ($muxAsset) use ($localIndex, $dashboardUrl, $records) {
                $source = RemoteVideo::make($muxAsset);
                $row = $this->normalizeRow($source);
                $muxId = $row['mux_id'];
                $playbackId = $row['playback_id'];
                $localAssets = $localIndex->get($muxId, collect());
                $localAsset = $localAssets->first();

                $record = $muxId ? $records->get($muxId) : null;
                $resolvedAsset = $record !== null && $record->asset !== null
                    ? $record->asset
                    : ($localAsset['asset'] ?? null);

                return [
                    ...$row,
                    'id' => $muxId,
                    'title' => $source->title() ?: $muxId,
                    'dashboard_url' => $this->dashboardAssetUrl($muxId, $dashboardUrl),
                    'match_status' => $record?->state->value,
                    'match_group' => $record?->state->group(),
                    'local_matches' => $record?->references->count() ?? $localAssets->count(),
                    'local_asset_id' => $resolvedAsset?->id(),
                    'path' => $resolvedAsset?->path(),
                    'container' => $resolvedAsset?->containerHandle(),
                    'resolution_tier' => $source->resolutionTier(),
                    'max_resolution_tier' => $source->maxResolutionTier(),
                    'is_test' => $source->isTest(),
                    'thumbnail_url' => $row['thumbnail_url'],
                    'thumbnail_copy_url' => $row['thumbnail_copy_url'],
                    'aspect_ratio' => $source->aspectRatioLabel(),
                ];
            });
    }

    protected function dashboardAssetUrl(?string $muxId, ?string $dashboardUrl): ?string
    {
        if (! $muxId || ! $dashboardUrl) {
            return null;
        }

        $baseUrl = rtrim($dashboardUrl, '/');
        $muxId = rawurlencode($muxId);

        return "{$baseUrl}/video/assets/{$muxId}";
    }

    /**
     * Shape Mux SDK data into the row fields shared by the remote tab and the
     * local tab's enrichment. The single place remote video data is normalized.
     */
    protected function normalizeRow(RemoteVideo $source): array
    {
        $duration = $source->duration();
        $playbackIds = $source->playbackIds();
        $playbackId = $this->getPrimaryPlaybackId($playbackIds);
        $playback = $this->getPrimaryPlayback($playbackIds);
        $playerUrl = $playback ? $this->service->getPlayerUrl($playback) : null;
        $thumbnailUrl = $playback ? $this->getMuxThumbnailUrl($playback, size: self::ListingThumbnailSize) : null;
        $thumbnailCopyUrl = $playback ? $this->getMuxThumbnailUrl($playback, size: self::CopyThumbnailSize) : null;

        return [
            'mux_id' => $source->id(),
            'processing_status' => $source->status(),
            'duration' => $duration,
            'duration_formatted' => $this->formatDuration($duration),
            'playback_ids' => $playbackIds,
            'playback_id' => $playbackId,
            'playback_policy' => $this->aggregatePlaybackPolicy($playbackIds),
            'thumbnail_url' => $thumbnailUrl,
            'thumbnail_copy_url' => $thumbnailCopyUrl,
            'player_url' => $playerUrl,
            'stream_url' => $playback ? $this->service->getPlaybackUrl($playback) : null,
            'embed_code' => $playback ? $this->service->getEmbedCode($playback) : null,
            'created_at' => $source->createdAt()?->toIso8601String(),
            'is_proxy' => $source->isProxy(),
        ];
    }

    protected function formatDuration(?float $duration): ?string
    {
        return $duration ? Str::durationForHumans($duration) : null;
    }

    /**
     * The asset's own creation date, used as a fallback for the "Created"
     * column when there is no remote Mux asset to read the real date from.
     * Statamic only tracks the file's last-modified time.
     */
    protected function assetCreatedAt(Asset $asset): ?string
    {
        $timestamp = $asset->meta('last_modified');

        return $timestamp ? Carbon::createFromTimestamp($timestamp)->toIso8601String() : null;
    }

    protected function assetDuration(Asset $asset): ?float
    {
        $duration = $asset->duration();

        return $duration !== null ? (float) $duration : null;
    }

    protected function getPrimaryPlaybackId(array $playbackIds): ?string
    {
        return $this->getPrimaryPlayback($playbackIds)?->id();
    }

    protected function getPrimaryPlayback(array $playbackIds): ?MuxPlaybackId
    {
        $playbackIds = collect($playbackIds);
        $playbackId = $playbackIds->firstWhere('policy', 'public') ?? $playbackIds->first();

        return MuxPlaybackId::make($playbackId['id'] ?? '', $playbackId['policy'] ?? '');
    }

    protected function getMuxThumbnailUrl(MuxPlaybackId $playbackId, string $orientation = 'landscape', ?int $size = null): ?string
    {
        return rescue(
            fn () => $this->thumbnails->forPlaybackId($playbackId, $orientation, $size),
            fn (\Throwable $th) => Log::error("Failed to generate Mux thumbnail: {$th->getMessage()}"),
            report: false,
        );
    }

    /**
     * Collapse all playback policies into a single label
     * (e.g. "public" or "public, signed").
     */
    protected function aggregatePlaybackPolicy(array $playbackIds): ?string
    {
        $policies = collect($playbackIds)
            ->pluck('policy')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $policies->isEmpty() ? null : $policies->implode(', ');
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
                'processing_status', 'match_status', 'match_group', 'playback_policy', 'resolution_tier' => is_array($value)
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
        $perPage = max(1, $perPage);
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
