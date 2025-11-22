<?php

use Daun\StatamicMux\Commands\PruneCommand;
use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();

    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);
    config(['mux.mirror.enabled' => true]);
    config(['queue.default' => 'sync']);
});

// Configuration validation tests

it('shows error when mux is not configured', function () {
    config(['mux.credentials.token_id' => null]);
    config(['mux.credentials.token_secret' => null]);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('Mux is not configured. Please add valid Mux credentials in your .env file.')
        ->assertSuccessful();
});

it('shows error when mirror feature is disabled', function () {
    config(['mux.mirror.enabled' => false]);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('The mirror feature is currently disabled.')
        ->assertSuccessful();
});

// No videos scenarios

it('shows message when no videos found on Mux', function () {
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([]));
    app()->instance(MuxService::class, $service);

    $this->artisan(PruneCommand::class)
        ->expectsOutput('No videos found on Mux')
        ->assertSuccessful();
});

// Prune scenarios

it('removes orphaned videos from Mux', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $localVideo->set('mux', ['id' => 'local-mux-id']);
    $localVideo->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'local-mux-id'],
        (object) ['id' => 'orphan-mux-id'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('Queued removal of orphan-mux-id')
        ->expectsOutputToContain('Keeping local-mux-id')
        ->expectsOutputToContain('✓ Queued 1 videos for removal, kept 1 videos')
        ->assertSuccessful();

    Queue::assertPushed(DeleteMuxAssetJob::class, function ($job) {
        $class = new \ReflectionClass($job);
        $asset = $class->getProperty('asset')->getValue($job);

        return $asset === 'orphan-mux-id';
    });
});

it('removes orphaned videos in sync mode', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $localVideo->set('mux', ['id' => 'local-mux-id']);
    $localVideo->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'local-mux-id'],
        (object) ['id' => 'orphan-mux-id'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    $service->shouldReceive('deleteMuxAsset')->with('orphan-mux-id')->once();
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('Removed orphan-mux-id')
        ->expectsOutputToContain('Keeping local-mux-id')
        ->expectsOutputToContain('✓ Removed 1 videos, kept 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('keeps all videos when no orphans found', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo1 = $this->uploadTestFileToTestContainer('test.mp4', 'video1.mp4', container: 'videos');
    $localVideo1->set('mux', ['id' => 'mux-id-1']);
    $localVideo1->save();

    $localVideo2 = $this->uploadTestFileToTestContainer('test.mp4', 'video2.mp4', container: 'videos');
    $localVideo2->set('mux', ['id' => 'mux-id-2']);
    $localVideo2->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'mux-id-1'],
        (object) ['id' => 'mux-id-2'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    $service->shouldNotReceive('deleteMuxAsset');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('Keeping mux-id-1')
        ->expectsOutputToContain('Keeping mux-id-2')
        ->expectsOutputToContain('✓ Removed 0 videos, kept 2 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('removes multiple orphaned videos', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $localVideo->set('mux', ['id' => 'local-mux-id']);
    $localVideo->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'local-mux-id'],
        (object) ['id' => 'orphan-1'],
        (object) ['id' => 'orphan-2'],
        (object) ['id' => 'orphan-3'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    $service->shouldReceive('deleteMuxAsset')->times(3);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(PruneCommand::class)
        ->expectsOutputToContain('Removed orphan-1')
        ->expectsOutputToContain('Removed orphan-2')
        ->expectsOutputToContain('Removed orphan-3')
        ->expectsOutputToContain('Keeping local-mux-id')
        ->expectsOutputToContain('✓ Removed 3 videos, kept 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

// Dry-run tests

it('performs dry-run without removing videos', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $localVideo->set('mux', ['id' => 'local-mux-id']);
    $localVideo->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'local-mux-id'],
        (object) ['id' => 'orphan-mux-id'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    $service->shouldNotReceive('deleteMuxAsset');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(PruneCommand::class, ['--dry-run' => true])
        ->expectsOutput('Performing dry run: no videos will be deleted')
        ->expectsOutputToContain('Would remove orphan-mux-id')
        ->expectsOutputToContain('Would keep local-mux-id')
        ->expectsOutputToContain('✓ Would have removed 1 videos, kept 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('shows dry-run output for multiple orphans', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'orphan-1'],
        (object) ['id' => 'orphan-2'],
    ]));
    $service->shouldNotReceive('deleteMuxAsset');
    app()->instance(MuxService::class, $service);

    $this->artisan(PruneCommand::class, ['--dry-run' => true])
        ->expectsOutput('Performing dry run: no videos will be deleted')
        ->expectsOutputToContain('Would remove orphan-1')
        ->expectsOutputToContain('Would remove orphan-2')
        ->expectsOutputToContain('✓ Would have removed 2 videos, kept 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

it('shows dry-run output when no orphans found', function () {
    Queue::fake();

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $localVideo = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $localVideo->set('mux', ['id' => 'local-mux-id']);
    $localVideo->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([
        (object) ['id' => 'local-mux-id'],
    ]));
    $service->shouldReceive('getMuxId')->andReturnUsing(function ($asset) {
        return $asset->get('mux')['id'] ?? null;
    });
    app()->instance(MuxService::class, $service);

    $this->artisan(PruneCommand::class, ['--dry-run' => true])
        ->expectsOutput('Performing dry run: no videos will be deleted')
        ->expectsOutputToContain('Would keep local-mux-id')
        ->expectsOutputToContain('✓ Would have removed 0 videos, kept 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(DeleteMuxAssetJob::class);
});

// Command name test

it('can be called by command name', function () {
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('listMuxAssets')->andReturn(collect([]));
    app()->instance(MuxService::class, $service);

    $this->artisan('mux:prune')
        ->assertSuccessful();
});
