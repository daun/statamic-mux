<?php

use Daun\StatamicMux\Commands\UploadCommand;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
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

    $this->artisan(UploadCommand::class)
        ->expectsOutput('Mux is not configured. Please add valid Mux credentials in your .env file.')
        ->assertSuccessful();
});

it('shows error when mirror feature is disabled', function () {
    config(['mux.mirror.enabled' => false]);

    $this->artisan(UploadCommand::class)
        ->expectsOutput('The mirror feature is currently disabled.')
        ->assertSuccessful();
});

it('shows error when no containers found to mirror', function () {
    $this->createAssetContainer('videos');
    $this->createAssetContainer('images');
    // No mirror fields added

    $this->artisan(UploadCommand::class)
        ->expectsOutput('No containers found to mirror.')
        ->expectsOutput('Please add a `mux_mirror` field to at least one of your asset blueprints.')
        ->assertSuccessful();
});

it('shows error when specified container not found', function () {
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $this->artisan(UploadCommand::class, ['--container' => 'nonexistent'])
        ->expectsOutput("Asset container 'nonexistent' not found")
        ->assertSuccessful();
});

// No videos scenarios

it('shows message when no videos found in containers', function () {
    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $this->uploadTestFileToTestContainer('test.jpg', container: 'videos'); // Upload non-video

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    app()->instance(MuxService::class, $service);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain('No videos found')
        ->assertSuccessful();
});

// Upload scenarios

it('uploads new videos', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Queued upload of {$video->id()}")
        ->expectsOutputToContain('✓ Queued 1 videos for background upload, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) use ($video) {
        $class = new \ReflectionClass($job);
        $asset = $class->getProperty('asset')->getValue($job);
        $force = $class->getProperty('force')->getValue($job);

        return $asset->id() === $video->id() && $force === false;
    });
});

it('uploads new videos in sync mode', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    $service->shouldReceive('createMuxAsset')->once()->andReturn('mux-asset-id');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Uploaded {$video->id()}")
        ->expectsOutputToContain('✓ Uploaded 1 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('skips existing videos without force flag', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Skipped {$video->id()}")
        ->expectsOutputToContain('✓ Uploaded 0 videos, skipped 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('reuploads existing videos with force flag', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(UploadCommand::class, ['--force' => true])
        ->expectsOutputToContain("Queued reupload of {$video->id()}")
        ->expectsOutputToContain('✓ Queued 1 videos for background upload, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) use ($video) {
        $class = new \ReflectionClass($job);
        $asset = $class->getProperty('asset')->getValue($job);
        $force = $class->getProperty('force')->getValue($job);

        return $asset->id() === $video->id() && $force === true;
    });
});

it('reuploads existing videos with force flag in sync mode', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $service->shouldReceive('createMuxAsset')->once()->andReturn('mux-asset-id');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class, ['--force' => true])
        ->expectsOutputToContain("Reuploaded {$video->id()}")
        ->expectsOutputToContain('✓ Uploaded 1 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('does not reupload proxy versions even with force flag', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $video->set('mux', ['id' => 'mux-asset-id', 'is_proxy' => true]);
    $video->save();

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class, ['--force' => true])
        ->expectsOutputToContain("Skipped {$video->id()}")
        ->expectsOutputToContain('✓ Uploaded 0 videos, skipped 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

// Container filtering tests

it('limits upload to specific container', function () {
    Queue::fake();

    $videos = $this->createAssetContainer('videos');
    $media = $this->createAssetContainer('media');

    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');

    $video1 = $this->uploadTestFileToTestContainer('test.mp4', 'video1.mp4', container: 'videos');
    $video2 = $this->uploadTestFileToTestContainer('test.mp4', 'video2.mp4', container: 'media');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    $service->shouldReceive('createMuxAsset')->once()->andReturn('mux-asset-id');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class, ['--container' => 'test_container_videos'])
        ->expectsOutputToContain("Uploaded {$video1->id()}")
        ->doesntExpectOutputToContain($video2->id())
        ->expectsOutputToContain('✓ Uploaded 1 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('processes videos from multiple containers when no container specified', function () {
    Queue::fake();

    $videos = $this->createAssetContainer('videos');
    $media = $this->createAssetContainer('media');

    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');

    $video1 = $this->uploadTestFileToTestContainer('test.mp4', 'video1.mp4', container: 'videos');
    $video2 = $this->uploadTestFileToTestContainer('test.mp4', 'video2.mp4', container: 'media');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    $service->shouldReceive('createMuxAsset')->twice()->andReturn('mux-asset-id');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Uploaded {$video1->id()}")
        ->expectsOutputToContain("Uploaded {$video2->id()}")
        ->expectsOutputToContain('✓ Uploaded 2 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

// Dry-run tests

it('performs dry-run without uploading', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    $service->shouldNotReceive('createMuxAsset');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(UploadCommand::class, ['--dry-run' => true])
        ->expectsOutput('Performing dry run: no videos will be uploaded')
        ->expectsOutputToContain("Would upload {$video->id()}")
        ->expectsOutputToContain('✓ Would have uploaded 1 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('shows dry-run output for reupload with force flag', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $service->shouldNotReceive('createMuxAsset');
    app()->instance(MuxService::class, $service);

    $this->artisan(UploadCommand::class, ['--dry-run' => true, '--force' => true])
        ->expectsOutput('Performing dry run: no videos will be uploaded')
        ->expectsOutputToContain("Would reupload {$video->id()}")
        ->expectsOutputToContain('✓ Would have uploaded 1 videos, skipped 0 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('shows dry-run output for skipped videos', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    app()->instance(MuxService::class, $service);

    $this->artisan(UploadCommand::class, ['--dry-run' => true])
        ->expectsOutput('Performing dry run: no videos will be uploaded')
        ->expectsOutputToContain("Would skip {$video->id()}")
        ->expectsOutputToContain('✓ Would have uploaded 0 videos, skipped 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

// Mixed scenarios

it('handles mixed scenarios with uploads, reuploads, and skips', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $newVideo = $this->uploadTestFileToTestContainer('test.mp4', 'new.mp4', container: 'videos');
    $existingVideo = $this->uploadTestFileToTestContainer('test.mp4', 'existing.mp4', container: 'videos');
    $reuploadVideo = $this->uploadTestFileToTestContainer('test.mp4', 'reupload.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false, true, true);
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'database']);

    $this->artisan(UploadCommand::class, ['--force' => true])
        ->expectsOutputToContain("Queued upload of {$newVideo->id()}")
        ->expectsOutputToContain("Queued reupload of {$existingVideo->id()}")
        ->expectsOutputToContain("Queued reupload of {$reuploadVideo->id()}")
        ->expectsOutputToContain('✓ Queued 3 videos for background upload, skipped 0 videos')
        ->assertSuccessful();

    // Verify 1 job with force=false (new video)
    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) use ($newVideo) {
        $class = new \ReflectionClass($job);
        $asset = $class->getProperty('asset')->getValue($job);
        $force = $class->getProperty('force')->getValue($job);

        return $asset->id() === $newVideo->id() && $force === false;
    });

    // Verify 2 jobs with force=true (existing videos)
    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) use ($existingVideo, $reuploadVideo) {
        $class = new \ReflectionClass($job);
        $asset = $class->getProperty('asset')->getValue($job);
        $force = $class->getProperty('force')->getValue($job);

        return $force === true
            && ($asset->id() === $existingVideo->id() || $asset->id() === $reuploadVideo->id());
    });

    // Verify total count
    Queue::assertPushed(CreateMuxAssetJob::class, 3);
});

it('handles mixed scenarios without force flag', function () {
    Queue::fake();

    $container = $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $newVideo = $this->uploadTestFileToTestContainer('test.mp4', 'new.mp4', container: 'videos');
    $existingVideo = $this->uploadTestFileToTestContainer('test.mp4', 'existing.mp4', container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    $service->shouldReceive('hasExistingMuxAsset')->andReturn(false, true);
    $service->shouldReceive('createMuxAsset')->once()->andReturn('mux-asset-id');
    app()->instance(MuxService::class, $service);

    config(['queue.default' => 'sync']);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Uploaded {$newVideo->id()}")
        ->expectsOutputToContain("Skipped {$existingVideo->id()}")
        ->expectsOutputToContain('✓ Uploaded 1 videos, skipped 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

// Command name test

it('can be called by command name', function () {
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    app()->instance(MuxService::class, $service);

    $this->artisan('mux:upload')
        ->assertSuccessful();
});
