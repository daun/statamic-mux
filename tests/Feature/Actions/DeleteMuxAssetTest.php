<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset as RemoteAsset;
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
        ->withId('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->save();

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv',
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
        ->withId('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->save();

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2',
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
                'video_quality' => 'plus',
                'passthrough' => 'example-passthrough',
            ],
        ]);

    $result = $this->deleteMuxAsset->handle('s5MBuXGvJaUWdXuXM93J9Q2yvSqQnqz6');

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(1);
});

it('skips deletion when other local assets reference the same Mux id', function () {
    Event::fake([AssetDeletingFromMux::class, AssetDeletedFromMux::class]);

    $this->addMirrorFieldToAssetBlueprint();

    $duplicate = $this->uploadTestFileToTestContainer('test.mp4', 'duplicate.mp4');

    MuxAsset::fromAsset($this->mp4)
        ->withId('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->save();
    MuxAsset::fromAsset($duplicate)
        ->withId('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv')
        ->save();
    Stache::clear();

    $result = $this->deleteMuxAsset->handle($this->mp4);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetDeletingFromMux::class);
    Event::assertNotDispatched(AssetDeletedFromMux::class);
});

it('bypasses the duplicate check when called by Mux id directly', function () {
    $this->addMirrorFieldToAssetBlueprint();

    $duplicate = $this->uploadTestFileToTestContainer('test.mp4', 'duplicate.mp4');

    MuxAsset::fromAsset($this->mp4)
        ->withId('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->save();
    MuxAsset::fromAsset($duplicate)
        ->withId('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->save();
    Stache::clear();

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2',
                'video_quality' => 'plus',
                'passthrough' => 'statamic::video.mp4',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->delete('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespond(Http::response(status: 204));

    $result = $this->deleteMuxAsset->handle('yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2');

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(2);
});

it('deletes orphaned Mux assets created by the addon', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'yvSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2',
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

it('deletes a fetched Mux asset without fetching it again', function () {
    $remoteAsset = new RemoteAsset([
        'id' => 'FETCHED-ID',
        'status' => RemoteAsset::STATUS_READY,
        'passthrough' => 'statamic::video.mp4',
    ]);

    $this->guzzler->expects($this->once())
        ->delete('https://api.mux.com/video/v1/assets/FETCHED-ID')
        ->willRespond(Http::response(status: 204));

    $result = $this->deleteMuxAsset->handle($remoteAsset);

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(1);
});

it('treats a missing asset during ownership lookup as deleted', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/MISSING-ID')
        ->willRespond(Http::response(status: 404));

    $result = $this->deleteMuxAsset->handle('MISSING-ID');

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(1);
});

it('treats a missing asset during final deletion as deleted', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/RACED-ID')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'RACED-ID',
                'passthrough' => 'statamic::video.mp4',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->delete('https://api.mux.com/video/v1/assets/RACED-ID')
        ->willRespond(Http::response(status: 404));

    $result = $this->deleteMuxAsset->handle('RACED-ID');

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(2);
});

it('evicts the listing cache whenever the asset ends up absent from Mux', function (string $muxId, array $responses, int $historyCount) {
    $cache = Mockery::mock(RemoteAssetCache::class);
    $cache->shouldReceive('forget')->once()->with($muxId);
    $this->app->instance(RemoteAssetCache::class, $cache);
    $action = $this->app->make(DeleteMuxAsset::class);

    foreach ($responses as $response) {
        $expectation = $this->guzzler->expects($this->once());
        $request = $response['method'] === 'get'
            ? $expectation->get("https://api.mux.com/video/v1/assets/{$muxId}")
            : $expectation->delete("https://api.mux.com/video/v1/assets/{$muxId}");

        isset($response['json'])
            ? $request->willRespondJson($response['json'])
            : $request->willRespond(Http::response(status: $response['status']));
    }

    expect($action->handle($muxId))->toBeTrue();
    $this->guzzler->assertHistoryCount($historyCount);
})->with([
    'deleted' => [
        'EVICT-DELETED',
        [
            ['method' => 'get', 'json' => ['data' => ['status' => 'ready', 'id' => 'EVICT-DELETED', 'passthrough' => 'statamic::video.mp4']]],
            ['method' => 'delete', 'status' => 204],
        ],
        2,
    ],
    'already absent' => [
        'EVICT-ABSENT',
        [['method' => 'get', 'status' => 404]],
        1,
    ],
    'deleted concurrently' => [
        'EVICT-RACED',
        [
            ['method' => 'get', 'json' => ['data' => ['status' => 'ready', 'id' => 'EVICT-RACED', 'passthrough' => 'statamic::video.mp4']]],
            ['method' => 'delete', 'status' => 404],
        ],
        2,
    ],
]);

it('keeps the listing cache for rejected and failed deletions', function (string $muxId, array $responses, bool $throws) {
    $cache = Mockery::mock(RemoteAssetCache::class);
    $cache->shouldNotReceive('forget');
    $this->app->instance(RemoteAssetCache::class, $cache);
    $action = $this->app->make(DeleteMuxAsset::class);

    foreach ($responses as $response) {
        $expectation = $this->guzzler->expects($this->once());
        $request = $response['method'] === 'get'
            ? $expectation->get("https://api.mux.com/video/v1/assets/{$muxId}")
            : $expectation->delete("https://api.mux.com/video/v1/assets/{$muxId}");

        isset($response['json'])
            ? $request->willRespondJson($response['json'])
            : $request->willRespond(Http::response(status: $response['status']));
    }

    $throws
        ? expect(fn () => $action->handle($muxId))->toThrow(ApiException::class)
        : expect($action->handle($muxId))->toBeFalse();
})->with([
    'not created by the addon' => [
        'KEEP-FOREIGN',
        [['method' => 'get', 'json' => ['data' => ['status' => 'ready', 'id' => 'KEEP-FOREIGN', 'passthrough' => 'example-passthrough']]]],
        false,
    ],
    'deletion failed' => [
        'KEEP-FAILED',
        [
            ['method' => 'get', 'json' => ['data' => ['status' => 'ready', 'id' => 'KEEP-FAILED', 'passthrough' => 'statamic::video.mp4']]],
            ['method' => 'delete', 'status' => 503],
        ],
        true,
    ],
]);

it('keeps the listing cache when a deletion is cancelled by a listener', function () {
    Event::listen(AssetDeletingFromMux::class, fn () => false);
    $cache = Mockery::mock(RemoteAssetCache::class);
    $cache->shouldNotReceive('forget');
    $this->app->instance(RemoteAssetCache::class, $cache);

    $this->addMirrorFieldToAssetBlueprint();
    MuxAsset::fromAsset($this->mp4)->withId('CANCELLED-ID')->save();

    expect($this->app->make(DeleteMuxAsset::class)->handle($this->mp4))->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
});

it('preserves non-404 Mux API exceptions', function () {
    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/assets/FAILED-ID')
        ->willRespondJson([
            'data' => [
                'status' => 'ready',
                'id' => 'FAILED-ID',
                'passthrough' => 'statamic::video.mp4',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->delete('https://api.mux.com/video/v1/assets/FAILED-ID')
        ->willRespond(Http::response(status: 503));

    expect(fn () => $this->deleteMuxAsset->handle('FAILED-ID'))
        ->toThrow(ApiException::class);
});
