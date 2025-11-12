<?php

use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\MuxService;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());

    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);

    // $this->api = Mockery::spy($this->app->make(MuxApi::class))->makePartial();
    $this->service = Mockery::spy($this->app->make(MuxService::class))->makePartial();

    $this->createProxyVersion = Mockery::spy($this->app->makeWith(CreateProxyVersion::class, ['api' => $this->api, 'service' => $this->service]))
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->shortMp4 = $this->uploadTestFileToTestContainer('short.mp4');
    $this->m4a = $this->uploadTestFileToTestContainer('test.mp4', 'test.m4a');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    Stache::clear();
});

it('ignores non-video asset', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->jpg;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores non-mp4 videos', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->m4a;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores short videos', function () {
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->shortMp4;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('ignores videos without mux asset', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(false);
    $this->createProxyVersion->shouldNotReceive('createClipFromAsset');

    $asset = $this->mp4;
    expect($this->createProxyVersion->canHandle($asset))->toBeFalse();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBeNull();
    $this->guzzler->assertHistoryCount(0);
});

it('handles videos with mux asset', function () {
    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $this->createProxyVersion->shouldReceive('createClipFromAsset')->andReturn('new-mux-asset-id');

    $asset = $this->mp4;
    $asset->set('mux', ['id' => 123]);
    $asset->save();

    expect($this->createProxyVersion->canHandle($asset))->toBeTrue();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBe('new-mux-asset-id');
})->only();

it('creates a clip from existing mux asset', function () {
    $this->guzzler->expects($this->once())
        ->ray()
        ->post('https://api.mux.com/video/v1/assets')
        ->withJson([
            'input' => [
                'url' => 'mux://assets/123',
            ],
            'playback_policy' => [
                'public',
            ],
            'passthrough' => 'proxy::123',
            'normalize_audio' => false,
            'test' => false,
            'static_renditions' => [
                'resolution' => 'highest',
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
                'id' => 'vSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2y',
                'created_at' => '1607452572',
            ],
        ]);

    $this->service->shouldReceive('hasExistingMuxAsset')->andReturn(true);
    $this->api->shouldReceive('assetIsReady')->andReturn(true);

    $asset = $this->mp4;
    $asset->set('mux', ['id' => 123]);
    $asset->save();

    $result = $this->createProxyVersion->handle($asset);

    expect($result)->toBe('vSqQnqz6s5MBuXGvJaUWdXuXM93J9Q2y');
    $this->guzzler->assertHistoryCount(1);
});
