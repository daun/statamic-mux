<?php

use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Event;
use Statamic\Assets\Asset;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->api = Mockery::mock(MuxApi::class);
    $this->service = Mockery::mock(MuxService::class);
    $this->asset = Mockery::mock(Asset::class);
    $this->createMuxAsset = Mockery::spy(new CreateMuxAsset($this->app, $this->service, $this->api))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->jpg);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('ignores existing mux asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('handles cancelled uploading event', function () {
    Event::fake([AssetUploadedToMux::class]);
    Event::listen(AssetUploadingToMux::class, fn () => false);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBeNull();
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('ingests assets from public containers', function () {
    Event::fake([AssetUploadedToMux::class]);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->createMuxAsset->shouldReceive('ingestAssetToMux')->with($this->mp4)->andReturn('mux_id');
    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('mux_id');
    Event::assertDispatched(AssetUploadedToMux::class);
});

it('uploads assets from private containers', function () {
    Event::fake([AssetUploadedToMux::class]);

    $privateContainer = $this->createAssetContainer('private', ['private' => true]);
    $privateMp4 = $this->uploadTestFileToTestContainer('test.mp4', $privateContainer);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->createMuxAsset->shouldReceive('uploadAssetToMux')->with($privateMp4)->andReturn('mux_id');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($privateMp4);

    expect($result)->toBe('mux_id');
    Event::assertDispatched(AssetUploadedToMux::class);
});

it('uploads in local environment', function () {
    Event::fake([AssetUploadedToMux::class]);

    $this->app['env'] = 'local';

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->createMuxAsset->shouldReceive('uploadAssetToMux')->with($this->mp4)->andReturn('mux_id');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('mux_id');
    Event::assertDispatched(AssetUploadedToMux::class);
});
