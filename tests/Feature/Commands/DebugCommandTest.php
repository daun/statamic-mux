<?php

use Daun\StatamicMux\Commands\DebugCommand;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
});

it('warns about missing credentials', function () {
    config(['mux.credentials.token_id' => null]);
    config(['mux.credentials.token_secret' => null]);

    $this->artisan(DebugCommand::class)
        ->expectsOutput('✗ Mux is not configured. Please add valid Mux credentials in your .env file.')
        ->assertSuccessful();
});

it('confirms valid credentials are configured', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✓ Mux is configured with credentials')
        ->assertSuccessful();
});

it('warns when queue is synchronous', function () {
    config(['queue.default' => 'sync']);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('synchronous mode')
        ->assertSuccessful();
});

it('confirms queue uses background worker', function () {
    config(['queue.default' => 'database']);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✓ The queue is configured to use a background worker')
        ->assertSuccessful();
});

it('respects custom mux queue connection', function () {
    config(['queue.default' => 'database']);
    config(['mux.queue.connection' => 'sync']);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✗ The queue is set to synchronous mode')
        ->assertSuccessful();
});

it('warns when mirror feature is globally disabled', function () {
    config(['mux.mirror.enabled' => false]);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✗ The mirror feature is globally disabled from the config flag')
        ->assertSuccessful();
});

it('confirms mirror feature is globally enabled', function () {
    config(['mux.mirror.enabled' => true]);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✓ The mirror feature is globally enabled')
        ->assertSuccessful();
});

it('warns when no asset containers have mirror field', function () {
    $this->createAssetContainer('test');
    $this->createAssetContainer('another');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('No asset containers found')
        ->assertSuccessful();
});

it('lists single asset container with mirror field', function () {
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('Found 1 asset container')
        ->assertSuccessful();
});

it('lists multiple asset containers with mirror field', function () {
    $this->createAssetContainer('videos');
    $this->createAssetContainer('media');
    $this->createAssetContainer('images'); // without mirror field

    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('Found 2 asset container')
        ->assertSuccessful();
});

it('shows all checks passing with optimal configuration', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);
    config(['queue.default' => 'database']);
    config(['mux.mirror.enabled' => true]);

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✓ Mux is configured with credentials')
        ->expectsOutputToContain('✓ The queue is configured to use a background worker')
        ->expectsOutputToContain('✓ The mirror feature is globally enabled')
        ->expectsOutputToContain('✓ Found 1 asset container(s) configured for mirroring')
        ->doesntExpectOutputToContain('✗')
        ->assertSuccessful();
});

it('shows all checks failing with problematic configuration', function () {
    config(['mux.credentials.token_id' => null]);
    config(['mux.credentials.token_secret' => null]);
    config(['queue.default' => 'sync']);
    config(['mux.mirror.enabled' => false]);

    $this->createAssetContainer('test');

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✗ Mux is not configured')
        ->expectsOutputToContain('✗ The queue is set to synchronous mode')
        ->expectsOutputToContain('✗ The mirror feature is globally disabled')
        ->expectsOutputToContain('✗ No asset containers found to mirror')
        ->doesntExpectOutputToContain('✓')
        ->assertSuccessful();
});

it('returns zero exit code on success', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    $this->artisan(DebugCommand::class)
        ->assertSuccessful();
});

it('can be called by command name', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    $this->artisan('mux:debug')
        ->assertSuccessful();
});

it('handles partial configuration correctly', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => null]);

    $this->artisan(DebugCommand::class)
        ->expectsOutputToContain('✗ Mux is not configured')
        ->assertSuccessful();
});
