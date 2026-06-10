<?php

use Daun\StatamicMux\Http\Controllers\Cp\CommandController;
use Daun\StatamicMux\Http\Controllers\Cp\ListingController;
use Daun\StatamicMux\Http\Controllers\Cp\ListingController as ApiListingController;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use MuxPhp\Api\AssetsApi;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset;
use MuxPhp\Models\PlaybackID;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Role;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'mux.mirror.enabled' => false,
    ]);

    $this->app->instance('statamic.hooks', collect());

    $this->superUser = User::make()->email('super@test.com')->makeSuper()->password('secret');
    $this->superUser->save();

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-001']]);
    $this->mp4->save();

    Stache::clear();

    // Mock the MuxService to avoid real API calls
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

    $assetsApi = Mockery::mock(AssetsApi::class);
    $assetsApi->shouldReceive('getAsset')->andReturnUsing(function (string $id) use ($remoteAsset) {
        if ($id !== 'mux-asset-001') {
            throw new ApiException('Not found', 404);
        }
        $response = Mockery::mock();
        $response->shouldReceive('getData')->andReturn($remoteAsset);

        return $response;
    });

    $muxApi = Mockery::mock(MuxApi::class);
    $muxApi->shouldReceive('assets')->andReturn($assetsApi);
    $muxApi->shouldReceive('getAsset')->andReturnUsing(function (string $id) use ($remoteAsset) {
        if ($id !== 'mux-asset-001') {
            return null;
        }

        return $remoteAsset;
    });
    $muxApi->shouldReceive('getAssets')->andReturnUsing(function (Collection|array $ids) use ($remoteAsset) {
        return collect($ids)
            ->filter(fn (string $id) => $id === 'mux-asset-001')
            ->mapWithKeys(fn (string $id) => [$id => $remoteAsset]);
    });
    $muxApi->shouldReceive('listAllAssets')->andReturn(collect([$remoteAsset]));
    $muxApi->shouldReceive('dashboardUrl')->andReturn('https://dashboard.mux.com/environments/env-001/');

    $muxService = Mockery::mock(MuxService::class);
    $muxService->shouldReceive('listMuxAssets')->with(0)->andReturn(collect([$remoteAsset]));
    $muxService->shouldReceive('api')->andReturn($muxApi);
    $this->app->instance(MuxApi::class, $muxApi);
    $this->app->instance('mux.api', $muxApi);
    $this->app->instance(MuxService::class, $muxService);
    $this->app->instance('mux.service', $muxService);

    Auth::guard()->login($this->superUser);
});

test('page controller returns mirrored assets view by default', function () {
    $controller = $this->app->make(ListingController::class);
    $response = $controller->index();

    $component = (fn () => $this->component)->call($response);
    $props = (fn () => $this->props)->call($response);

    expect($component)->toBe('MuxAssetsPage');
    expect($props)->toHaveKeys(['endpoint', 'commandEndpoint', 'assetEditorChunks']);
    expect($props)->not->toHaveKeys(['refreshEndpoint', 'dashboardUrl']);
});

test('page controller returns mux library view', function () {
    $controller = $this->app->make(ListingController::class);
    $response = $controller->library();

    $component = (fn () => $this->component)->call($response);
    $props = (fn () => $this->props)->call($response);

    expect($component)->toBe('MuxLibraryPage');
    expect($props)->toHaveKeys(['endpoint', 'refreshEndpoint', 'dashboardUrl']);
    expect($props)->not->toHaveKeys(['commandEndpoint', 'assetEditorChunks']);
});

test('page controller passes correct endpoints', function () {
    $controller = $this->app->make(ListingController::class);
    $mirrored = (fn () => $this->props)->call($controller->index());
    $library = (fn () => $this->props)->call($controller->library());

    expect($mirrored['endpoint'])->toContain('/mux/listing/local');
    expect($mirrored['commandEndpoint'])->toContain('/mux/command');
    expect($library['endpoint'])->toContain('/mux/listing/remote');
    expect($library['refreshEndpoint'])->toContain('/mux/listing/refresh');
});

test('local api returns json with data and meta', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET');
    $response = $controller->local($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['data', 'meta']);
    expect($json['meta'])->toHaveKeys(['current_page', 'per_page', 'total', 'last_page', 'columns']);
});

test('local api data has expected fields', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET');
    $response = $controller->local($request);
    $json = $response->getData(true);

    expect($json['data'])->not->toBeEmpty();
    $row = collect($json['data'])->firstWhere('mux_id', 'mux-asset-001');
    expect($row)->not->toBeNull();
    expect($row)->toHaveKeys(['id', 'title', 'path', 'edit_url', 'can_edit', 'mux_id', 'dashboard_url', 'has_mux_data', 'mirror_status', 'processing_status', 'duration', 'duration_formatted', 'playback_policy', 'playback_id', 'playback_ids']);
    expect($row['edit_url'])->toContain('/assets/');
    expect($row['can_edit'])->toBeTrue();
    expect($row['dashboard_url'])->toBe('https://dashboard.mux.com/environments/env-001/video/assets/mux-asset-001');
    expect($row['playback_id'])->toBe('playback-mux-asset-001');
});

test('remote api returns json with data and meta', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/remote', 'GET');
    $response = $controller->remote($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['data', 'meta']);
    expect($json['meta'])->toHaveKeys(['current_page', 'per_page', 'total', 'last_page', 'columns']);
});

test('remote api data has expected fields', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/remote', 'GET');
    $response = $controller->remote($request);
    $json = $response->getData(true);

    expect($json['data'])->not->toBeEmpty();
    $row = $json['data'][0];
    expect($row)->toHaveKeys(['id', 'title', 'mux_id', 'dashboard_url', 'match_status', 'processing_status', 'duration', 'duration_formatted', 'playback_policy', 'playback_id', 'playback_ids']);
    expect($row['dashboard_url'])->toBe('https://dashboard.mux.com/environments/env-001/video/assets/mux-asset-001');
    expect($row['playback_id'])->toBe('playback-mux-asset-001');
});

test('refresh endpoint returns success', function () {
    $controller = $this->app->make(ApiListingController::class);
    $response = $controller->refresh();

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['message', 'count']);
    expect($json['count'])->toBe(1);
});

test('command endpoint queues mirror command', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $controller = $this->app->make(CommandController::class);
    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'mirror']));
    $response = $controller->run();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(202);
    expect($response->getData(true))->toHaveKeys(['message', 'command', 'status']);
    expect($response->getData(true)['command'])->toBe('mirror');
    expect($response->getData(true)['status'])->toBe('dispatched');

    Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'mux:mirror');
});

test('command endpoint queues upload command', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $controller = $this->app->make(CommandController::class);
    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'upload']));
    $response = $controller->run();

    expect($response->getStatusCode())->toBe(202);

    Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'mux:upload');
});

test('command endpoint queues prune command', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $controller = $this->app->make(CommandController::class);
    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'prune']));
    $response = $controller->run();

    expect($response->getStatusCode())->toBe(202);

    Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'mux:prune');
});

test('command endpoint requires manage mux permission', function () {
    $user = User::make()->email('viewer@test.com')->password('secret');
    $user->save();

    $role = Role::make('mux-viewer')->title('Mux Viewer')->addPermission('view mux');
    $role->save();

    $user->assignRole('mux-viewer')->save();
    Auth::guard()->login($user);

    Queue::fake();

    $controller = $this->app->make(CommandController::class);

    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'mirror']));

    expect(fn () => $controller->run())->toThrow(HttpException::class);
    Queue::assertNothingPushed();
});

test('command endpoint rejects unknown commands', function () {
    Queue::fake();

    $controller = $this->app->make(CommandController::class);

    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'debug']));

    expect(fn () => $controller->run())->toThrow(HttpException::class);
    Queue::assertNothingPushed();
});

test('local api supports search parameter', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET', ['search' => 'test']);
    $response = $controller->local($request);

    expect($response->getStatusCode())->toBe(200);
});

test('local api supports pagination', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET', ['page' => 1, 'perPage' => 10]);
    $response = $controller->local($request);
    $json = $response->getData(true);

    expect($json['meta']['current_page'])->toBe(1);
    expect($json['meta']['per_page'])->toBe(10);
});

test('local api supports sorting', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET', ['sort' => 'title', 'order' => 'desc']);
    $response = $controller->local($request);

    expect($response->getStatusCode())->toBe(200);
});

test('remote api columns include match status', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/remote', 'GET');
    $response = $controller->remote($request);
    $json = $response->getData(true);
    $columns = collect($json['meta']['columns']);

    expect($columns->pluck('field')->toArray())->toContain('match_status');
});

test('local api columns include mirror status', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET');
    $response = $controller->local($request);
    $json = $response->getData(true);
    $columns = collect($json['meta']['columns']);

    expect($columns->pluck('field')->toArray())->toContain('mirror_status');
});

test('local api response excludes mux_asset', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET');
    $response = $controller->local($request);
    $json = $response->getData(true);

    foreach ($json['data'] as $row) {
        expect($row)->not->toHaveKey('mux_asset');
    }
});

test('remote api includes filter definitions', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/remote', 'GET');
    $response = $controller->remote($request);
    $json = $response->getData(true);

    expect($json['meta']['filters'])->not->toBeEmpty();
    $handles = collect($json['meta']['filters'])->pluck('handle')->toArray();
    expect($handles)->toContain('processing_status');
    expect($handles)->toContain('match_status');
    expect($handles)->toContain('resolution_tier');
    expect($handles)->toContain('is_test');
});

test('routes are registered', function () {
    expect(cp_route('mux.index'))->toContain('/mux');
    expect(cp_route('mux.mirrored'))->toContain('/mux/mirrored');
    expect(cp_route('mux.library'))->toContain('/mux/library');
    expect(cp_route('mux.listing.local'))->toContain('/mux/listing/local');
    expect(cp_route('mux.listing.remote'))->toContain('/mux/listing/remote');
    expect(cp_route('mux.listing.refresh'))->toContain('/mux/listing/refresh');
    expect(cp_route('mux.command'))->toContain('/mux/command');
});

test('control panel nav builds mux children', function () {
    $tools = Nav::build()->firstWhere('display', 'Tools');
    $mux = $tools['items']->firstWhere(fn ($item) => $item->display() === 'Mux');
    $children = $mux->resolveChildren()->children();

    expect($mux->url())->toBe(cp_route('mux.index'));
    expect($children)->toHaveCount(2);
    expect($children->map->display()->all())->toBe(['Mirrored Assets', 'Mux Library']);
    expect($children->map->url()->all())->toBe([cp_route('mux.index'), cp_route('mux.library')]);
});
