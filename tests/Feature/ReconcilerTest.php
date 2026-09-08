<?php

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use MuxPhp\Models\Asset as RemoteAsset;
use MuxPhp\Models\AssetMetadata;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
    config([
        'mux.credentials.token_id' => 'test-token-id',
        'mux.credentials.token_secret' => 'test-token-secret',
        'mux.mirror.enabled' => false,
        'mux.storage.placeholder_length' => 10,
    ]);

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
});

function reconciliationRemote(string $id, array $data = []): RemoteAsset
{
    $meta = $data['meta'] ?? null;
    if (is_array($meta)) {
        $meta = new AssetMetadata($meta);
    }

    return new RemoteAsset([
        'id' => $id,
        'status' => 'ready',
        'created_at' => (string) now()->timestamp,
        'duration' => null,
        'aspect_ratio' => null,
        'playback_ids' => [],
        ...$data,
        'meta' => $meta,
    ]);
}

function buildReconciliationPlan(array $remotes, ?string $container = null)
{
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('listMuxAssets')->with(0)->once()->andReturn(collect($remotes));

    return (new Reconciler($service, app(RemoteAssetCache::class)))->plan($container);
}

it('classifies linked, upload, superseded, missing source, and foreign assets', function () {
    $linked = $this->uploadTestFileToTestContainer('test.mp4', 'linked.mp4', container: 'videos');
    $linked->set('mux', ['id' => 'linked-mux'])->saveQuietly();
    $fresh = $this->uploadTestFileToTestContainer('test.mp4', 'fresh.mp4', container: 'videos');
    $superseded = $this->uploadTestFileToTestContainer('test.mp4', 'superseded.mp4', container: 'videos');
    $superseded->set('mux', ['id' => 'new-mux'])->saveQuietly();

    $plan = buildReconciliationPlan([
        reconciliationRemote('linked-mux', ['passthrough' => "statamic::{$linked->id()}"]),
        reconciliationRemote('new-mux', ['passthrough' => "statamic::{$superseded->id()}"]),
        reconciliationRemote('old-mux', ['passthrough' => "statamic::{$superseded->id()}"]),
        reconciliationRemote('gone-mux', ['passthrough' => 'statamic::assets::gone.mp4']),
        reconciliationRemote('foreign-mux'),
    ]);

    expect($plan->local(ReconciliationState::Linked))->toHaveCount(2)
        ->and($plan->local(ReconciliationState::Upload)->first()->asset->id())->toBe($fresh->id())
        ->and($plan->remote(ReconciliationState::Superseded)->pluck('remote')->map->id()->all())->toBe(['old-mux'])
        ->and($plan->remote(ReconciliationState::MissingSource))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::Foreign))->toHaveCount(1);
});

it('uses metadata fallback but rejects conflicting or insufficient attribution', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');

    $plan = buildReconciliationPlan([
        reconciliationRemote('fallback', ['meta' => ['creator_id' => 'statamic-mux', 'external_id' => $video->id()]]),
        reconciliationRemote('conflict', [
            'passthrough' => "statamic::{$video->id()}",
            'meta' => ['creator_id' => 'somebody-else', 'external_id' => $video->id()],
        ]),
        reconciliationRemote('external-only', ['meta' => ['external_id' => $video->id()]]),
        reconciliationRemote('malformed', ['passthrough' => 'statamic::']),
    ]);

    expect($plan->remote(ReconciliationState::Unlinked))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::AttributionConflict))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::Foreign))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::Unattributable))->toHaveCount(1);
});

it('selects the newest ready candidate and holds preparing and unknown candidates', function () {
    $ready = $this->uploadTestFileToTestContainer('test.mp4', 'ready.mp4', container: 'videos');
    $preparing = $this->uploadTestFileToTestContainer('test.mp4', 'preparing.mp4', container: 'videos');
    $unknown = $this->uploadTestFileToTestContainer('test.mp4', 'unknown.mp4', container: 'videos');

    $plan = buildReconciliationPlan([
        reconciliationRemote('ready-old', ['passthrough' => "statamic::{$ready->id()}", 'created_at' => '100']),
        reconciliationRemote('ready-new', ['passthrough' => "statamic::{$ready->id()}", 'created_at' => '200']),
        reconciliationRemote('preparing', ['passthrough' => "statamic::{$preparing->id()}", 'status' => 'preparing']),
        reconciliationRemote('unknown', ['passthrough' => "statamic::{$unknown->id()}", 'status' => 'unexpected']),
    ]);

    $record = $plan->local(ReconciliationState::Unlinked)->first();
    expect($record->selected->id())->toBe('ready-new')
        ->and($record->alternatives()->map->id()->all())->toBe(['ready-old'])
        ->and($plan->local(ReconciliationState::Preparing))->toHaveCount(1)
        ->and($plan->local(ReconciliationState::UnknownStatus))->toHaveCount(1);
});

it('selects the same record instance the remote listing reports', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $mismatch = $this->uploadTestFileToTestContainer('test.mp4', 'mismatch.mp4', container: 'videos');

    $plan = buildReconciliationPlan([
        reconciliationRemote('safe', ['passthrough' => "statamic::{$video->id()}"]),
        reconciliationRemote('bad', ['passthrough' => "statamic::{$mismatch->id()}", 'duration' => 40, 'aspect_ratio' => '4:3']),
    ]);

    $selected = $plan->local(ReconciliationState::Unlinked)->first()->selected;
    $held = $plan->local(ReconciliationState::MediaMismatch)->first()->selected;

    expect($selected)->toBe($plan->remotes->firstWhere('state', ReconciliationState::Unlinked))
        ->and($selected->isSafeCandidate())->toBeTrue()
        ->and($held->state)->toBe(ReconciliationState::MediaMismatch)
        ->and($held)->toBe($plan->remotes->firstWhere('state', ReconciliationState::MediaMismatch))
        ->and($held->isSafeCandidate())->toBeFalse();
});

it('returns fresh classifications for changed snapshots of equal size', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $reconciler = new Reconciler(Mockery::mock(MuxService::class), app(RemoteAssetCache::class));

    $first = $reconciler->fromRemoteAssets(collect([
        reconciliationRemote('one', ['passthrough' => "statamic::{$video->id()}"]),
    ]));

    $second = $reconciler->fromRemoteAssets(collect([
        reconciliationRemote('two', ['passthrough' => "statamic::{$video->id()}"]),
    ]));

    expect($first->remotes->first()->id())->toBe('one')
        ->and($second->remotes->first()->id())->toBe('two')
        ->and($second->local(ReconciliationState::Unlinked)->first()->selected->id())->toBe('two');

    $video->set('mux', ['id' => 'two'])->saveQuietly();
    Stache::clear();

    $third = $reconciler->fromRemoteAssets(collect([
        reconciliationRemote('two', ['passthrough' => "statamic::{$video->id()}"]),
    ]));

    expect($third->local(ReconciliationState::Linked))->toHaveCount(1);
});

it('detects media mismatches while allowing downscales and placeholder sources', function () {
    $mismatch = $this->uploadTestFileToTestContainer('test.mp4', 'mismatch.mp4', container: 'videos');
    $proxy = $this->uploadTestFileToTestContainer('test.mp4', 'proxy.mp4', container: 'videos');
    $downscale = $this->uploadTestFileToTestContainer('test.mp4', 'downscale.mp4', container: 'videos');
    config(['mux.storage.placeholder_length' => 28.433333]);

    $plan = buildReconciliationPlan([
        reconciliationRemote('mismatch', ['passthrough' => "statamic::{$mismatch->id()}", 'duration' => 40, 'aspect_ratio' => '4:3']),
        reconciliationRemote('proxy', ['passthrough' => "statamic::{$proxy->id()}", 'duration' => 60, 'aspect_ratio' => '16:9']),
        reconciliationRemote('downscale', ['passthrough' => "statamic::{$downscale->id()}", 'duration' => 28.433333, 'aspect_ratio' => '16:9', 'resolution_tier' => '1080p']),
    ]);

    expect($plan->local(ReconciliationState::MediaMismatch))->toHaveCount(1)
        ->and($plan->local(ReconciliationState::ProxySource))->toHaveCount(1)
        ->and($plan->local(ReconciliationState::ProxySource)->first()->proxySource)->toBeTrue()
        ->and($plan->local(ReconciliationState::Unlinked))->toHaveCount(1);
});

it('resolves proxy parents, expires old proxies, and keeps unknown-age proxies', function (string $prefix) {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $parent = reconciliationRemote('parent', ['passthrough' => "statamic::{$video->id()}"]);

    $plan = buildReconciliationPlan([
        $parent,
        reconciliationRemote('recent-proxy', ['passthrough' => "{$prefix}parent", 'created_at' => null]),
        reconciliationRemote('expired-proxy', ['passthrough' => "{$prefix}parent", 'created_at' => (string) now()->subDays(2)->timestamp]),
        reconciliationRemote('orphaned-proxy', ['passthrough' => "{$prefix}missing", 'created_at' => (string) now()->subDays(2)->timestamp]),
    ]);

    expect($plan->remote(ReconciliationState::ProxyInFlight))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::ExpiredProxy))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::ExpiredProxy)->first()->attributedAssetId)->toBe($video->id())
        ->and($plan->remote(ReconciliationState::OrphanedProxy))->toHaveCount(1);
})->with(['statamic-proxy::', 'proxy::']);

it('keeps shared references and never offers them for relinking', function () {
    $first = $this->uploadTestFileToTestContainer('test.mp4', 'first.mp4', container: 'videos');
    $second = $this->uploadTestFileToTestContainer('test.mp4', 'second.mp4', container: 'videos');
    $first->set('mux', ['id' => 'shared'])->saveQuietly();
    $second->set('mux', ['id' => 'shared'])->saveQuietly();

    $plan = buildReconciliationPlan([
        reconciliationRemote('shared', ['passthrough' => "statamic::{$first->id()}"]),
    ]);

    expect($plan->remote(ReconciliationState::SharedReference))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::SharedReference)->first()->references)->toHaveCount(2)
        ->and($plan->prunable())->toBeEmpty();
});

it('classifies an existing source without a mux field as unmanaged', function () {
    $plain = $this->uploadTestFileToTestContainer('test.mp4', 'plain.mp4');

    $plan = buildReconciliationPlan([
        reconciliationRemote('remote', ['passthrough' => "statamic::{$plain->id()}"]),
    ]);

    expect($plan->remote(ReconciliationState::UnmanagedSource))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::MissingSource))->toBeEmpty();
});

it('treats a stale passthrough attribution as linked', function () {
    $owner = $this->uploadTestFileToTestContainer('test.mp4', 'owner.mp4', container: 'videos');
    $reference = $this->uploadTestFileToTestContainer('test.mp4', 'reference.mp4', container: 'videos');
    $reference->set('mux', ['id' => 'mux-id'])->saveQuietly();

    $plan = buildReconciliationPlan([
        reconciliationRemote('mux-id', ['passthrough' => "statamic::{$owner->id()}"]),
    ]);

    expect($plan->remote(ReconciliationState::Linked))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::Linked)->first()->asset?->id())->toBe($reference->id())
        ->and($plan->remote(ReconciliationState::SharedReference))->toBeEmpty()
        ->and($plan->remote(ReconciliationState::Superseded))->toBeEmpty()
        ->and($plan->prunable())->toBeEmpty();
});

it('reports a non-ready current encoding without relinking backwards', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $video->set('mux', ['id' => 'current'])->saveQuietly();

    $plan = buildReconciliationPlan([
        reconciliationRemote('current', ['passthrough' => "statamic::{$video->id()}", 'status' => 'errored']),
        reconciliationRemote('older', ['passthrough' => "statamic::{$video->id()}", 'status' => 'ready', 'created_at' => '100']),
    ]);

    expect($plan->local(ReconciliationState::NonReadyLinked))->toHaveCount(1)
        ->and($plan->local(ReconciliationState::Unlinked))->toBeEmpty()
        ->and($plan->remote(ReconciliationState::Superseded)->pluck('remote')->map->id()->all())->toBe(['older']);
});

it('holds preparing candidates ahead of errored candidates', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');

    $plan = buildReconciliationPlan([
        reconciliationRemote('preparing', ['passthrough' => "statamic::{$video->id()}", 'status' => 'preparing']),
        reconciliationRemote('errored', ['passthrough' => "statamic::{$video->id()}", 'status' => 'errored']),
    ]);

    expect($plan->local(ReconciliationState::Preparing))->toHaveCount(1)
        ->and($plan->remote(ReconciliationState::Errored)->first()->isPrunable())->toBeTrue();
});

it('applies container scope only after classifying every container', function () {
    $this->createAssetContainer('media');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $other = $this->uploadTestFileToTestContainer('test.mp4', 'other.mp4', container: 'media');
    $video->set('mux', ['id' => 'video-mux'])->saveQuietly();
    $other->set('mux', ['id' => 'other-mux'])->saveQuietly();

    $plan = buildReconciliationPlan([
        reconciliationRemote('video-mux', ['passthrough' => "statamic::{$video->id()}"]),
        reconciliationRemote('other-mux', ['passthrough' => "statamic::{$other->id()}"]),
    ], $video->containerHandle());

    // Both containers are classified; only the scoped accessors narrow the result.
    expect($plan->remotes->where('state', ReconciliationState::Linked))->toHaveCount(2)
        ->and($plan->remote(ReconciliationState::Linked))->toHaveCount(1)
        ->and($plan->scopedRemotes())->toHaveCount(1)
        ->and($plan->scopedRemotes()->first()->id())->toBe('video-mux');
});

it('wraps remote fetch failures without returning a partial plan', function () {
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('listMuxAssets')->andThrow(new RuntimeException('timeout'));

    expect(fn () => (new Reconciler($service, app(RemoteAssetCache::class)))->plan())
        ->toThrow(RuntimeException::class, 'Unable to fetch Mux assets: timeout');
});
