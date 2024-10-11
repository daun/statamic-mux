<?php

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->service = $this->app->make(MuxService::class);

    $this->addMirrorFieldToAssetBlueprint();
    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->webm = $this->uploadTestFileToTestContainer('test.webm');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    MirrorField::clear($this->mp4);
    MirrorField::clear($this->webm);
    MirrorField::clear($this->jpg);

    Stache::clear();
});

test('returns an api instance', function () {
    expect($this->service->api())->toBeInstanceOf(MuxApi::class);
});

test('returns the configuration state', function () {
    expect($this->service->configured())->toBeBool();
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => 'test', 'mux.credentials.token_secret' => null]);
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => null, 'mux.credentials.token_secret' => 'test']);
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => 'test', 'mux.credentials.token_secret' => 'test']);
    expect($this->service->configured())->toBeTrue();
});

test('returns the default playback policy', function () {
    config(['mux.playback_policy' => null]);
    expect($this->service->getDefaultPlaybackPolicy())->toBeNull();

    config(['mux.playback_policy' => 'public']);
    expect($this->service->getDefaultPlaybackPolicy())->toBeInstanceOf(MuxPlaybackPolicy::class);
    expect($this->service->getDefaultPlaybackPolicy()->isPublic())->toBeTrue();

    config(['mux.playback_policy' => 'signed']);
    expect($this->service->getDefaultPlaybackPolicy())->toBeInstanceOf(MuxPlaybackPolicy::class);
    expect($this->service->getDefaultPlaybackPolicy()->isSigned())->toBeTrue();
});

test('returns the default playback modifiers', function () {
    config(['mux.playback_modifiers' => null]);
    expect($this->service->getDefaultPlaybackModifiers())->toBeArray()->toHaveCount(0);

    config(['mux.playback_modifiers' => []]);
    expect($this->service->getDefaultPlaybackModifiers())->toBeArray()->toHaveCount(0);

    config(['mux.playback_modifiers' => ['width' => 100, 'height' => 100]]);
    expect($this->service->getDefaultPlaybackModifiers())->toEqual(['width' => 100, 'height' => 100]);
});
