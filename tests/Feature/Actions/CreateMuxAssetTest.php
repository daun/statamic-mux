<?php

use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);
    $this->service = Mockery::spy($this->app->make(MuxService::class))->makePartial();

    $this->createMuxAsset = Mockery::spy($this->app->makeWith(CreateMuxAsset::class, ['service' => $this->service]))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->jpg);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('ignores existing mux asset', function () {
    Event::fake([AssetUploadingToMux::class, AssetUploadedToMux::class]);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);

    $this->createMuxAsset->shouldNotReceive('uploadAssetToMux');
    $this->createMuxAsset->shouldNotReceive('ingestAssetToMux');

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetUploadingToMux::class);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('handles cancelled uploading event', function () {
    Event::fake([AssetUploadedToMux::class]);
    Event::listen(AssetUploadingToMux::class, fn () => false);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
    Event::assertNotDispatched(AssetUploadedToMux::class);
});

it('ingests assets from public containers', function () {
    Event::fake([AssetUploadedToMux::class]);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->guzzler->expects($this->once())
        ->ray()
        ->post('https://api.mux.com/video/v1/assets')
        ->withJson([
            'input' => [
                'url' => 'http://localhost/assets/assets/test.mp4',
            ],
            'playback_policy' => [
                'public',
            ],
            'passthrough' => 'statamic::test_container_assets::test.mp4',
            'normalize_audio' => false,
            'test' => false,
            'video_quality' => 'plus',
            'meta' => [
                'title' => 'test.mp4',
                'creator_id' => 'statamic-mux',
                'external_id' => 'test_container_assets::test.mp4',
            ],
        ])
        ->willRespondJson([
            'data' => [
                'status' => 'preparing',
                'playback_ids' => [
                    [
                        'policy' => 'public',
                        'id' => 'uNbxnGLKJ00yfbijDO8COxTOyVKT01xpxW',
                    ],
                ],
                'id' => 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv',
                'created_at' => '1607452572',
            ],
        ]);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv');
    $this->guzzler->assertHistoryCount(1);
    Event::assertDispatched(AssetUploadedToMux::class);
});

it('uploads assets from private containers', function () {
    Event::fake([AssetUploadedToMux::class]);

    $privateMp4 = $this->uploadTestFileToTestContainer('test.mp4', filename: 'private.mp4', container: 'private');

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->guzzler->expects($this->once())
        ->post('https://api.mux.com/video/v1/uploads')
        ->withJson([
            'timeout' => 3600,
            'cors_origin' => '*',
            'new_asset_settings' => [
                'playback_policy' => [
                    'public',
                ],
                'passthrough' => 'statamic::test_container_private::private.mp4',
                'normalize_audio' => false,
                'test' => false,
                'video_quality' => 'plus',
                'meta' => [
                    'title' => 'private.mp4',
                    'creator_id' => 'statamic-mux',
                    'external_id' => 'test_container_private::private.mp4',
                ],
            ],
            'test' => false,
        ])
        ->willRespondJson([
            'data' => [
                'url' => 'https://storage.googleapis.com/video-storage-us-east1-uploads/zd01Pe2bNpYhxbrw',
                'timeout' => 3600,
                'status' => 'waiting',
                'new_asset_settings' => [
                    'playback_policies' => [
                        'public',
                    ],
                    'video_quality' => 'plus',
                ],
                'id' => 'zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->put('https://storage.googleapis.com/video-storage-us-east1-uploads/zd01Pe2bNpYhxbrw')
        ->withHeaders(['Content-Type' => 'application/octet-stream'])
        ->withBody($privateMp4->contents())
        ->willRespond(Http::response('', 200));

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/uploads/zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc')
        ->willRespondJson([
            'data' => [
                'status' => 'asset_created',
                'id' => 'zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc',
                'asset_id' => '123456789',
            ],
        ]);

    $result = $this->createMuxAsset->handle($privateMp4);

    expect($result)->toBe('123456789');
    $this->guzzler->assertHistoryCount(3);
    Event::assertDispatched(AssetUploadedToMux::class);
});

it('uploads assets from local environment', function () {
    Event::fake([AssetUploadedToMux::class]);

    $this->app['env'] = 'local';

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    $this->guzzler->expects($this->once())
        ->post('https://api.mux.com/video/v1/uploads')
        ->withJson([
            'timeout' => 3600,
            'cors_origin' => '*',
            'new_asset_settings' => [
                'playback_policy' => [
                    'public',
                ],
                'passthrough' => 'statamic::test_container_assets::test.mp4',
                'normalize_audio' => false,
                'test' => false,
                'video_quality' => 'plus',
                'meta' => [
                    'title' => 'test.mp4',
                    'creator_id' => 'statamic-mux',
                    'external_id' => 'test_container_assets::test.mp4',
                ],
            ],
            'test' => false,
        ])
        ->willRespondJson([
            'data' => [
                'url' => 'https://storage.googleapis.com/video-storage-us-east1-uploads/zd01Pe2bNpYhxbrw',
                'timeout' => 3600,
                'status' => 'waiting',
                'new_asset_settings' => [
                    'playback_policies' => [
                        'public',
                    ],
                    'video_quality' => 'plus',
                ],
                'id' => 'zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc',
            ],
        ]);

    $this->guzzler->expects($this->once())
        ->put('https://storage.googleapis.com/video-storage-us-east1-uploads/zd01Pe2bNpYhxbrw')
        ->withHeaders(['Content-Type' => 'application/octet-stream'])
        ->withBody($this->mp4->contents())
        ->willRespond(Http::response('', 200));

    $this->guzzler->expects($this->once())
        ->get('https://api.mux.com/video/v1/uploads/zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc')
        ->willRespondJson([
            'data' => [
                'status' => 'asset_created',
                'id' => 'zd01Pe2bNpYhxbrwYABgFE01twZdtv4M00kts2i02GhbGjc',
                'asset_id' => '123456789',
            ],
        ]);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('123456789');
    Event::assertDispatched(AssetUploadedToMux::class);
});

it('allows modifying asset settings via hook', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    Mux::hook('asset-settings', function ($payload, $next) {
        expect($payload->settings)->toBeArray();
        expect($payload->asset)->toBeInstanceOf(Asset::class);

        $payload->settings['video_quality'] = 'very_bad';
        $payload->settings['test'] = true;
        $payload->settings['passthrough'] = 'cannot::be::overridden::by::hook';

        return $next($payload);
    });

    $this->guzzler->expects($this->once())
        ->post('https://api.mux.com/video/v1/assets')
        ->withJson([
            'input' => [
                'url' => 'http://localhost/assets/assets/test.mp4',
            ],
            'playback_policy' => [
                'public',
            ],
            'passthrough' => 'statamic::test_container_assets::test.mp4',
            'normalize_audio' => false,
            'test' => true,
            'video_quality' => 'very_bad',
            'meta' => [
                'title' => 'test.mp4',
                'creator_id' => 'statamic-mux',
                'external_id' => 'test_container_assets::test.mp4',
            ],
        ])
        ->willRespondJson([
            'data' => [
                'status' => 'preparing',
                'playback_ids' => [
                    [
                        'policy' => 'public',
                        'id' => 'uNbxnGLKJ00yfbijDO8COxTOyVKT01xpxW',
                    ],
                ],
                'id' => 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv',
                'created_at' => '1607452572',
            ],
        ]);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv');
});

it('allows modifying asset metadata via hook', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);

    Mux::hook('asset-meta', function ($payload, $next) {
        expect($payload->meta)->toBeArray();
        expect($payload->asset)->toBeInstanceOf(Asset::class);

        $payload->meta = [
            'title' => 'Lorem ipsum',
            'creator_id' => '123',
            'external_id' => '456',
        ];

        return $next($payload);
    });

    $this->guzzler->expects($this->once())
        ->post('https://api.mux.com/video/v1/assets')
        ->withJson([
            'input' => [
                'url' => 'http://localhost/assets/assets/test.mp4',
            ],
            'playback_policy' => [
                'public',
            ],
            'passthrough' => 'statamic::test_container_assets::test.mp4',
            'normalize_audio' => false,
            'test' => false,
            'video_quality' => 'plus',
            'meta' => [
                'title' => 'Lorem ipsum',
                'creator_id' => '123',
                'external_id' => '456',
            ],
        ])
        ->willRespondJson([
            'data' => [
                'status' => 'preparing',
                'playback_ids' => [
                    [
                        'policy' => 'public',
                        'id' => 'uNbxnGLKJ00yfbijDO8COxTOyVKT01xpxW',
                    ],
                ],
                'id' => 'JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv',
                'created_at' => '1607452572',
            ],
        ]);

    $result = $this->createMuxAsset->handle($this->mp4);

    expect($result)->toBe('JaUWdXuXM93J9Q2yvSqQnqz6s5MBuXGv');
});
