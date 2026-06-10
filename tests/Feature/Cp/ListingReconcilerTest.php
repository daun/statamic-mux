<?php

use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Mux\MuxApi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MuxPhp\Api\AssetsApi;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset;
use MuxPhp\Models\PlaybackID;
use Statamic\Facades\Stache;

beforeEach(function () {
    config(['mux.mirror.enabled' => false]);

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-001'], 'duration' => 120.5]);
    $this->mp4->save();

    $this->mp4b = $this->uploadTestFileToTestContainer('test.mp4', 'second.mp4');
    $this->mp4b->set('mux', ['id' => 'mux-asset-002', 'playback_ids' => ['public' => 'playback-002']]);
    $this->mp4b->save();

    $this->mp4c = $this->uploadTestFileToTestContainer('test.mp4', 'no-mux.mp4');

    Stache::clear();

    $this->remoteAssets = collect([
        makeRemoteAsset('mux-asset-001', 'ready', 120.5, 'My Video'),
        makeRemoteAsset('mux-asset-002', 'ready', 60.0),
        makeRemoteAsset('mux-asset-orphan', 'ready', 30.0, 'Orphaned Video'),
    ]);

    $this->remoteAssetsById = $this->remoteAssets->keyBy(fn ($a) => $a->getId());

    $assetsApi = Mockery::mock(AssetsApi::class);
    $assetsApi->shouldReceive('getAsset')->andReturnUsing(function (string $id) {
        $asset = $this->remoteAssetsById->get($id);
        if (! $asset) {
            throw new ApiException('Not found', 404);
        }
        $response = Mockery::mock();
        $response->shouldReceive('getData')->andReturn($asset);

        return $response;
    });

    $muxApi = Mockery::mock(MuxApi::class);
    $muxApi->shouldReceive('assets')->andReturn($assetsApi);
    $muxApi->shouldReceive('getAsset')->andReturnUsing(function (string $id) {
        return $this->remoteAssetsById->get($id);
    });
    $muxApi->shouldReceive('getAssets')->andReturnUsing(function (Collection|array $ids) {
        return collect($ids)
            ->mapWithKeys(fn (string $id) => [$id => $this->remoteAssetsById->get($id)])
            ->filter();
    });
    $muxApi->shouldReceive('listAllAssets')->andReturn($this->remoteAssets);
    $muxApi->shouldReceive('dashboardUrl')->andReturn('https://dashboard.mux.com/environments/env-001/');

    $this->app->instance(MuxApi::class, $muxApi);
    $this->app->instance('mux.api', $muxApi);

    $this->reconciler = new ListingReconciler($muxApi);
});

function makeRemoteAsset(string $id, string $status = 'ready', float $duration = 60.0, ?string $title = null): Asset
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('getId')->andReturn($id);
    $asset->shouldReceive('getStatus')->andReturn($status);
    $asset->shouldReceive('getDuration')->andReturn($duration);
    $asset->shouldReceive('getResolutionTier')->andReturn('1080p');
    $asset->shouldReceive('getMaxResolutionTier')->andReturn('1080p');
    $asset->shouldReceive('getTest')->andReturn(false);
    $asset->shouldReceive('getCreatedAt')->andReturn('1717200000');
    $asset->shouldReceive('getAspectRatio')->andReturn('16:9');

    $meta = null;
    if ($title) {
        $meta = Mockery::mock();
        $meta->shouldReceive('getTitle')->andReturn($title);
    } else {
        $meta = Mockery::mock();
        $meta->shouldReceive('getTitle')->andReturn(null);
    }
    $asset->shouldReceive('getMeta')->andReturn($meta);

    $playbackId = Mockery::mock(PlaybackID::class);
    $playbackId->shouldReceive('getId')->andReturn("playback-{$id}");
    $playbackId->shouldReceive('getPolicy')->andReturn('public');
    $asset->shouldReceive('getPlaybackIds')->andReturn([$playbackId]);
    $asset->shouldReceive('getPassthrough')->andReturn(null);

    return $asset;
}

test('builds local rows with remote state enrichment', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getLocalVideos();

    expect($result['data'])->toBeArray();
    expect($result['meta']['total'])->toBe(3);

    $rows = collect($result['data']);

    // Asset with matching remote: remote is authoritative, so its values win.
    $mirrored = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($mirrored)->not->toBeNull();
    expect($mirrored['has_mux_data'])->toBeTrue();
    expect($mirrored['exists_remotely'])->toBeTrue();
    expect($mirrored['mirror_status'])->toBe('uploaded');
    expect($mirrored['processing_status'])->toBe('ready');
    expect($mirrored['playback_id'])->toBe('playback-mux-asset-001');
    expect($mirrored['playback_ids'])->toBe([['id' => 'playback-mux-asset-001', 'policy' => 'public']]);

    // Asset without mux data
    $waiting = $rows->first(fn ($r) => ! $r['has_mux_data']);
    expect($waiting)->not->toBeNull();
    expect($waiting['mirror_status'])->toBe('not_uploaded');
    expect($waiting['processing_status'])->toBeNull();
});

test('falls back to local data when asset is gone from Mux', function () {
    // Asset with a Mux ID + cached duration that no longer exists remotely.
    $this->mp4b->set('mux', ['id' => 'mux-asset-gone', 'playback_ids' => ['public' => 'playback-gone'], 'duration' => 99.0]);
    $this->mp4b->save();
    Stache::clear();
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getLocalVideos();
    $rows = collect($result['data']);

    $stale = $rows->firstWhere('mux_id', 'mux-asset-gone');
    expect($stale)->not->toBeNull();

    // Still counts as uploaded (it has a Mux ID); status is binary.
    expect($stale['mirror_status'])->toBe('uploaded');
    expect($stale['exists_remotely'])->toBeFalse();

    // Remote-only fields are empty, not em-dashed.
    expect($stale['processing_status'])->toBeNull();
    expect($stale['playback_ids'])->toBe([]);
    expect($stale['playback_id'])->toBeNull();
    expect($stale['playback_policy'])->toBeNull();

    // Duration falls back to the locally cached value.
    expect($stale['duration'])->toBe(99.0);
    expect($stale['duration_formatted'])->not->toBeNull();

    // Created date falls back to the asset's own date.
    expect($stale['created_at'])->not->toBeNull();
});

test('builds remote rows with correct state badges', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos();

    expect($result['data'])->toBeArray();
    expect($result['meta']['total'])->toBe(3);

    $rows = collect($result['data']);

    // Mirrored: remote asset with exactly 1 local match
    $mirrored = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($mirrored['match_status'])->toBe('mirrored');
    expect($mirrored['playback_id'])->toBe('playback-mux-asset-001');
    expect($mirrored['playback_ids'])->toBe([['id' => 'playback-mux-asset-001', 'policy' => 'public']]);

    // Orphaned: remote asset with 0 local matches
    $orphaned = $rows->firstWhere('mux_id', 'mux-asset-orphan');
    expect($orphaned['match_status'])->toBe('orphaned');
    expect($orphaned['title'])->toBe('Orphaned Video');
});

test('detects duplicated remote references', function () {
    // Two local assets referencing same Mux ID
    $this->mp4b->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-dupe']]);
    $this->mp4b->save();
    Stache::clear();
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos();
    $rows = collect($result['data']);

    $duplicated = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($duplicated['match_status'])->toBe('duplicated');
    expect($duplicated['local_matches'])->toBe(2);
});

test('remote title falls back to mux id', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos();
    $rows = collect($result['data']);

    // Asset with title
    $withTitle = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($withTitle['title'])->toBe('My Video');

    // Asset without title
    $noTitle = $rows->firstWhere('mux_id', 'mux-asset-002');
    expect($noTitle['title'])->toBe('mux-asset-002');
});

test('caches remote assets for 10 minutes', function () {
    Cache::forget('mux.remote_assets');

    $this->reconciler->getCachedRemoteAssets();
    expect(Cache::has('mux.remote_assets'))->toBeTrue();

    $cached = Cache::get('mux.remote_assets');
    expect($cached)->toHaveCount(3);
});

test('refresh bypasses and replaces cache', function () {
    Cache::put('mux.remote_assets', collect(['stale-data']), 600);

    $result = $this->reconciler->refreshRemoteAssets();
    expect($result)->toHaveCount(3);
    expect(Cache::get('mux.remote_assets'))->toHaveCount(3);
});

test('search filters by title', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos(['search' => 'orphaned']);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['title'])->toBe('Orphaned Video');
});

test('search filters by mux id', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos(['search' => 'mux-asset-001']);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['mux_id'])->toBe('mux-asset-001');
});

test('pagination works correctly', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos(['perPage' => 2, 'page' => 1]);
    expect($result['data'])->toHaveCount(2);
    expect($result['meta']['total'])->toBe(3);
    expect($result['meta']['last_page'])->toBe(2);
    expect($result['meta']['current_page'])->toBe(1);

    $result = $this->reconciler->getRemoteVideos(['perPage' => 2, 'page' => 2]);
    expect($result['data'])->toHaveCount(1);
    expect($result['meta']['current_page'])->toBe(2);
});

test('sort by duration descending', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos(['sort' => 'duration', 'order' => 'desc']);
    $durations = collect($result['data'])->pluck('duration')->all();
    expect($durations)->toBe([120.5, 60.0, 30.0]);
});

test('filters remote by match status', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos([
        'filters' => [['field' => 'match_status', 'value' => 'orphaned']],
    ]);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['match_status'])->toBe('orphaned');
});

test('filters remote by processing status', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getRemoteVideos([
        'filters' => [['field' => 'processing_status', 'value' => 'ready']],
    ]);
    expect($result['meta']['total'])->toBe(3);
});

test('filters remote by duration range', function () {
    Cache::forget('mux.remote_assets');

    // short = <= 60 seconds, matches both 30.0 and 60.0
    $result = $this->reconciler->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'short']],
    ]);
    expect($result['meta']['total'])->toBe(2);

    // long = > 600 seconds, matches 120.5 (which is medium, actually)
    $result = $this->reconciler->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'long']],
    ]);
    expect($result['meta']['total'])->toBe(0);

    // medium = 60-600 seconds, matches 120.5
    $result = $this->reconciler->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'medium']],
    ]);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['duration'])->toBe(120.5);
});

test('local rows exclude mux_asset from response', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->reconciler->getLocalVideos();
    $rows = collect($result['data']);
    $rows->each(fn ($row) => expect($row)->not->toHaveKey('mux_asset'));
});
