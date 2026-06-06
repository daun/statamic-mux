<?php

use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxVideoListingService;
use Illuminate\Support\Facades\Cache;
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

    $muxService = Mockery::mock(MuxService::class);
    $muxService->shouldReceive('listMuxAssets')->with(0)->andReturn($this->remoteAssets);
    $this->app->instance(MuxService::class, $muxService);
    $this->app->instance('mux.service', $muxService);

    $this->service = new MuxVideoListingService($muxService);
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

    return $asset;
}

test('builds local rows with remote state enrichment', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getLocalVideos();

    expect($result['data'])->toBeArray();
    expect($result['meta']['total'])->toBe(3);

    $rows = collect($result['data']);

    // Asset with matching remote
    $mirrored = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($mirrored)->not->toBeNull();
    expect($mirrored['has_mux_data'])->toBeTrue();
    expect($mirrored['exists_remotely'])->toBeTrue();
    expect($mirrored['is_stale'])->toBeFalse();
    expect($mirrored['status'])->toBe('ready');

    // Asset without mux data
    $waiting = $rows->first(fn ($r) => ! $r['has_mux_data']);
    expect($waiting)->not->toBeNull();
    expect($waiting['status'])->toBe('waiting');
    expect($waiting['is_stale'])->toBeFalse();
});

test('detects stale local assets', function () {
    // Set up asset with mux ID that doesn't exist remotely
    $this->mp4b->set('mux', ['id' => 'mux-asset-gone', 'playback_ids' => ['public' => 'playback-gone']]);
    $this->mp4b->save();
    Stache::clear();
    Cache::forget('mux.remote_assets');

    $result = $this->service->getLocalVideos();
    $rows = collect($result['data']);

    $stale = $rows->firstWhere('mux_id', 'mux-asset-gone');
    expect($stale)->not->toBeNull();
    expect($stale['is_stale'])->toBeTrue();
    expect($stale['status'])->toBe('stale');
});

test('builds remote rows with correct state badges', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos();

    expect($result['data'])->toBeArray();
    expect($result['meta']['total'])->toBe(3);

    $rows = collect($result['data']);

    // Mirrored: remote asset with exactly 1 local match
    $mirrored = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($mirrored['state'])->toBe('mirrored');

    // Orphaned: remote asset with 0 local matches
    $orphaned = $rows->firstWhere('mux_id', 'mux-asset-orphan');
    expect($orphaned['state'])->toBe('orphaned');
    expect($orphaned['title'])->toBe('Orphaned Video');
});

test('detects duplicated remote references', function () {
    // Two local assets referencing same Mux ID
    $this->mp4b->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-dupe']]);
    $this->mp4b->save();
    Stache::clear();
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos();
    $rows = collect($result['data']);

    $duplicated = $rows->firstWhere('mux_id', 'mux-asset-001');
    expect($duplicated['state'])->toBe('duplicated');
    expect($duplicated['local_matches'])->toBe(2);
});

test('remote title falls back to mux id', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos();
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

    $this->service->getCachedRemoteAssets();
    expect(Cache::has('mux.remote_assets'))->toBeTrue();

    $cached = Cache::get('mux.remote_assets');
    expect($cached)->toHaveCount(3);
});

test('refresh bypasses and replaces cache', function () {
    Cache::put('mux.remote_assets', collect(['stale-data']), 600);

    $result = $this->service->refreshRemoteAssets();
    expect($result)->toHaveCount(3);
    expect(Cache::get('mux.remote_assets'))->toHaveCount(3);
});

test('search filters by title', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos(['search' => 'orphaned']);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['title'])->toBe('Orphaned Video');
});

test('search filters by mux id', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos(['search' => 'mux-asset-001']);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['mux_id'])->toBe('mux-asset-001');
});

test('pagination works correctly', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos(['perPage' => 2, 'page' => 1]);
    expect($result['data'])->toHaveCount(2);
    expect($result['meta']['total'])->toBe(3);
    expect($result['meta']['last_page'])->toBe(2);
    expect($result['meta']['current_page'])->toBe(1);

    $result = $this->service->getRemoteVideos(['perPage' => 2, 'page' => 2]);
    expect($result['data'])->toHaveCount(1);
    expect($result['meta']['current_page'])->toBe(2);
});

test('sort by duration descending', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos(['sort' => 'duration', 'order' => 'desc']);
    $durations = collect($result['data'])->pluck('duration')->all();
    expect($durations)->toBe([120.5, 60.0, 30.0]);
});

test('filters remote by state', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos([
        'filters' => [['field' => 'state', 'value' => 'orphaned']],
    ]);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['state'])->toBe('orphaned');
});

test('filters remote by status', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getRemoteVideos([
        'filters' => [['field' => 'status', 'value' => 'ready']],
    ]);
    expect($result['meta']['total'])->toBe(3);
});

test('filters remote by duration range', function () {
    Cache::forget('mux.remote_assets');

    // short = <= 60 seconds, matches both 30.0 and 60.0
    $result = $this->service->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'short']],
    ]);
    expect($result['meta']['total'])->toBe(2);

    // long = > 600 seconds, matches 120.5 (which is medium, actually)
    $result = $this->service->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'long']],
    ]);
    expect($result['meta']['total'])->toBe(0);

    // medium = 60-600 seconds, matches 120.5
    $result = $this->service->getRemoteVideos([
        'filters' => [['field' => 'duration_range', 'value' => 'medium']],
    ]);
    expect($result['meta']['total'])->toBe(1);
    expect($result['data'][0]['duration'])->toBe(120.5);
});

test('filters local by state', function () {
    Cache::forget('mux.remote_assets');

    $result = $this->service->getLocalVideos([
        'filters' => [['field' => 'local_state', 'value' => 'waiting']],
    ]);
    $rows = collect($result['data']);
    $rows->each(fn ($row) => expect($row['has_mux_data'])->toBeFalse());
});
