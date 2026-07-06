<?php

use Daun\StatamicMux\Mux\Actions\UpdateMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);
    $this->service = Mockery::spy($this->app->make(MuxService::class))->makePartial();

    $this->action = $this->app->makeWith(UpdateMuxAsset::class, ['service' => $this->service]);

    $this->muxId = 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv';

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    $result = $this->action->handle($this->jpg);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores assets that do not exist on Mux', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $result = $this->action->handle($this->mp4);

    expect($result)->toBeFalse();
    $this->guzzler->assertHistoryCount(0);
});

it('updates the remote asset metadata', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $this->service->shouldReceive('getMuxId')->andReturn($this->muxId);

    $this->guzzler->expects($this->once())
        ->patch("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->withJson([
            'passthrough' => 'statamic::test_container_assets::test.mp4',
            'meta' => [
                'title' => 'test.mp4',
                'creator_id' => 'statamic-mux',
                'external_id' => 'test_container_assets::test.mp4',
            ],
        ])
        ->willRespondJson([
            'data' => [
                'id' => $this->muxId,
                'status' => 'ready',
                'passthrough' => 'statamic::test_container_assets::test.mp4',
            ],
        ]);

    $result = $this->action->handle($this->mp4);

    expect($result)->toBeTrue();
    $this->guzzler->assertHistoryCount(1);
});

it('rethrows a wrapped exception when the update fails', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $this->service->shouldReceive('getMuxId')->andReturn($this->muxId);

    $this->guzzler->expects($this->once())
        ->patch("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespond(Http::response('server error', 500));

    expect(fn () => $this->action->handle($this->mp4))
        ->toThrow(Exception::class, 'Failed to update asset data on Mux');
});
