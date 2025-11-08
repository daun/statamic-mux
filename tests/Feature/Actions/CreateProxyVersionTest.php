<?php

use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);
    $this->service = Mockery::spy($this->app->make(MuxService::class))->makePartial();

    $this->createProxyVersion = Mockery::spy($this->app->makeWith(CreateProxyVersion::class, ['service' => $this->service]))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('duration', 120);
    $this->mp4->save();

    $this->shortMp4 = $this->uploadTestFileToTestContainer('test.mp4', 'short.mp4');
    $this->shortMp4->set('duration', 3);
    $this->shortMp4->save();

    $this->m4a = $this->uploadTestFileToTestContainer('test.mp4', 'test.m4a');
    $this->m4a->set('duration', 120);
    $this->m4a->save();

    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->jpg;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores non-mp4 videos', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->m4a;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores short videos', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->shortMp4;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores videos without mux asset', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->mp4;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});
