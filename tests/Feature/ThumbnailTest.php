<?php

use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Support\Facades\Auth;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['statamic.assets.video_thumbnails' => false]);

    $this->app->instance('statamic.hooks', collect());

    $this->thumbnails = $this->app->make(ThumbnailService::class);

    $this->user = User::make()->email('foo@bar.com')->makeSuper()->password('secret');
    $this->user->save();
    Auth::guard()->login($this->user);

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');

    $this->mp4WithoutPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-mux.mp4');
    $this->mp4WithoutPlaybackId->set('mux', ['id' => 123]);
    $this->mp4WithoutPlaybackId->save();

    $this->mp4WithPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-playback.mp4');
    $this->mp4WithPlaybackId->set('mux', ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $this->mp4WithPlaybackId->save();

    $this->mp4WithSignedPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-signed.mp4');
    $this->mp4WithSignedPlaybackId->set('mux', ['id' => 123, 'playback_ids' => ['signed' => 'signed-playback-id']]);
    $this->mp4WithSignedPlaybackId->save();

    Stache::clear();
});

test('checks if enabled', function () {
    $thumbnails = $this->app->make(ThumbnailService::class);

    expect($thumbnails->enabled())->toBeTrue();
});

test('checks if disabled', function () {
    config(['mux.cp_thumbnails.enabled' => false]);

    expect($this->thumbnails->enabled())->toBeFalse();

    config(['mux.cp_thumbnails.enabled' => true]);
});

test('injects controller thumbnails for mux assets', function () {
    $this->thumbnails->createHooks();

    $asset = $this->mp4WithoutPlaybackId;
    $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;

    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toBe(cp_route('mux.thumbnail', base64_encode($asset->id())));
});

test('injects cdn thumbnails for mux assets with playback id', function () {
    $this->thumbnails->createHooks();

    $asset = $this->mp4WithPlaybackId;
    $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;

    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toStartWith('https://image.mux.com');
    expect($data['thumbnail'])->toEndWith('animated.webp?width=400');
});

test('injects static cdn thumbnails if configured', function () {
    config(['mux.cp_thumbnails.animated' => false]);

    $this->thumbnails->createHooks();

    $asset = $this->mp4WithPlaybackId;
    $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;

    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toStartWith('https://image.mux.com');
    expect($data['thumbnail'])->toEndWith('thumbnail.webp?width=400');
});

test('does not crash asset browser hook when signing keys missing for signed playback id', function () {
    config(['mux.playback_policy' => 'signed']);
    config(['mux.signing_key.key_id' => null]);
    config(['mux.signing_key.private_key' => null]);

    $this->thumbnails->createHooks();

    $asset = $this->mp4WithSignedPlaybackId;

    $data = null;
    expect(function () use (&$data, $asset) {
        $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;
    })->not->toThrow(Exception::class);

    // Degrades to the default thumbnail instead of a Mux url
    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toBeNull();
});

test('does not inject thumbnails for non-mux assets', function () {
    $this->thumbnails->createHooks();

    $asset = $this->mp4;
    $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;

    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toBeNull();
});

test('does not inject thumbnails if disabled', function () {
    config(['mux.cp_thumbnails.enabled' => false]);

    $this->thumbnails->createHooks();

    $asset = $this->mp4WithPlaybackId;
    $data = $this->app->makeWith(AssetResource::class, ['resource' => $asset])->resolve()['data'] ?? null;

    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data['thumbnail'])->toBeNull();
});
