<?php

use Daun\StatamicMux\Commands\DebugCommand;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
});

it('warns about missing credentials', function () {
    config(['mux.credentials.token_id' => null]);
    config(['mux.credentials.token_secret' => null]);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× Mux is not configured');
    expect($output)->toContain('Please add valid Mux credentials in your .env file');
});

it('confirms valid credentials are configured', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ Mux is configured with credentials');
});

it('warns when queue is synchronous', function () {
    config(['queue.default' => 'sync']);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× The queue is set to synchronous mode');
    expect($output)->toContain('It is recommended to use a background queue worker');
});

it('confirms queue uses background worker', function () {
    config(['queue.default' => 'database']);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ The queue is configured to use a background worker');
});

it('respects custom mux queue connection', function () {
    config(['queue.default' => 'database']);
    config(['mux.queue.connection' => 'sync']);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× The queue is set to synchronous mode');
});

it('warns when mirror feature is globally disabled', function () {
    config(['mux.mirror.enabled' => false]);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× The mirror feature is globally disabled from the config flag');
});

it('confirms mirror feature is globally enabled', function () {
    config(['mux.mirror.enabled' => true]);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ The mirror feature is globally enabled');
});

it('warns when no asset containers have mirror field', function () {
    $this->createAssetContainer('test');
    $this->createAssetContainer('another');

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× No asset containers found to mirror');
    expect($output)->toContain('Please add a `mux_mirror` field to at least one of your asset blueprints');
});

it('lists single asset container with mirror field', function () {
    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ Found 1 asset container(s) configured for mirroring');
    expect($output)->toContain('test_container_videos');
});

it('lists multiple asset containers with mirror field', function () {
    $this->createAssetContainer('videos');
    $this->createAssetContainer('media');
    $this->createAssetContainer('images'); // without mirror field

    $this->addMirrorFieldToAssetBlueprint(container: 'videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'media');

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ Found 2 asset container(s) configured for mirroring');
    expect($output)->toContain('test_container_videos');
    expect($output)->toContain('test_container_media');
    expect($output)->not->toContain('test_container_images');
});

it('shows all checks passing with optimal configuration', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);
    config(['queue.default' => 'database']);
    config(['mux.mirror.enabled' => true]);

    $this->createAssetContainer('videos');
    $this->addMirrorFieldToAssetBlueprint(container: 'videos');

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('✓ Mux is configured with credentials');
    expect($output)->toContain('✓ The queue is configured to use a background worker');
    expect($output)->toContain('✓ The mirror feature is globally enabled');
    expect($output)->toContain('✓ Found 1 asset container(s) configured for mirroring');
    expect($output)->not->toContain('×');
});

it('shows all checks failing with problematic configuration', function () {
    config(['mux.credentials.token_id' => null]);
    config(['mux.credentials.token_secret' => null]);
    config(['queue.default' => 'sync']);
    config(['mux.mirror.enabled' => false]);

    $this->createAssetContainer('test');

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× Mux is not configured');
    expect($output)->toContain('× The queue is set to synchronous mode');
    expect($output)->toContain('× The mirror feature is globally disabled');
    expect($output)->toContain('× No asset containers found to mirror');
    expect($output)->not->toContain('✓');
});

it('returns zero exit code on success', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    $exitCode = Artisan::call(DebugCommand::class);

    expect($exitCode)->toBe(0);
});

it('can be called by command name', function () {
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => 'test-token-secret']);

    $exitCode = Artisan::call('mux:debug');

    expect($exitCode)->toBe(0);
});

it('handles partial configuration correctly', function () {
    // Only token_id provided
    config(['mux.credentials.token_id' => 'test-token-id']);
    config(['mux.credentials.token_secret' => null]);

    Artisan::call(DebugCommand::class);

    $output = Artisan::output();

    expect($output)->toContain('× Mux is not configured');
});
