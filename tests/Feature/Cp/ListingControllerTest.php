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
    $muxApi->shouldReceive('listAssets')->with(0)->andReturn(collect([$remoteAsset]));
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

test('page controller returns view', function () {
    $controller = $this->app->make(ListingController::class);
    $response = $controller->index();

    expect($response->getName())->toBe('statamic-mux::cp.videos.index');
    expect($response->getData())->toHaveKeys(['title', 'localEndpoint', 'remoteEndpoint', 'refreshEndpoint', 'commandEndpoint']);
});

test('page controller passes correct endpoints', function () {
    $controller = $this->app->make(ListingController::class);
    $response = $controller->index();
    $data = $response->getData();

    expect($data['localEndpoint'])->toContain('/mux/listing/local');
    expect($data['remoteEndpoint'])->toContain('/mux/listing/remote');
    expect($data['refreshEndpoint'])->toContain('/mux/listing/refresh');
    expect($data['commandEndpoint'])->toContain('/mux/command');
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
    expect($row)->toHaveKeys(['id', 'title', 'mux_id', 'has_mux_data', 'status', 'duration', 'playback_policy']);
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
    expect($row)->toHaveKeys(['id', 'title', 'mux_id', 'state', 'status', 'duration', 'playback_policy']);
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
    Queue::fake();

    $controller = $this->app->make(CommandController::class);
    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'mirror']));
    $response = $controller->run();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(202);
    expect($response->getData(true))->toHaveKeys(['message', 'command']);
    expect($response->getData(true)['command'])->toBe('mirror');

    Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'mux:mirror');
});

test('command endpoint queues upload command', function () {
    Queue::fake();

    $controller = $this->app->make(CommandController::class);
    $this->app->instance('request', Request::create('/mux/command', 'POST', ['command' => 'upload']));
    $response = $controller->run();

    expect($response->getStatusCode())->toBe(202);

    Queue::assertPushed(QueuedCommand::class, fn (QueuedCommand $job) => $job->displayName() === 'mux:upload');
});

test('command endpoint queues prune command', function () {
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

test('remote api columns include state', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/remote', 'GET');
    $response = $controller->remote($request);
    $json = $response->getData(true);
    $columns = collect($json['meta']['columns']);

    expect($columns->pluck('field')->toArray())->toContain('state');
});

test('local api columns include is_stale', function () {
    $controller = $this->app->make(ApiListingController::class);
    $request = Request::create('/mux/listing/local', 'GET');
    $response = $controller->local($request);
    $json = $response->getData(true);
    $columns = collect($json['meta']['columns']);

    expect($columns->pluck('field')->toArray())->toContain('is_stale');
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
    expect($handles)->toContain('status');
    expect($handles)->toContain('state');
    expect($handles)->toContain('resolution_tier');
    expect($handles)->toContain('is_test');
});

test('routes are registered', function () {
    expect(cp_route('mux.index'))->toContain('/mux');
    expect(cp_route('mux.listing.local'))->toContain('/mux/listing/local');
    expect(cp_route('mux.listing.remote'))->toContain('/mux/listing/remote');
    expect(cp_route('mux.listing.refresh'))->toContain('/mux/listing/refresh');
    expect(cp_route('mux.command'))->toContain('/mux/command');
});
