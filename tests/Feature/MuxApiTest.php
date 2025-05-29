<?php

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\DeliveryUsageApi;
use MuxPhp\Api\DirectUploadsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\Api\PlaybackIDApi;
use MuxPhp\Api\URLSigningKeysApi;

beforeEach(function () {
    $this->api = $this->app->make(MuxApi::class);
});

test('creates config from constructor arguments', function () {
    $client = new Client;
    $api = new MuxApi($client, 'token-id', 'token-secret');

    expect($api->client())->toBe($client);
    expect($api->config()->getUsername())->toEqual('token-id');
    expect($api->config()->getPassword())->toEqual('token-secret');
    expect($api->config()->getDebug())->toBeFalse();

    $api = new MuxApi($client, 'token-id-2', 'token-secret-2', debug: true);
    expect($api->config()->getUsername())->toEqual('token-id-2');
    expect($api->config()->getPassword())->toEqual('token-secret-2');
    expect($api->config()->getDebug())->toBeTrue();
    expect($api->assets()->getConfig()->getDebug())->toBeTrue();
});

test('returns a configured AssetsApi instance', function () {
    expect($this->api->assets())->toBeInstanceOf(AssetsApi::class);
    expect($this->api->assets()->getConfig())->toBe($this->api->config());
});

test('returns a configured DirectUploadsApi instance', function () {
    expect($this->api->directUploads())->toBeInstanceOf(DirectUploadsApi::class);
    expect($this->api->directUploads()->getConfig())->toBe($this->api->config());
});

test('returns a configured LiveStreamsApi instance', function () {
    expect($this->api->liveStreams())->toBeInstanceOf(LiveStreamsApi::class);
    expect($this->api->liveStreams()->getConfig())->toBe($this->api->config());
});

test('returns a configured URLSigningKeysApi instance', function () {
    expect($this->api->urlSigningKeys())->toBeInstanceOf(URLSigningKeysApi::class);
    expect($this->api->urlSigningKeys()->getConfig())->toBe($this->api->config());
});

test('returns a configured PlaybackIDApi instance', function () {
    expect($this->api->playbackIDs())->toBeInstanceOf(PlaybackIDApi::class);
    expect($this->api->playbackIDs()->getConfig())->toBe($this->api->config());
});

test('returns a configured DeliveryUsageApi instance', function () {
    expect($this->api->deliveryUsage())->toBeInstanceOf(DeliveryUsageApi::class);
    expect($this->api->deliveryUsage()->getConfig())->toBe($this->api->config());
});

test('sends API request to create asset', function () {
    $requestBody = file_get_contents(fixtures_path('mux/asset-create-request.json'));
    $responseBody = file_get_contents(fixtures_path('mux/asset-create-response.json'));

    $this->app->bind(MuxClient::class, fn() => $this->guzzler->getClient());

    $this->guzzler->expects($this->once())
        ->post('https://api.mux.com/video/v1/assets')
        ->withBody($requestBody)
        ->willRespond(Http::response($responseBody, 200));

    $assetRequest = $this->api->createAssetRequest([
        'input' => $this->api->input(['url' => 'https://example.com/video.mp4']),
        'passthrough' => '1234',
    ]);

    $muxAsset = $this->api->assets()->createAsset($assetRequest)->getData();

    expect($muxAsset->getId())->toBe('SqQnqz6s5MBuXGvJaUWdXuXM93J9Q2yv');
});
