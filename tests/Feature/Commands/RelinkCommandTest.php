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
        'queue.default' => 'sync',
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
        ->expectsOutputToContain('Relink complete — 1 re-linked.')
        ->assertSuccessful();
});

it('relinks safe matches in json without prompting', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::Unlinked);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, false)->once()->andReturnTrue();

    $json = muxCommandJson('mux:relink');

    expect($json['exit_code'])->toBe(0);
    expect($json['dry_run'])->toBeFalse();
    expect($json['plan']['relink'])->toBe(1);
    expect($json['failures'])->toBe([]);
    expect($json['records'])->toBe([[
        'action' => 'relink',
        'id' => $video->id(),
        'state' => 'unlinked',
        'reason' => null,
    ]]);
});

it('holds a media mismatch unless force is passed', function () {
    [, , $service] = relinkScenario($this, ReconciliationState::MediaMismatch, ['reason' => 'Duration differs.']);
    $service->shouldNotReceive('relinkMuxAsset');

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])
        ->expectsOutputToContain('Relink complete — 1 held.')
        ->assertSuccessful();
});

it('holds a media mismatch in json unless force is passed', function () {
    [$video, , $service] = relinkScenario($this, ReconciliationState::MediaMismatch, ['reason' => 'Duration differs.']);
    $service->shouldNotReceive('relinkMuxAsset');

    $json = muxCommandJson('mux:relink');

    expect($json['exit_code'])->toBe(0);
    expect($json['plan']['relink'])->toBe(0);
    expect($json['plan']['hold'])->toBe(1);
    expect($json['records'][0]['action'])->toBe('hold');
    expect($json['records'][0]['id'])->toBe($video->id());
    expect($json['records'][0]['state'])->toBe('media-mismatch');
});

it('force relinks a media mismatch in json', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::MediaMismatch);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, false)->once()->andReturnTrue();

    $json = muxCommandJson('mux:relink', ['--force' => true]);

    expect($json['plan']['relink'])->toBe(1);
    expect($json['plan']['hold'])->toBe(0);
    expect($json['records'][0]['action'])->toBe('relink');
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
        ->expectsOutputToContain('1 re-link pending.')
        ->assertSuccessful();
});

it('relinks a placeholder source with the proxy flag set', function () {
    [$video, $remote, $service] = relinkScenario($this, ReconciliationState::ProxySource);
    $service->shouldReceive('relinkMuxAsset')->with($video, $remote, true)->once()->andReturnTrue();

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])
        ->expectsOutputToContain('placeholder clip is re-linked')
        ->assertSuccessful();
});

it('returns failure when a relink write fails', function () {
    [, , $service] = relinkScenario($this, ReconciliationState::Unlinked);
    $service->shouldReceive('relinkMuxAsset')->once()->andReturnFalse();

    $this->artisan(RelinkCommand::class, ['--no-interaction' => true])->assertFailed();
});

it('reports a failed relink in the json failures contract', function () {
    [$video, , $service] = relinkScenario($this, ReconciliationState::Unlinked);
    $service->shouldReceive('relinkMuxAsset')->once()->andReturnFalse();

    $json = muxCommandJson('mux:relink');

    expect($json['exit_code'])->toBe(1);
    expect($json['plan']['relink'])->toBe(0);
    expect($json['failures'])->toBe([[
        'action' => 'relink',
        'id' => $video->id(),
        'error' => 'The Mux asset could not be re-linked.',
    ]]);
});

it('can be called by command name', function () {
    $video = $this->uploadTestFileToTestContainer('test.mp4', container: 'videos');
    bindRelinkPlan([muxLocalRecord($video, ReconciliationState::Linked, ['muxId' => 'mux-id'])]);

    $this->artisan('mux:relink', ['--no-interaction' => true])
        ->expectsOutputToContain('Relink complete — no action needed.')
        ->assertSuccessful();
});
