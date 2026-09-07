<?php

use Daun\StatamicMux\Commands\UploadCommand;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Asset;
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

function uploadCommandPlan($test, array $locals, array $remotes = [], ?string $container = null): MuxService
{
    config(['mux.mirror.enabled' => true]);
    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('plan')->with($container)->once()->andReturn(muxPlan($locals, $remotes, $container));
    app()->instance(Reconciler::class, $reconciler);

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('configured')->andReturn(true);
    app()->instance(MuxService::class, $service);

    return $service;
}

it('returns failure for invalid command preconditions', function (array $config, string $message) {
    config($config);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    [['mux.credentials.token_id' => null, 'mux.credentials.token_secret' => null], 'Mux is not configured'],
    [['mux.mirror.enabled' => false], 'mirror feature is currently disabled'],
]);

it('returns failure for an unknown container', function () {
    config(['mux.mirror.enabled' => true]);

    $this->artisan(UploadCommand::class, ['--container' => 'missing'])
        ->expectsOutputToContain("Asset container 'missing' not found")
        ->assertFailed();
});

it('shows a message when no videos are found', function () {
    uploadCommandPlan($this, []);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain('No videos found')
        ->assertSuccessful();
});

it('uploads an unlinked asset and reports an attributable replacement', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $candidate = muxRemoteRecord('existing-mux', ReconciliationState::Unlinked, $video);
    uploadCommandPlan(
        $this,
        [muxLocalRecord($video, ReconciliationState::Unlinked, ['selected' => $candidate])],
        [$candidate],
    );

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain('already has attributable Mux encodings')
        ->expectsOutputToContain("Queued upload of {$video->id()}")
        ->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, fn ($job) => (new ReflectionProperty($job, 'asset'))->getValue($job)->id() === $video->id());
});

it('warns before uploading a placeholder clip over its master', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $candidate = muxRemoteRecord('master-mux', ReconciliationState::ProxySource, $video);
    uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::ProxySource, ['selected' => $candidate])]);

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain('replace the full master with the short placeholder clip')
        ->assertSuccessful();
});

it('skips already linked videos without the force flag', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $video->set('mux', ['id' => 'mux-video'])->saveQuietly();
    uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::Linked, ['muxId' => 'mux-video'])]);

    $this->artisan(UploadCommand::class, ['-v' => true])
        ->expectsOutputToContain("Skipped {$video->id()}")
        ->expectsOutputToContain('skipped 1 videos')
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('clears a stale mux id before uploading its replacement', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $video->set('mux', ['id' => 'missing-mux'])->saveQuietly();
    $service = uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::Reupload, ['muxId' => 'missing-mux'])]);
    $service->shouldReceive('clearMuxAsset')->with($video)->once();

    $this->artisan(UploadCommand::class)->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) {
        return (new ReflectionProperty($job, 'force'))->getValue($job) === false;
    });
});

it('force reuploads linked assets but never proxies', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', 'video.mp4', container: 'videos');
    $proxy = $this->uploadTestFileToTestContainer('test.mp4', 'proxy.mp4', container: 'videos');
    $video->set('mux', ['id' => 'mux-video'])->saveQuietly();
    $proxy->set('mux', ['id' => 'mux-proxy', 'is_proxy' => true])->saveQuietly();
    uploadCommandPlan($this, [
        muxLocalRecord($video, ReconciliationState::Linked, ['muxId' => 'mux-video']),
        muxLocalRecord($proxy, ReconciliationState::Linked, ['muxId' => 'mux-proxy']),
    ]);

    $this->artisan(UploadCommand::class, ['--force' => true, '-v' => true])
        ->expectsOutputToContain("Queued reupload of {$video->id()}")
        ->expectsOutputToContain("Skipped {$proxy->id()}")
        ->assertSuccessful();

    Queue::assertPushed(CreateMuxAssetJob::class, 1);
});

it('uploads synchronously when the queue is sync', function () {
    config(['queue.default' => 'sync']);
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $service = uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::Upload)]);
    $service->shouldReceive('createMuxAsset')->with($video, false)->once()->andReturn('new-mux');

    $this->artisan(UploadCommand::class)
        ->expectsOutputToContain("Uploaded {$video->id()}")
        ->assertSuccessful();
});

it('honors container scope and dry run without writing', function () {
    Queue::fake();
    $this->createAssetContainer('media');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $other = $this->uploadTestFileToTestContainer('test.mp4', container: 'media');
    $container = $video->containerHandle();
    uploadCommandPlan($this, [
        muxLocalRecord($video, ReconciliationState::Upload),
        muxLocalRecord($other, ReconciliationState::Upload),
    ], container: $container);

    $this->artisan(UploadCommand::class, ['--container' => $container, '--dry-run' => true])
        ->expectsOutputToContain("Would upload {$video->id()}")
        ->doesntExpectOutputToContain($other->id())
        ->assertSuccessful();

    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

// Regression: a dry run once cleared the stored Mux ID before deciding not to
// upload, leaving the asset unlinked. See the fix in "Avoid asset meta writes
// during command dry run".
it('does not mutate local asset metadata during a dry run', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $video->set('mux', ['id' => 'missing-mux'])->saveQuietly();
    $service = uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::Reupload, ['muxId' => 'missing-mux'])]);
    $service->shouldNotReceive('clearMuxAsset');
    $service->shouldNotReceive('createMuxAsset');

    $this->artisan(UploadCommand::class, ['--dry-run' => true])->assertSuccessful();

    expect(Asset::find($video->id())->get('mux'))->toBe(['id' => 'missing-mux']);
    Queue::assertNotPushed(CreateMuxAssetJob::class);
});

it('can be called by command name', function () {
    Queue::fake();
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    uploadCommandPlan($this, [muxLocalRecord($video, ReconciliationState::Upload)]);

    $this->artisan('mux:upload', ['--dry-run' => true])
        ->expectsOutputToContain("Would upload {$video->id()}")
        ->assertSuccessful();
});
