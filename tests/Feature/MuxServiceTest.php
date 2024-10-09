<?php

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
