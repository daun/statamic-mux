<?php

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use Daun\StatamicMux\ServiceProvider;

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

test('binds placeholder service', function () {
    expect($this->app[PlaceholderService::class])->toBeInstanceOf(PlaceholderService::class);
    expect($this->app['mux.placeholders'])->toBeInstanceOf(PlaceholderService::class);
});

test('binds url service', function () {
    expect($this->app[MuxUrls::class])->toBeInstanceOf(MuxUrls::class);
    expect($this->app['mux.urls'])->toBeInstanceOf(MuxUrls::class);
});
