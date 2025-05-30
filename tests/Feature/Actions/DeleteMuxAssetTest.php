<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);

    $this->deleteMuxAsset = Mockery::spy($this->app->make(DeleteMuxAsset::class))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    Event::fake([AssetDeletingFromMux::class, AssetDeletedFromMux::class]);

    $this->deleteMuxAsset->shouldNotReceive('deleteOrphanedMuxAsset');
    $this->deleteMuxAsset->shouldNotReceive('deleteConnectedMuxAsset');

    $result = $this->deleteMuxAsset->handle($this->jpg);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetDeletingFromMux::class);
    Event::assertNotDispatched(AssetDeletedFromMux::class);
});

it('ignores local assets without Mux data', function () {
    Event::fake([AssetDeletingFromMux::class, AssetDeletedFromMux::class]);

    $this->deleteMuxAsset->shouldNotReceive('deleteOrphanedMuxAsset');
    $this->deleteMuxAsset->shouldNotReceive('deleteConnectedMuxAsset');

    $result = $this->deleteMuxAsset->handle($this->mp4);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetDeletingFromMux::class);
    Event::assertNotDispatched(AssetDeletedFromMux::class);
});

it('handles cancelled deleting event', function () {
    Event::fake([AssetDeletedFromMux::class]);
    Event::listen(AssetDeletingFromMux::class, fn () => false);

    $result = $this->deleteMuxAsset->handle($this->mp4);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetDeletedFromMux::class);
});

it('ignores Mux assets not created by the addon', function () {
    Event::fake([AssetDeletingFromMux::class, AssetDeletedFromMux::class]);

    $this->addMirrorFieldToAssetBlueprint();
    MuxAsset::fromAsset($this->mp4)
        ->setId('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->save();

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv',
                'asset_id' => '123456789',
                'video_quality' => 'plus',
                'passthrough' => 'example-passthrough',
            ],
        ]);

    $result = $this->deleteMuxAsset->handle($this->mp4);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(1);
    Event::assertDispatched(AssetDeletingFromMux::class);
    Event::assertNotDispatched(AssetDeletedFromMux::class);
});

it('deletes associated Mux assets of local assets created by the addon', function () {
    Event::fake([AssetDeletedFromMux::class]);

    $this->addMirrorFieldToAssetBlueprint();
    MuxAsset::fromAsset($this->mp4)
        ->setId('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->save();

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2',
                'asset_id' => '123456789',
                'video_quality' => 'plus',
                'passthrough' => 'statamic::video.mp4',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->ray()
        ->delete('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespond(Http::response(status: 204));

    $result = $this->deleteMuxAsset->handle($this->mp4);

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(2);
    Event::assertDispatched(AssetDeletedFromMux::class);
});

it('ignores orphaned Mux assets not created by the addon', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/s5MBuXGvJaUWdXuXM93J9Q2yvSqQnqz6')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 's5MBuXGvJaUWdXuXM93J9Q2yvSqQnqz6',
                'asset_id' => '123456789',
                'video_quality' => 'plus',
                'passthrough' => 'example-passthrough',
            ],
        ]);

    $result = $this->deleteMuxAsset->handle('s5MBuXGvJaUWdXuXM93J9Q2yvSqQnqz6');

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(1);
});

it('deletes orphaned Mux assets created by the addon', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2',
                'asset_id' => '123456789',
                'video_quality' => 'plus',
                'passthrough' => 'statamic::video.mp4',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->ray()
        ->delete('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespond(Http::response(status: 204));

    $result = $this->deleteMuxAsset->handle('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2');

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(2);
});
