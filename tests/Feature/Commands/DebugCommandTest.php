<?php

use Daun\StatamicMux\Commands\DebugCommand;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
    config([
        'mux.credentials.token_id' => 'test-token-id',
        'mux.credentials.token_secret' => 'test-token-secret',
        'mux.mirror.enabled' => true,
        'mux.queue.connection' => null,
        'queue.default' => 'database',
    ]);
});

/** A container that is actually set up for mirroring. */
$mirroredContainer = function (string $handle = 'videos'): string {
    $this->createAssetContainer($handle);
    $this->addMirrorFieldToAssetBlueprint(container: $handle);

    return $this->getAssetContainer($handle)->handle();
};

it('succeeds and summarizes a healthy setup', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('Debug complete — the Mux setup looks good.')
        ->assertSuccessful();
});

it('reports the setup as context rows', function () use ($mirroredContainer) {
    $handle = $mirroredContainer->call($this);

    $json = muxCommandJson('mux:debug');

    expect($json['exit_code'])->toBe(0);
    expect($json['context'])->toBe([
        'Credentials' => 'OK',
        'Queue' => 'database (background)',
        'Mirror feature' => 'ON',
        'Containers' => $handle,
        'Signed playback' => 'OFF',
    ]);
    expect($json['failures'])->toBe([]);
    expect($json['advisories'])->toBe([]);
});

it('lists every mirroring container as context', function () use ($mirroredContainer) {
    $videos = $mirroredContainer->call($this, 'videos');
    $media = $mirroredContainer->call($this, 'media');
    $this->createAssetContainer('images'); // without a mirror field

    $json = muxCommandJson('mux:debug');

    expect($json['context']['Containers'])->toContain($videos);
    expect($json['context']['Containers'])->toContain($media);
    expect($json['context']['Containers'])->not->toContain('images');
});

it('fails when the setup is broken', function (array $config, string $message) use ($mirroredContainer) {
    $mirroredContainer->call($this);
    config($config);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'missing credentials' => [
        ['mux.credentials.token_id' => null, 'mux.credentials.token_secret' => null],
        'Mux is not configured',
    ],
    'partial credentials' => [
        ['mux.credentials.token_secret' => null],
        'Mux is not configured',
    ],
    'disabled mirror' => [
        ['mux.mirror.enabled' => false],
        'The mirror feature is globally disabled',
    ],
]);

it('fails when no container is configured for mirroring', function () {
    $this->createAssetContainer('images');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('No asset containers found to mirror')
        ->assertFailed();
});

it('warns about a synchronous queue without failing a healthy setup', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);
    config(['queue.default' => 'sync']);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('The queue is synchronous.')
        ->assertSuccessful();
});

it('warns about a synchronous mux queue connection', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);
    config(['queue.default' => 'database', 'mux.queue.connection' => 'sync']);

    $json = muxCommandJson('mux:debug');

    expect($json['exit_code'])->toBe(0);
    expect($json['context']['Queue'])->toBe('sync (not recommended)');
    expect(collect($json['advisories'])->pluck('code'))->toContain('sync-queue');
});

it('warns about signed playback without a signing key', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);
    config([
        'mux.playback_policy' => 'signed',
        'mux.signing_key.key_id' => null,
        'mux.signing_key.private_key' => null,
    ]);

    $json = muxCommandJson('mux:debug');

    expect($json['exit_code'])->toBe(0);
    expect($json['context']['Signed playback'])->toBe('ON');
    expect(collect($json['advisories'])->pluck('code'))->toContain('signing-key');
});

it('emits one glyph-free json object on success', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);

    Artisan::call('mux:debug', ['--json' => true]);
    $output = trim(Artisan::output());

    foreach (['✓', '✗', '⚠'] as $glyph) {
        expect($output)->not->toContain($glyph);
    }

    $json = json_decode($output, true);
    expect($json['command'])->toBe('mux:debug');
    expect($json['exit_code'])->toBe(0);
    expect($json['records'])->toBe([]);
    expect($json['failures'])->toBe([]);
});

it('emits one glyph-free json object listing every failed check', function () {
    $this->createAssetContainer('images');
    config([
        'mux.credentials.token_id' => null,
        'mux.credentials.token_secret' => null,
        'mux.mirror.enabled' => false,
        'queue.default' => 'sync',
    ]);

    Artisan::call('mux:debug', ['--json' => true]);
    $output = trim(Artisan::output());

    foreach (['✓', '✗', '⚠'] as $glyph) {
        expect($output)->not->toContain($glyph);
    }

    $json = json_decode($output, true);
    expect($json['exit_code'])->toBe(1);
    expect($json['context'])->toMatchArray([
        'Credentials' => 'MISSING',
        'Mirror feature' => 'OFF',
        'Containers' => 'NONE',
    ]);
    expect(collect($json['failures'])->pluck('error')->all())->toBe([
        'Mux is not configured. Please add valid Mux credentials in your .env file.',
        'The mirror feature is globally disabled from the config flag.',
        'No asset containers found to mirror. Please add a `mux_mirror` field to at least one of your asset blueprints.',
    ]);
    expect(collect($json['advisories'])->pluck('code')->all())->toBe(['setup', 'setup', 'setup', 'sync-queue']);
});

it('can be called by command name', function () use ($mirroredContainer) {
    $mirroredContainer->call($this);

    $this->artisan('mux:debug')->assertSuccessful();
});
