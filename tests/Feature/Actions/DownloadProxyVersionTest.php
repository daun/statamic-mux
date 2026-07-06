<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Http;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Models\Asset as MuxApiAsset;
use MuxPhp\Models\AssetResponse;
use MuxPhp\Models\AssetStaticRenditions;
use MuxPhp\Models\PlaybackID;
use MuxPhp\Models\StaticRendition;
use Statamic\Facades\Stache;

function muxOriginalAsset(): MuxApiAsset
{
    return new MuxApiAsset(['id' => 'originalMuxAsset0000000000000000', 'duration' => 42.5]);
}

function muxProxyAsset(?string $playbackId = 'proxyPlaybackId', ?string $rendition = 'highest.mp4'): MuxApiAsset
{
    return new MuxApiAsset([
        'id' => 'proxyMuxAsset000000000000000000',
        'playback_ids' => $playbackId ? [new PlaybackID(['id' => $playbackId, 'policy' => 'public'])] : [],
        'static_renditions' => new AssetStaticRenditions([
            'status' => 'ready',
            'files' => $rendition ? [new StaticRendition(['name' => $rendition, 'status' => 'ready'])] : [],
        ]),
    ]);
}

beforeEach(function () {
    $this->muxId = 'originalMuxAsset0000000000000000';
    $this->proxyId = 'proxyMuxAsset000000000000000000';

    $this->api = Mockery::mock(MuxApi::class);
    $this->api->shouldReceive('assetExists')->andReturn(true);
    $this->api->shouldReceive('assetIsReady')->andReturn(true);
    $this->api->shouldReceive('assetRenditionsAreReady')->andReturn(true);

    $this->service = Mockery::mock(MuxService::class);
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $this->service->shouldReceive('getMuxId')->andReturn($this->muxId);

    $this->useProxyAsset = function (MuxApiAsset $proxyAsset) {
        $assetsApi = Mockery::mock(AssetsApi::class);
        $assetsApi->shouldReceive('getAsset')->with($this->muxId)
            ->andReturn(new AssetResponse(['data' => muxOriginalAsset()]));
        $assetsApi->shouldReceive('getAsset')->with($this->proxyId)
            ->andReturn(new AssetResponse(['data' => $proxyAsset]));
        $this->api->shouldReceive('assets')->andReturn($assetsApi);
    };

    $this->action = $this->app->makeWith(DownloadProxyVersion::class, [
        'api' => $this->api,
        'service' => $this->service,
    ]);

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => $this->muxId])->save();

    $this->originalContents = $this->mp4->disk()->get($this->mp4->path());

    Stache::clear();
});

it('replaces the original file with a plausible proxy rendition', function () {
    ($this->useProxyAsset)(muxProxyAsset());

    $downloadBytes = $this->getTestFileContents('short.mp4');
    Http::fake(['stream.mux.com/*' => Http::response($downloadBytes, 200, ['Content-Type' => 'video/mp4'])]);

    $result = $this->action->handle($this->mp4, $this->proxyId);

    expect($result)->toBeTrue();
    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($downloadBytes);
    expect($this->mp4->disk()->get($this->mp4->path()))->not->toBe($this->originalContents);

    $muxAsset = MuxAsset::fromAsset($this->mp4);
    expect($muxAsset->isProxy())->toBeTrue();
    expect($muxAsset->duration())->toBe(42.5);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://stream.mux.com/proxyPlaybackId/highest.mp4'));
});

it('does not touch the original file on an error status', function (int $status) {
    ($this->useProxyAsset)(muxProxyAsset());
    Http::fake(['stream.mux.com/*' => Http::response('upstream error', $status)]);

    expect(fn () => $this->action->handle($this->mp4, $this->proxyId))
        ->toThrow(Exception::class);

    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($this->originalContents);
    expect(MuxAsset::fromAsset($this->mp4)->isProxy())->toBeFalse();
})->with([500, 404]);

it('does not touch the original file on an empty response body', function () {
    ($this->useProxyAsset)(muxProxyAsset());
    Http::fake(['stream.mux.com/*' => Http::response('', 200, ['Content-Type' => 'video/mp4'])]);

    expect(fn () => $this->action->handle($this->mp4, $this->proxyId))
        ->toThrow(Exception::class);

    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($this->originalContents);
    expect(MuxAsset::fromAsset($this->mp4)->isProxy())->toBeFalse();
});

it('does not touch the original file on a non-video (JSON) response body', function () {
    ($this->useProxyAsset)(muxProxyAsset());

    $body = json_encode(['error' => ['type' => 'invalid_parameters', 'messages' => [str_repeat('x', 2000)]]]);
    Http::fake(['stream.mux.com/*' => Http::response($body, 200, ['Content-Type' => 'application/json'])]);

    expect(fn () => $this->action->handle($this->mp4, $this->proxyId))
        ->toThrow(Exception::class);

    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($this->originalContents);
    expect(MuxAsset::fromAsset($this->mp4)->isProxy())->toBeFalse();
});

it('bails cleanly when the proxy has no playback id', function () {
    ($this->useProxyAsset)(muxProxyAsset(playbackId: null));
    Http::fake();

    $result = $this->action->handle($this->mp4, $this->proxyId);

    expect($result)->toBeFalse();
    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($this->originalContents);
    expect(MuxAsset::fromAsset($this->mp4)->isProxy())->toBeFalse();
    Http::assertNothingSent();
});

it('bails cleanly when the proxy has no static rendition', function () {
    ($this->useProxyAsset)(muxProxyAsset(rendition: null));
    Http::fake();

    $result = $this->action->handle($this->mp4, $this->proxyId);

    expect($result)->toBeFalse();
    expect($this->mp4->disk()->get($this->mp4->path()))->toBe($this->originalContents);
    expect(MuxAsset::fromAsset($this->mp4)->isProxy())->toBeFalse();
    Http::assertNothingSent();
});
