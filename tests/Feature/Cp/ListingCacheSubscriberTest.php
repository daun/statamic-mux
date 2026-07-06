<?php

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Subscribers\ListingCacheSubscriber;
use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Support\Facades\Cache;
use MuxPhp\Models\Asset;
use MuxPhp\Models\PlaybackID;
use Statamic\Facades\Stache;

beforeEach(function () {
    config(['mux.mirror.enabled' => false]);

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-001']]);
    $this->mp4->save();

    Stache::clear();

    $remoteAsset = Mockery::mock(Asset::class);
    $remoteAsset->shouldReceive('getId')->andReturn('mux-asset-001');
    $remoteAsset->shouldReceive('getStatus')->andReturn('ready');
    $remoteAsset->shouldReceive('getDuration')->andReturn(120.0);
    $remoteAsset->shouldReceive('getResolutionTier')->andReturn('1080p');
    $remoteAsset->shouldReceive('getMaxResolutionTier')->andReturn('1080p');
    $remoteAsset->shouldReceive('getTest')->andReturn(false);
    $remoteAsset->shouldReceive('getCreatedAt')->andReturn('1717200000');
    $remoteAsset->shouldReceive('getAspectRatio')->andReturn('16:9');
    $meta = Mockery::mock();
    $meta->shouldReceive('getTitle')->andReturn('Test Video');
    $remoteAsset->shouldReceive('getMeta')->andReturn($meta);
    $playbackId = Mockery::mock(PlaybackID::class);
    $playbackId->shouldReceive('getId')->andReturn('playback-mux-asset-001');
    $playbackId->shouldReceive('getPolicy')->andReturn('public');
    $remoteAsset->shouldReceive('getPlaybackIds')->andReturn([$playbackId]);
    $remoteAsset->shouldReceive('getPassthrough')->andReturn(null);

    $this->remoteAssets = collect([$remoteAsset]);

    $muxApi = Mockery::mock(MuxApi::class);
    $muxApi->shouldReceive('listAllAssets')->andReturn($this->remoteAssets);
    $muxApi->shouldReceive('getAssets')->andReturn(collect());
    $muxApi->shouldReceive('dashboardUrl')->andReturn(null);

    $muxService = Mockery::mock(MuxService::class);
    $muxService->shouldReceive('getPlayerUrl')->andReturnUsing(fn (MuxPlaybackId $playbackId, array $params = []) => "https://player.mux.com/{$playbackId->id()}");
    $muxService->shouldReceive('getEmbedCode')->andReturnUsing(fn (MuxPlaybackId $playbackId, array $params = []) => "<iframe src=\"https://player.mux.com/{$playbackId->id()}\"></iframe>");
    $muxService->shouldReceive('getPlaybackUrl')->andReturnUsing(fn (MuxPlaybackId $playbackId, array $params = []) => "https://stream.mux.com/{$playbackId->id()}.m3u8");

    $thumbnails = Mockery::mock(ThumbnailService::class);
    $thumbnails->shouldReceive('forAsset')->andReturn('https://image.mux.com/playback-001/animated.gif');
    $thumbnails->shouldReceive('forPlaybackId')->andReturnUsing(fn (MuxPlaybackId $playbackId, string $orientation = 'landscape', ?int $size = null) => "https://image.mux.com/{$playbackId->id()}/thumbnail.webp".($size ? "?width={$size}" : ''));

    $this->app->instance(MuxApi::class, $muxApi);
    $this->app->instance('mux.api', $muxApi);
    $this->app->instance(MuxService::class, $muxService);
    $this->app->instance('mux.service', $muxService);

    $this->reconciler = new ListingReconciler($muxApi, $muxService, $thumbnails);
});

test('subscriber listens to AssetUploadedToMux', function () {
    $subscriber = $this->app->make(ListingCacheSubscriber::class);
    $events = $subscriber->subscribe();

    expect($events)->toHaveKey(AssetUploadedToMux::class);
});

test('dispatching AssetUploadedToMux invalidates remote listing cache', function () {
    // Populate cache
    $this->reconciler->getCachedRemoteAssets();
    expect(Cache::has('mux.remote_assets'))->toBeTrue();
    expect(Cache::has('mux.remote_assets.valid'))->toBeTrue();

    // Dispatch event
    AssetUploadedToMux::dispatch($this->mp4, 'mux-asset-new');

    // Validity key gone, data still cached
    expect(Cache::has('mux.remote_assets.valid'))->toBeFalse();
    expect(Cache::has('mux.remote_assets'))->toBeTrue();
});

test('invalidateRemoteAssets clears validity key without refetching', function () {
    // Populate cache
    $this->reconciler->getCachedRemoteAssets();
    expect(Cache::has('mux.remote_assets.valid'))->toBeTrue();

    $this->reconciler->invalidateRemoteAssets();

    expect(Cache::has('mux.remote_assets.valid'))->toBeFalse();
    expect(Cache::has('mux.remote_assets'))->toBeTrue();
});
