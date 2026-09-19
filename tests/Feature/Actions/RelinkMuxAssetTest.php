<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetRelinkedToMux;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Actions\RelinkMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use MuxPhp\Models\Asset as MuxApiAssetModel;
use Statamic\Facades\Asset as Assets;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);

    $this->action = $this->app->make(RelinkMuxAsset::class);

    $this->muxId = 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv';

    $this->remote = new MuxApiAssetModel([
        'id' => $this->muxId,
        'status' => 'ready',
        'duration' => 12.5,
    ]);

    $this->expectRemotePlaybackId = function (string $id = 'remotePublicPlaybackId') {
        $this->guzzler->expects($this->once())
            ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
            ->willRespondJson([
                'data' => [
                    'id' => $this->muxId,
                    'status' => 'ready',
                    'playback_ids' => [
                        ['policy' => 'public', 'id' => $id],
                    ],
                ],
            ]);
    };

    $this->storedMuxData = fn () => Assets::find($this->mp4->id())?->get('mux');

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');

    Stache::clear();
});

it('relinks an asset to an existing mux asset', function () {
    Event::fake([AssetRelinkedToMux::class, AssetUploadedToMux::class]);

    ($this->expectRemotePlaybackId)();

    $result = $this->action->handle($this->mp4, $this->remote);

    expect($result)->toBeTrue();

    $data = ($this->storedMuxData)();

    expect($data['id'])->toBe($this->muxId);
    expect($data['duration'])->toBe(12.5);
    expect($data['playback_ids'])->toBe(['public' => 'remotePublicPlaybackId']);
    expect($data)->not->toHaveKey('is_proxy');

    Event::assertDispatchedTimes(AssetRelinkedToMux::class, 1);
    Event::assertDispatched(AssetRelinkedToMux::class, function (AssetRelinkedToMux $event) {
        return $event->asset->id() === $this->mp4->id() && $event->muxId === $this->muxId;
    });
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('replaces stale local mux data when relinking', function () {
    $this->mp4->set('mux', [
        'id' => 'staleMuxId',
        'duration' => 99.0,
        'playback_ids' => ['public' => 'stalePlaybackId', 'signed' => 'staleSignedPlaybackId'],
        'is_proxy' => true,
        'custom' => 'value',
    ])->save();

    ($this->expectRemotePlaybackId)();

    $result = $this->action->handle($this->mp4, $this->remote);

    expect($result)->toBeTrue();

    $data = ($this->storedMuxData)();

    expect($data['id'])->toBe($this->muxId);
    expect($data['playback_ids'])->toBe(['public' => 'remotePublicPlaybackId']);
    expect($data)->not->toHaveKey('custom');
    expect($data)->not->toHaveKey('is_proxy');

    expect(MuxAsset::fromAsset(Assets::find($this->mp4->id()))->playbackId()?->id())
        ->toBe('remotePublicPlaybackId');
});

it('flags the asset as a proxy when relinking a proxy source', function () {
    ($this->expectRemotePlaybackId)();

    $result = $this->action->handle($this->mp4, $this->remote, proxySource: true);

    expect($result)->toBeTrue();
    expect(($this->storedMuxData)()['is_proxy'])->toBeTrue();
});

it('creates a playback id when the remote asset has none matching the policy', function () {
    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => ['id' => $this->muxId, 'status' => 'ready', 'playback_ids' => []],
        ]);

    $this->guzzler->expects($this->once())
        ->post("https://api.mux.com/video/v1/assets/{$this->muxId}/playback-ids")
        ->withJson(['policy' => 'public'])
        ->willRespondJson([
            'data' => ['policy' => 'public', 'id' => 'createdPlaybackId'],
        ]);

    $result = $this->action->handle($this->mp4, $this->remote);

    expect($result)->toBeTrue();
    expect(($this->storedMuxData)()['playback_ids'])->toBe(['public' => 'createdPlaybackId']);
});

it('preserves the local mux data and dispatches no event when saving fails', function () {
    Event::fake([AssetRelinkedToMux::class, AssetUploadedToMux::class]);

    $original = [
        'id' => 'previousMuxId',
        'duration' => 99.0,
        'playback_ids' => ['public' => 'previousPlaybackId'],
    ];

    $this->mp4->set('mux', $original)->save();

    ($this->expectRemotePlaybackId)();

    $asset = Mockery::mock($this->mp4)->makePartial();
    $failed = false;
    $asset->shouldReceive('saveQuietly')->andReturnUsing(function () use (&$failed) {
        if (! $failed) {
            $failed = true;

            throw new Exception('Disk is on fire');
        }

        return true;
    });

    $result = $this->action->handle($asset, $this->remote);

    expect($result)->toBeFalse();
    expect($asset->get('mux'))->toBe($original);
    expect(($this->storedMuxData)())->toBe($original);

    Event::assertNotDispatched(AssetRelinkedToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('leaves the local mux data untouched when the playback request fails', function () {
    Event::fake([AssetRelinkedToMux::class]);

    $original = ['id' => 'previousMuxId', 'playback_ids' => ['public' => 'previousPlaybackId']];
    $this->mp4->set('mux', $original)->save();

    $this->guzzler->expects($this->once())
        ->get("https://api.mux.com/video/v1/assets/{$this->muxId}")
        ->willRespondJson([
            'data' => ['id' => $this->muxId, 'status' => 'ready', 'playback_ids' => []],
        ]);

    $this->guzzler->expects($this->once())
        ->post("https://api.mux.com/video/v1/assets/{$this->muxId}/playback-ids")
        ->willRespond(Http::response('server error', 500));

    expect(fn () => $this->action->handle($this->mp4, $this->remote))
        ->toThrow(Exception::class, 'Error generating playback id for Mux asset');

    expect(($this->storedMuxData)())->toBe($original);

    Event::assertNotDispatched(AssetRelinkedToMux::class);
});

it('is exposed through the mux service', function () {
    ($this->expectRemotePlaybackId)();

    expect(Mux::relinkMuxAsset($this->mp4, $this->remote))->toBeTrue();
    expect(($this->storedMuxData)()['id'])->toBe($this->muxId);
});
