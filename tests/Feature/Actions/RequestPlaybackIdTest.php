<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Actions\RequestPlaybackId;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);

    $this->action = $this->app->make(RequestPlaybackId::class);

    $this->muxId = 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv';

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('returns null for a non-video asset', function () {
    $result = $this->action->handle($this->jpg, MuxPlaybackPolicy::Public);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('returns null for a video asset without a mux id', function () {
    $result = $this->action->handle($this->mp4, MuxPlaybackPolicy::Public);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('reuses an existing playback id with a matching policy', function () {
    $this->mp4->set('mux', ['id' => $this->muxId])->save();

    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => [
                'id' => $this->muxId,
                'status' => 'ready',
                'playback_ids' => [
                    ['policy' => 'public', 'id' => 'existingPublicPlaybackId'],
                ],
            ],
        ]);

    $result = $this->action->handle($this->mp4, MuxPlaybackPolicy::Public);

    expect($result->id())->toBe('existingPublicPlaybackId');
    expect($result->isPublic())->toBeTrue();

    $this->guzzler->assertHistoryCount(1);

    expect(MuxAsset::fromAsset($this->mp4)->playbackId(MuxPlaybackPolicy::Public)?->id())
        ->toBe('existingPublicPlaybackId');
});

it('creates a new playback id when the mux asset has none', function () {
    $this->mp4->set('mux', ['id' => $this->muxId])->save();

    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => [
                'id' => $this->muxId,
                'status' => 'ready',
                'playback_ids' => [],
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->post("https://api.mux.com/video/v1/assets/{$this->muxId}/playback-ids")
        ->withJson(['policy' => 'public'])
        ->willRespondJson([
            'data' => ['policy' => 'public', 'id' => 'newlyCreatedPlaybackId'],
        ]);

    $result = $this->action->handle($this->mp4, MuxPlaybackPolicy::Public);

    expect($result->id())->toBe('newlyCreatedPlaybackId');
    $this->guzzler->assertHistoryCount(2);

    expect(MuxAsset::fromAsset($this->mp4)->playbackId(MuxPlaybackPolicy::Public)?->id())
        ->toBe('newlyCreatedPlaybackId');
});

it('creates a new playback id when no existing one matches the requested policy', function () {
    $this->mp4->set('mux', ['id' => $this->muxId])->save();

    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => [
                'id' => $this->muxId,
                'status' => 'ready',
                'playback_ids' => [
                    ['policy' => 'signed', 'id' => 'existingSignedPlaybackId'],
                ],
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->post("https://api.mux.com/video/v1/assets/{$this->muxId}/playback-ids")
        ->withJson(['policy' => 'public'])
        ->willRespondJson([
            'data' => ['policy' => 'public', 'id' => 'newlyCreatedPublicPlaybackId'],
        ]);

    $result = $this->action->handle($this->mp4, MuxPlaybackPolicy::Public);

    expect($result->id())->toBe('newlyCreatedPublicPlaybackId');
    expect($result->isPublic())->toBeTrue();
    $this->guzzler->assertHistoryCount(2);
});

it('rethrows a wrapped exception when creating a playback id fails', function () {
    $this->mp4->set('mux', ['id' => $this->muxId])->save();

    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => [
                'id' => $this->muxId,
                'status' => 'ready',
                'playback_ids' => [],
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->post("https://api.mux.com/video/v1/assets/{$this->muxId}/playback-ids")
        ->willRespond(Http::response('server error', 500));

    expect(fn () => $this->action->handle($this->mp4, MuxPlaybackPolicy::Public))
        ->toThrow(Exception::class, 'Error generating playback id for Mux asset');
});
