<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Support\Attribution;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;

/** Classifies local Statamic assets against remote Mux assets. */
class Reconciler
{
    public const PROXY_GRACE_PERIOD_HOURS = 24;

    /** Aspect ratios within 1% are the same ratio; encoders round differently. */
    protected const ASPECT_TOLERANCE = 0.01;

    public function __construct(
        protected MuxService $service,
        protected RemoteAssetCache $cache,
    ) {}

    public function plan(?string $container = null): ReconciliationPlan
    {
        try {
            $remoteAssets = $this->service->listMuxAssets(limit: 0)->values();
        } catch (\Throwable $exception) {
            throw new \RuntimeException("Unable to fetch Mux assets: {$exception->getMessage()}", previous: $exception);
        }

        $this->cache->seed($remoteAssets);

        return $this->fromRemoteAssets($remoteAssets, $container);
    }

    public function fromRemoteAssets(Collection $remoteAssets, ?string $container = null): ReconciliationPlan
    {
        $videos = $remoteAssets->values()->map(fn ($remote) => RemoteVideo::make($remote));
        $videosById = $videos->keyBy->id();
        $assets = MirrorField::assets();

        $references = $assets
            ->map(fn (Asset $asset) => ['asset' => $asset, 'mux_id' => MuxAsset::fromAsset($asset)->id()])
            ->filter(fn (array $item) => filled($item['mux_id']))
            ->groupBy('mux_id')
            ->map(fn (Collection $items) => $items->pluck('asset')->values());

        $attributed = $videos->map(fn (RemoteVideo $video) => $this->classifyRemote($video, $videosById, $references));
        $byAssetId = $attributed->filter(fn (array $it) => filled($it['asset_id']))->groupBy('asset_id');

        $refinements = $this->validateCandidates($assets, $byAssetId);

        $remoteRecords = $attributed->map(function (array $it) use ($refinements) {
            $refined = $refinements->get($it['video']->id());

            return new RemoteAssetRecord(
                remote: $it['video'],
                state: $refined['state'] ?? $it['state'],
                asset: $it['asset'],
                attributedAssetId: $it['asset_id'],
                reason: $refined['reason'] ?? $it['reason'],
                container: $it['container'],
                references: $it['references'],
            );
        })->values();

        $recordsById = $remoteRecords->keyBy->id();

        $localRecords = $assets->map(function (Asset $asset) use ($videosById, $byAssetId, $recordsById) {
            $attributedRecords = collect($byAssetId->get($asset->id(), []))
                ->map(fn (array $it) => $recordsById->get($it['video']->id()))
                ->filter()
                ->values();

            return $this->classifyLocal($asset, $videosById, $attributedRecords);
        })->values();

        return new ReconciliationPlan($localRecords, $remoteRecords, $container);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  Collection<int|string, mixed>  $byAssetId
     * @return Collection<string, array{state: ReconciliationState, reason: ?string}>
     */
    protected function validateCandidates(Collection $assets, Collection $byAssetId): Collection
    {
        $refinements = collect();

        foreach ($assets as $asset) {
            if (MuxAsset::fromAsset($asset)->id()) {
                continue;
            }

            foreach ($byAssetId->get($asset->id(), []) as $attributed) {
                if ($attributed['state'] !== ReconciliationState::Unlinked) {
                    continue;
                }

                $validation = $this->validateMedia($asset, $attributed['video']);
                $state = match (true) {
                    ! $validation['matches'] => ReconciliationState::MediaMismatch,
                    $validation['proxy_source'] => ReconciliationState::ProxySource,
                    default => ReconciliationState::Unlinked,
                };

                if ($state !== ReconciliationState::Unlinked) {
                    $refinements->put($attributed['video']->id(), ['state' => $state, 'reason' => $validation['reason']]);
                }
            }
        }

        return $refinements;
    }

    /**
     * @return array{video: RemoteVideo, state: ReconciliationState, asset: ?Asset, asset_id: ?string, reason: ?string, container: ?string, references: Collection}
     */
    protected function classifyRemote(RemoteVideo $video, Collection $videosById, Collection $allReferences): array
    {
        $references = $allReferences->get($video->id(), collect());
        $attribution = $video->isProxy()
            ? $this->resolveProxyAttribution($video, $videosById)
            : $this->resolveAttribution($video->passthrough(), $video->creatorId(), $video->externalId());

        $state = $attribution['state'];
        $asset = $attribution['asset'];
        $unresolved = in_array($state, [
            ReconciliationState::Foreign,
            ReconciliationState::Unattributable,
            ReconciliationState::AttributionConflict,
        ], true);

        $result = fn (ReconciliationState $state, ?Asset $asset = null, ?string $reason = null) => [
            'video' => $video,
            'state' => $state,
            'asset' => $asset ?? $attribution['asset'],
            'asset_id' => $attribution['asset_id'],
            'reason' => $reason ?? $attribution['reason'],
            'container' => ($asset ?? $attribution['asset'])?->containerHandle(),
            'references' => $references,
        ];

        // A local file pointing here outranks whatever the passthrough claims.
        if ($references->count() > 1
            || ($references->count() === 1 && $attribution['asset_id'] && $references->first()->id() !== $attribution['asset_id'])) {
            return $result(
                ReconciliationState::SharedReference,
                $references->first(),
                'The Mux asset is referenced by multiple or differently attributed local assets.',
            );
        }

        if ($references->count() === 1) {
            return $state === ReconciliationState::AttributionConflict
                ? $result($state, $references->first())
                : $result(ReconciliationState::Linked, $references->first());
        }

        if ($video->isProxy()) {
            if ($unresolved) {
                return $result($state);
            }

            return $result(match (true) {
                $this->proxyIsInFlight($video) => ReconciliationState::ProxyInFlight,
                $videosById->has($video->proxyParentId()) => ReconciliationState::ExpiredProxy,
                default => ReconciliationState::OrphanedProxy,
            });
        }

        if ($unresolved || $state === ReconciliationState::MissingSource) {
            return $result($state);
        }

        if (! $asset) {
            return $result(ReconciliationState::MissingSource);
        }

        if (! MirrorField::existsInBlueprint($asset)) {
            return $result(ReconciliationState::UnmanagedSource);
        }

        if (MuxAsset::fromAsset($asset)->id()) {
            return $result(ReconciliationState::Superseded);
        }

        return $result(match ($video->status()) {
            'ready' => ReconciliationState::Unlinked,
            'preparing' => ReconciliationState::Preparing,
            'errored' => ReconciliationState::Errored,
            default => ReconciliationState::UnknownStatus,
        });
    }

    /**
     * @param  Collection<int, RemoteAssetRecord>  $attributed
     */
    protected function classifyLocal(Asset $asset, Collection $videosById, Collection $attributed): LocalAssetRecord
    {
        $muxId = MuxAsset::fromAsset($asset)->id();

        if ($muxId) {
            return $this->classifyLinkedLocal($asset, $muxId, $videosById, $attributed);
        }

        $candidates = $attributed
            ->filter(fn (RemoteAssetRecord $record) => in_array($record->state, [
                ReconciliationState::Unlinked,
                ReconciliationState::ProxySource,
                ReconciliationState::MediaMismatch,
                ReconciliationState::Preparing,
                ReconciliationState::Errored,
                ReconciliationState::UnknownStatus,
            ], true))
            ->values();

        $safe = $candidates->filter->isSafeCandidate()->values();

        if ($safe->isNotEmpty()) {
            $selected = $this->newest($safe);
            $proxySource = $selected->isProxySource();

            return new LocalAssetRecord(
                asset: $asset,
                state: $proxySource ? ReconciliationState::ProxySource : ReconciliationState::Unlinked,
                selected: $selected,
                candidates: $candidates,
                proxySource: $proxySource,
            );
        }

        $held = $candidates->first(fn (RemoteAssetRecord $c) => $c->state === ReconciliationState::Preparing)
            ?? $candidates->first(fn (RemoteAssetRecord $c) => $c->state === ReconciliationState::UnknownStatus);

        if ($held) {
            return new LocalAssetRecord(
                asset: $asset,
                state: $held->state,
                candidates: $candidates,
                reason: $held->state === ReconciliationState::Preparing
                    ? 'An attributable Mux asset is still preparing.'
                    : 'An attributable Mux asset has an unknown processing status.',
            );
        }

        $mismatched = $candidates->where('state', ReconciliationState::MediaMismatch)->values();

        if ($mismatched->isNotEmpty()) {
            $selected = $this->newest($mismatched);

            return new LocalAssetRecord(
                asset: $asset,
                state: ReconciliationState::MediaMismatch,
                selected: $selected,
                candidates: $candidates,
                reason: $selected->reason,
            );
        }

        return new LocalAssetRecord(
            asset: $asset,
            state: ReconciliationState::Upload,
            candidates: $candidates,
            reason: $candidates->isNotEmpty()
                ? 'Only errored Mux encodings exist.'
                : 'No attributable Mux encoding exists.',
        );
    }

    /**
     * @param  Collection<int, RemoteAssetRecord>  $attributed
     */
    protected function classifyLinkedLocal(Asset $asset, string $muxId, Collection $videosById, Collection $attributed): LocalAssetRecord
    {
        $video = $videosById->get($muxId);

        if (! $video) {
            return new LocalAssetRecord(
                asset: $asset,
                state: ReconciliationState::Reupload,
                muxId: $muxId,
                reason: 'The locally stored Mux ID no longer exists remotely.',
            );
        }

        $readyPredecessor = $attributed->first(
            fn (RemoteAssetRecord $record) => $record->id() !== $muxId && $record->remote->isReady()
        );

        if (! $video->isReady() && $readyPredecessor) {
            return new LocalAssetRecord(
                asset: $asset,
                state: ReconciliationState::NonReadyLinked,
                muxId: $muxId,
                reason: 'An older ready encoding still exists.',
            );
        }

        return new LocalAssetRecord($asset, ReconciliationState::Linked, $muxId);
    }

    /** @param  Collection<int, RemoteAssetRecord>  $candidates */
    protected function newest(Collection $candidates): RemoteAssetRecord
    {
        return $candidates
            ->sortByDesc(function (RemoteAssetRecord $candidate) {
                $createdAt = $candidate->remote->createdAt();

                return $createdAt !== null ? $createdAt->timestamp : 0;
            })
            ->first();
    }

    protected function resolveAttribution(?string $passthrough, ?string $creatorId, ?string $externalId): array
    {
        $passthroughId = Attribution::assetId($passthrough);
        $malformed = is_string($passthrough) && Str::startsWith($passthrough, Attribution::PREFIX) && ! $passthroughId;

        if ($passthroughId) {
            $disagrees = ($creatorId !== null && $creatorId !== Attribution::CREATOR_ID)
                || ($externalId !== null && $externalId !== $passthroughId);

            return $disagrees
                ? $this->attributionResult(ReconciliationState::AttributionConflict, $passthroughId, 'Passthrough and metadata ownership or attribution disagree.')
                : $this->attributionResult(ReconciliationState::Unlinked, $passthroughId);
        }

        if ($creatorId === Attribution::CREATOR_ID && $externalId && Assets::find($externalId)) {
            return $this->attributionResult(ReconciliationState::Unlinked, $externalId);
        }

        if ($malformed || $creatorId === Attribution::CREATOR_ID) {
            return $this->attributionResult(ReconciliationState::Unattributable, reason: 'Addon ownership is present but no valid local asset attribution can be resolved.');
        }

        return $this->attributionResult(ReconciliationState::Foreign, reason: 'The remote asset was not created by this addon.');
    }

    protected function resolveProxyAttribution(RemoteVideo $video, Collection $videosById): array
    {
        $parent = $videosById->get($video->proxyParentId());

        if (! $parent) {
            return $this->attributionResult(ReconciliationState::OrphanedProxy, reason: 'The proxy parent no longer exists.');
        }

        return $this->resolveAttribution($parent->passthrough(), $parent->creatorId(), $parent->externalId());
    }

    protected function attributionResult(ReconciliationState $state, ?string $assetId = null, ?string $reason = null): array
    {
        return [
            'state' => $state,
            'asset_id' => $assetId,
            'asset' => $assetId ? Assets::find($assetId) : null,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{matches: bool, proxy_source: bool, reason: ?string}
     */
    protected function validateMedia(Asset $asset, RemoteVideo $video): array
    {
        $localDuration = $asset->duration() !== null ? (float) $asset->duration() : null;
        $remoteDuration = $video->duration();
        $localAspect = $asset->width() && $asset->height() ? $asset->width() / $asset->height() : null;
        $remoteAspect = $video->aspectRatio();

        $aspectMatches = $localAspect === null || $remoteAspect === null
            || abs($localAspect - $remoteAspect) / max($localAspect, $remoteAspect) <= self::ASPECT_TOLERANCE;

        // A local file that is the short placeholder clip of the master is a match, not a mismatch.
        $proxyLength = (float) config('mux.storage.placeholder_length', 10);
        $isProxySource = $localDuration !== null && $remoteDuration !== null
            && abs($localDuration - $proxyLength) <= $this->durationTolerance($proxyLength)
            && $remoteDuration > $localDuration + 2
            && $aspectMatches;

        if ($isProxySource) {
            return ['matches' => true, 'proxy_source' => true, 'reason' => null];
        }

        if (! $aspectMatches) {
            return ['matches' => false, 'proxy_source' => false, 'reason' => 'Display aspect ratio differs by more than 1%.'];
        }

        if ($localDuration !== null && $remoteDuration !== null
            && abs($localDuration - $remoteDuration) > $this->durationTolerance(max($localDuration, $remoteDuration))) {
            return ['matches' => false, 'proxy_source' => false, 'reason' => 'Duration differs beyond the encoding tolerance.'];
        }

        return ['matches' => true, 'proxy_source' => false, 'reason' => null];
    }

    protected function durationTolerance(float $duration): float
    {
        return min(2.0, max(1.0, $duration / 1800));
    }

    /** Missing creation timestamp counts as in flight, so incomplete uploads are never pruned. */
    protected function proxyIsInFlight(RemoteVideo $video): bool
    {
        $createdAt = $video->createdAt();

        return $createdAt === null
            || $createdAt->greaterThan(now()->subHours(self::PROXY_GRACE_PERIOD_HOURS));
    }
}
