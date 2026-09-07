<?php

use Daun\StatamicMux\Commands\PruneCommand;
use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
    config([
        'mux.credentials.token_id' => 'test-token-id',
        'mux.credentials.token_secret' => 'test-token-secret',
        'mux.mirror.enabled' => false,
        'queue.default' => 'database',
    ]);
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
});

function bindPrunePlan(array $remotes, ?string $container = null): array
{
    config(['mux.mirror.enabled' => true]);
    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('plan')->with($container)->once()->andReturn(muxPlan([], $remotes, $container));
    app()->instance(Reconciler::class, $reconciler);

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    app()->instance(MuxService::class, $service);

    $cache = Mockery::mock(RemoteAssetCache::class);
    app()->instance(RemoteAssetCache::class, $cache);

    return [$service, $cache];
}

it('returns failure for missing configuration and a disabled mirror', function (array $config, string $message) {
    config($config);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    [['mux.credentials.token_id' => null, 'mux.credentials.token_secret' => null], 'Mux is not configured'],
    [['mux.mirror.enabled' => false], 'mirror feature is currently disabled'],
]);

it('warns about unlinked live files and queues their deletion', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    bindPrunePlan([
        muxRemoteRecord('mux-one', ReconciliationState::Unlinked, $video),
        muxRemoteRecord('mux-two', ReconciliationState::Unlinked, $video),
    ]);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('2 orphans are the only Mux encoding')
        ->expectsOutputToContain($video->id())
        ->expectsOutputToContain('2 unlinked encodings were queued for removal')
        ->assertSuccessful();

    Queue::assertPushed(DeleteMuxAssetJob::class, 2);
});

it('keeps unsafe ownership states out of removal counts', function () {
    Queue::fake();
    bindPrunePlan([
        muxRemoteRecord('foreign', ReconciliationState::Foreign),
        muxRemoteRecord('conflict', ReconciliationState::AttributionConflict),
        muxRemoteRecord('unattributable', ReconciliationState::Unattributable),
    ]);

    $this->artisan(PruneCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('0 would be removed')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('keeps in-flight proxy placeholders and prunes expired ones', function () {
    Queue::fake();
    bindPrunePlan([
        muxRemoteRecord('in-flight', ReconciliationState::ProxyInFlight),
        muxRemoteRecord('expired', ReconciliationState::ExpiredProxy),
        muxRemoteRecord('orphaned', ReconciliationState::OrphanedProxy),
    ]);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('2 queued for removal')
        ->assertSuccessful();

    Queue::assertPushed(DeleteMuxAssetJob::class, 2);
});

it('prunes safe groups synchronously', function () {
    config(['queue.default' => 'sync']);
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $record = muxRemoteRecord('old-mux', ReconciliationState::Superseded, $video);
    [$service] = bindPrunePlan([$record]);
    $service->shouldReceive('deleteMuxAsset')->with($record->remote->asset())->once()->andReturnTrue();

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('Removed old-mux')
        ->assertSuccessful();
});

it('reports failure when a synchronous delete fails', function () {
    config(['queue.default' => 'sync']);
    $record = muxRemoteRecord('stuck', ReconciliationState::MissingSource, attributes: ['attributedAssetId' => 'gone::file.mp4']);
    [$service] = bindPrunePlan([$record]);
    $service->shouldReceive('deleteMuxAsset')->once()->andReturnFalse();

    $this->artisan(PruneCommand::class)->assertFailed();
});

it('holds unscopable remote assets when container filtered', function () {
    Queue::fake();
    $container = $this->getAssetContainer('videos')->handle();
    bindPrunePlan([
        muxRemoteRecord('gone', ReconciliationState::MissingSource, attributes: ['attributedAssetId' => 'gone::file.mp4']),
    ], $container);

    $this->artisan(PruneCommand::class, ['--container' => $container])
        ->expectsOutputToContain("could not be scoped to --container={$container}")
        ->expectsOutputToContain('0 queued for removal')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('dry run never queues or deletes assets', function () {
    Queue::fake();
    $record = muxRemoteRecord('gone', ReconciliationState::MissingSource, attributes: ['attributedAssetId' => 'gone::file.mp4']);
    [$service] = bindPrunePlan([$record]);
    $service->shouldNotReceive('deleteMuxAsset');

    $this->artisan(PruneCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('1 would be removed')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('can be called by command name', function () {
    Queue::fake();
    bindPrunePlan([]);

    $this->artisan('mux:prune', ['--dry-run' => true])
        ->expectsOutputToContain('0 would be removed')
        ->assertSuccessful();
});
