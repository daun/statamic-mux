<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Event;
use Statamic\Assets\Asset;

beforeEach(function () {
    $this->api = Mockery::mock(MuxApi::class);
    $this->service = Mockery::mock(MuxService::class);
    $this->asset = Mockery::mock(Asset::class)->makePartial();
    $this->createMuxAsset = Mockery::spy(new CreateMuxAsset($this->app, $this->service, $this->api))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
})->only();

it('ignores non-video asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->asset->shouldReceive('isVideo')->andReturn(false);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->asset);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('ignores existing mux asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->asset->shouldReceive('isVideo')->andReturn(true);
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->asset);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('handles cancelled uploading event', function () {
    Event::fake([AssetUploadedToMux::class]);
    Event::listen(AssetUploadingToMux::class, fn () => false);

    $this->asset->shouldReceive('isVideo')->andReturn(true);
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $result = $this->createMuxAsset->handle($this->asset);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('handles local or private asset', function () {
    $this->asset->shouldReceive('isVideo')->andReturn(true);
    $muxAsset = Mockery::mock(MuxAsset::class);
    $muxAsset->shouldReceive('existsOnMux')->andReturn(false);

    MuxAsset::shouldReceive('fromAsset')->with($this->asset)->andReturn($muxAsset);
    AssetUploadingToMux::shouldReceive('dispatch')->with($this->asset)->andReturn(true);

    $this->app->shouldReceive('isLocal')->andReturn(true);
    $this->createMuxAsset->shouldReceive('uploadAssetToMux')->with($this->asset)->andReturn('mux_id');

    $result = $this->createMuxAsset->handle($this->asset);

    expect($result)->toBe('mux_id');
});

it('handles public asset', function () {
    $this->asset->shouldReceive('isVideo')->andReturn(true);
    $muxAsset = Mockery::mock(MuxAsset::class);
    $muxAsset->shouldReceive('existsOnMux')->andReturn(false);

    MuxAsset::shouldReceive('fromAsset')->with($this->asset)->andReturn($muxAsset);
    AssetUploadingToMux::shouldReceive('dispatch')->with($this->asset)->andReturn(true);

    $this->app->shouldReceive('isLocal')->andReturn(false);
    $this->asset->shouldReceive('container')->andReturnSelf();
    $this->asset->shouldReceive('private')->andReturn(false);
    $this->createMuxAsset->shouldReceive('ingestAssetToMux')->with($this->asset)->andReturn('mux_id');

    $result = $this->createMuxAsset->handle($this->asset);

    expect($result)->toBe('mux_id');
});

it('uploads asset to mux', function () {
    $this->api->shouldReceive('createUploadRequest')->andReturn('request');
    $this->api->shouldReceive('directUploads')->andReturnSelf();
    $this->api->shouldReceive('createDirectUpload')->andReturnSelf();
    $this->api->shouldReceive('getData')->andReturn((object)['getId' => 'upload_id', 'getUrl' => 'upload_url']);
    $this->api->shouldReceive('client')->andReturnSelf();
    $this->api->shouldReceive('put')->andReturnSelf();
    $this->api->shouldReceive('getDirectUpload')->andReturnSelf();
    $this->api->shouldReceive('getData')->andReturn((object)['getAssetId' => 'mux_id']);

    $this->asset->shouldReceive('contents')->andReturn('video_content');

    $result = $this->createMuxAsset->uploadAssetToMux($this->asset);

    expect($result)->toBe('mux_id');
});

it('ingests asset to mux', function () {
    $this->api->shouldReceive('createAssetRequest')->andReturn('request');
    $this->api->shouldReceive('assets')->andReturnSelf();
    $this->api->shouldReceive('createAsset')->andReturnSelf();
    $this->api->shouldReceive('getData')->andReturn((object)['getId' => 'mux_id']);

    $this->asset->shouldReceive('absoluteUrl')->andReturn('http://example.com/video.mp4');

    $result = $this->createMuxAsset->ingestAssetToMux($this->asset);

    expect($result)->toBe('mux_id');
});

it('gets asset passthrough data', function () {
    $this->asset->shouldReceive('id')->andReturn('asset_id');

    $result = $this->createMuxAsset->getAssetPassthroughData($this->asset);

    expect($result)->toBe('statamic::asset_id');
});

it('gets asset identifier', function () {
    $this->asset->shouldReceive('id')->andReturn('asset_id');

    $result = $this->createMuxAsset->getAssetIdentifier($this->asset);

    expect($result)->toBe('statamic::asset_id');
});
