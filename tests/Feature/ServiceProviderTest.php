<?php

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Daun\StatamicMux\ServiceProvider;
use Daun\StatamicMux\Thumbnails\PlaceholderService;
use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Console\Application as ArtisanApplication;
use Statamic\Facades\Permission;

test('provides services', function () {
    $provider = new ServiceProvider($this->app);
    expect($provider->provides())->toBeArray()->not->toBeEmpty();
});

test('binds mux service', function () {
    expect($this->app[MuxService::class])->toBeInstanceOf(MuxService::class);
    expect($this->app['mux.service'])->toBeInstanceOf(MuxService::class);
});

test('binds mux api', function () {
    expect($this->app[MuxApi::class])->toBeInstanceOf(MuxApi::class);
    expect($this->app['mux.api'])->toBeInstanceOf(MuxApi::class);
});

test('binds thumbnail service', function () {
    expect($this->app[ThumbnailService::class])->toBeInstanceOf(ThumbnailService::class);
    expect($this->app['mux.thumbnails'])->toBeInstanceOf(ThumbnailService::class);
});

test('binds placeholder service', function () {
    expect($this->app[PlaceholderService::class])->toBeInstanceOf(PlaceholderService::class);
    expect($this->app['mux.placeholders'])->toBeInstanceOf(PlaceholderService::class);
});

test('binds url service', function () {
    expect($this->app[MuxUrls::class])->toBeInstanceOf(MuxUrls::class);
    expect($this->app['mux.urls'])->toBeInstanceOf(MuxUrls::class);
});

test('registers mux permissions', function () {
    Permission::boot();

    expect(Permission::get('manage mux'))->not->toBeNull();
    expect(Permission::get('view mux library'))->not->toBeNull();
    expect(Permission::get('view mux dashboard'))->not->toBeNull();
    expect(Permission::get('delete mux assets'))->not->toBeNull();
    expect(Permission::get('trigger mux sync'))->not->toBeNull();
    expect(Permission::get('view mux'))->toBeNull();
});

test('registers commands when artisan starts outside console', function () {
    $runningInConsole = new ReflectionProperty($this->app, 'isRunningInConsole');
    $runningInConsole->setValue($this->app, false);

    $provider = new ServiceProvider($this->app);
    $bootCommands = new ReflectionMethod($provider, 'bootCommands');
    $bootCommands->invoke($provider);

    $artisan = new ArtisanApplication($this->app, $this->app['events'], $this->app->version());
    $commands = array_keys($artisan->all());

    expect($commands)->toContain('mux:mirror');
    expect($commands)->toContain('mux:upload');
    expect($commands)->toContain('mux:prune');
});
