<?php

use Daun\StatamicMux\Commands\MirrorCommand;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
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
        'queue.default' => 'sync',
    ]);
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
});

function bindMirrorPlan(array $locals, array $remotes, ?string $container = null): array
{
    config(['mux.mirror.enabled' => true]);
    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('plan')->with($container)->once()->andReturn(muxPlan($locals, $remotes, $container));
    app()->instance(Reconciler::class, $reconciler);

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    app()->instance(MuxService::class, $service);

    $cache = Mockery::mock(RemoteAssetCache::class);
    app()->instance(RemoteAssetCache::class, $cache);

    return [$service, $cache];
}

it('executes relink then upload then prune from one plan', function () {
    $relinkAsset = $this->uploadTestFileToTestContainer('test.mp4', 'relink.mp4', container: 'videos');
    $uploadAsset = $this->uploadTestFileToTestContainer('test.mp4', 'upload.mp4', container: 'videos');
    $candidate = muxRemoteRecord('selected', ReconciliationState::Unlinked, $relinkAsset);
    $old = muxRemoteRecord('old', ReconciliationState::Superseded, $relinkAsset);
    [$service] = bindMirrorPlan([
        muxLocalRecord($relinkAsset, ReconciliationState::Unlinked, ['selected' => $candidate]),
        muxLocalRecord($uploadAsset, ReconciliationState::Upload),
    ], [$candidate, $old]);

    $service->shouldReceive('relinkMuxAsset')->with($relinkAsset, $candidate->remote->asset(), false)->once()->ordered()->andReturnTrue();
    $service->shouldReceive('createMuxAsset')->with($uploadAsset, false)->once()->ordered()->andReturn('uploaded');
    $service->shouldReceive('deleteMuxAsset')->with($old->remote->asset())->once()->ordered()->andReturnTrue();

    $this->artisan(MirrorCommand::class)
        ->expectsOutputToContain('Plan for 2 local videos and 2 Mux assets')
        ->assertSuccessful();
});

it('never prunes the encoding it just re-linked', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $candidate = muxRemoteRecord('reused', ReconciliationState::Unlinked, $video);
    [$service] = bindMirrorPlan(
        [muxLocalRecord($video, ReconciliationState::Unlinked, ['selected' => $candidate])],
        [$candidate],
    );
    $service->shouldReceive('relinkMuxAsset')->once()->andReturnTrue();
    $service->shouldNotReceive('deleteMuxAsset');

    $this->artisan(MirrorCommand::class)->assertSuccessful();
});

it('continues independent uploads but aborts prune after relink failure', function () {
    $relinkAsset = $this->uploadTestFileToTestContainer('test.mp4', 'relink.mp4', container: 'videos');
    $uploadAsset = $this->uploadTestFileToTestContainer('test.mp4', 'upload.mp4', container: 'videos');
    $candidate = muxRemoteRecord('selected', ReconciliationState::Unlinked, $relinkAsset);
    [$service] = bindMirrorPlan([
        muxLocalRecord($relinkAsset, ReconciliationState::Unlinked, ['selected' => $candidate]),
        muxLocalRecord($uploadAsset, ReconciliationState::Upload),
    ], [
        $candidate,
        muxRemoteRecord('old', ReconciliationState::Superseded, $relinkAsset),
    ]);
    $service->shouldReceive('relinkMuxAsset')->once()->andReturnFalse();
    $service->shouldReceive('createMuxAsset')->with($uploadAsset, false)->once()->andReturn('uploaded');
    $service->shouldNotReceive('deleteMuxAsset');

    $this->artisan(MirrorCommand::class)
        ->expectsOutputToContain('Prune aborted')
        ->assertFailed();
});

it('force skips relinking and retains old candidates during queued upload', function () {
    Queue::fake();
    config(['queue.default' => 'database']);
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $candidate = muxRemoteRecord('candidate', ReconciliationState::Unlinked, $video);
    [$service] = bindMirrorPlan(
        [muxLocalRecord($video, ReconciliationState::Unlinked, ['selected' => $candidate])],
        [$candidate],
    );
    $service->shouldNotReceive('relinkMuxAsset');
    $service->shouldNotReceive('deleteMuxAsset');

    $this->artisan(MirrorCommand::class, ['--force' => true])
        ->expectsOutputToContain('Force mode skips ordinary re-linking')
        ->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, 1);
    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('force repairs placeholder sources instead of uploading their clips', function () {
    Queue::fake();
    config(['queue.default' => 'database']);
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $candidate = muxRemoteRecord('master', ReconciliationState::ProxySource, $video);
    [$service] = bindMirrorPlan(
        [muxLocalRecord($video, ReconciliationState::ProxySource, ['selected' => $candidate])],
        [$candidate],
    );
    $service->shouldReceive('relinkMuxAsset')->with($video, $candidate->remote->asset(), true)->once()->andReturnTrue();

    $this->artisan(MirrorCommand::class, ['--force' => true])->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('dry run reports the plan without executing it', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    [$service] = bindMirrorPlan([muxLocalRecord($video, ReconciliationState::Upload)], []);
    $service->shouldNotReceive('createMuxAsset');
    $service->shouldNotReceive('relinkMuxAsset');
    $service->shouldNotReceive('deleteMuxAsset');

    $this->artisan(MirrorCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('Performing dry run')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('can be called by command name', function () {
    Queue::fake();
    bindMirrorPlan([], []);

    $this->artisan('mux:mirror', ['--dry-run' => true])
        ->expectsOutputToContain('Plan for 0 local videos')
        ->assertSuccessful();
});
