<?php

use Daun\StatamicMux\Commands\RelinkCommand;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Statamic\Assets\Asset;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
    config([
        'mux.credentials.token_id' => 'test-token-id',
        'mux.credentials.token_secret' => 'test-token-secret',
        'mux.mirror.enabled' => false,
    ]);
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
});

function bindRelinkPlan(array $locals, array $remotes = [], ?string $container = null): MuxService
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

/**
 * A local file with one candidate in the given state, plus the matching remote record.
 *
 * @return array{0: Asset, 1: MuxPhp\Models\Asset, 2: MuxService}
 */
function relinkScenario($test, ReconciliationState $state, array $attributes = []): array
{
    $video = $test->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    $remote = muxRemoteVideo('mux-id');
    $candidate = muxRemoteRecord($remote, $state, $video, $attributes);
    $local = muxLocalRecord($video, $state, ['selected' => $candidate, ...$attributes]);
    $service = bindRelinkPlan([$local], [$candidate]);

    return [$video, $remote->asset(), $service];
}

it('relinks all safe matches without prompting in a non tty', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::Unlinked);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, false)->once()->andReturnTrue();

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])
        ->expectsOutputToContain("Re-linked {$video->id()} to mux-id")
        ->assertSuccessful();
});

it('holds a media mismatch unless force is passed', function () {
    [, , $service] = relinkScenario($this, ReconciliationState::MediaMismatch, ['reason' => 'Duration differs.']);
    $service->shouldNotReceive('relinkMuxAsset');

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])
        ->expectsOutputToContain('media mismatches held')
        ->assertSuccessful();
});

it('force relinks a media mismatch', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::MediaMismatch);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, false)->once()->andReturnTrue();

    $this->artisan(RelinkCommand::class, ['--force' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

it('dry run never writes or prompts', function () {
    [, , $service] = relinkScenario($this, ReconciliationState::ProxySource);
    $service->shouldNotReceive('relinkMuxAsset');

    $this->artisan(RelinkCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('Performing dry run')
        ->assertSuccessful();
});

it('relinks a placeholder source with the proxy flag set', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::ProxySource);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, true)->once()->andReturnTrue();

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])
        ->expectsOutputToContain('placeholder source')
        ->assertSuccessful();
});

it('returns failure when a relink write fails', function () {
    [, , $service] = relinkScenario($this, ReconciliationState::Unlinked);
    $service->shouldReceive('relinkMuxAsset')->once()->andReturnFalse();

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])->assertFailed();
});

it('can be called by command name', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    bindRelinkPlan([muxLocalRecord($video, ReconciliationState::Linked, ['muxId' => 'mux-id'])]);

    $this->artisan('mux:relink', ['--no-interaction' => true])
        ->expectsOutputToContain('Nothing to re-link')
        ->assertSuccessful();
});
