<?php

use Daun\StatamicMux\Http\Controllers\Cp\ThumbnailController;
use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Support\Facades\Auth;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config([
        'statamic.assets.video_thumbnails' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    $this->app->instance('statamic.hooks', collect());

    $this->user = User::make()->email('foo@bar.com')->makeSuper()->password('secret');
    $this->user->save();
    Auth::guard()->login($this->user);

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');

    $this->mp4WithPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-playback.mp4');
    $this->mp4WithPlaybackId->set('mux', ['id' => 'mux-asset-123', 'playback_ids' => ['public' => 'playback-456']]);
    $this->mp4WithPlaybackId->save();

    $this->mp4WithoutPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-mux.mp4');
    $this->mp4WithoutPlaybackId->set('mux', ['id' => 'mux-asset-789']);
    $this->mp4WithoutPlaybackId->save();

    $this->mp4WithSignedPlaybackId = $this->uploadTestFileToTestContainer('test.mp4', 'test-signed.mp4');
    $this->mp4WithSignedPlaybackId->set('mux', ['id' => 'mux-asset-987', 'playback_ids' => ['signed' => 'signed-playback-id']]);
    $this->mp4WithSignedPlaybackId->save();

    Stache::clear();
});

test('redirects to thumbnail url for asset with playback id', function () {
    $asset = $this->mp4WithPlaybackId;
    $id = base64_encode($asset->id());

    $response = $this->app->make(ThumbnailController::class)->thumbnail($id);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getTargetUrl())->toStartWith('https://image.mux.com');
    expect($response->getTargetUrl())->toContain('playback-456');
});

test('redirects to animated thumbnail by default', function () {
    $asset = $this->mp4WithPlaybackId;
    $id = base64_encode($asset->id());

    $response = $this->app->make(ThumbnailController::class)->thumbnail($id);

    expect($response->getTargetUrl())->toEndWith('animated.webp?width=400');
});

test('redirects to static thumbnail when animated is disabled', function () {
    config(['mux.cp_thumbnails.animated' => false]);

    $asset = $this->mp4WithPlaybackId;
    $id = base64_encode($asset->id());

    $response = $this->app->make(ThumbnailController::class)->thumbnail($id);

    expect($response->getTargetUrl())->toEndWith('thumbnail.webp?width=400');
});

test('returns 404 for non-existent asset', function () {
    $id = base64_encode('test_container_assets::nonexistent.mp4');

    $this->app->make(ThumbnailController::class)->thumbnail($id);
})->throws(NotFoundHttpException::class);

test('returns 404 for invalid base64 id', function () {
    $this->app->make(ThumbnailController::class)->thumbnail('!!!invalid!!!');
})->throws(Exception::class);

test('returns 404 for asset without mux data', function () {
    $asset = $this->mp4;
    $id = base64_encode($asset->id());

    $this->app->make(ThumbnailController::class)->thumbnail($id);
})->throws(NotFoundHttpException::class);

test('returns 404 when thumbnail service returns null', function () {
    $service = Mockery::mock(ThumbnailService::class);
    $service->shouldReceive('generateForAsset')->once()->andReturnNull();
    $this->app->instance(ThumbnailService::class, $service);

    $asset = $this->mp4WithPlaybackId;
    $id = base64_encode($asset->id());

    $this->app->make(ThumbnailController::class)->thumbnail($id);
})->throws(NotFoundHttpException::class);

test('returns 404 for empty id', function () {
    $this->app->make(ThumbnailController::class)->thumbnail(base64_encode(''));
})->throws(Exception::class);

test('calls thumbnail service with correct asset', function () {
    $service = Mockery::mock(ThumbnailService::class);
    $service->shouldReceive('generateForAsset')
        ->once()
        ->withArgs(fn ($asset) => $asset->id() === $this->mp4WithPlaybackId->id())
        ->andReturn('https://image.mux.com/playback-456/thumbnail.webp');
    $this->app->instance(ThumbnailService::class, $service);

    $asset = $this->mp4WithPlaybackId;
    $id = base64_encode($asset->id());

    $response = $this->app->make(ThumbnailController::class)->thumbnail($id);

    expect($response->getTargetUrl())->toBe('https://image.mux.com/playback-456/thumbnail.webp');
});

test('returns 404 instead of crashing when signing keys missing for signed playback id', function () {
    config(['mux.playback_policy' => 'signed']);
    config(['mux.signing_key.key_id' => null]);
    config(['mux.signing_key.private_key' => null]);

    $asset = $this->mp4WithSignedPlaybackId;
    $id = base64_encode($asset->id());

    $this->app->make(ThumbnailController::class)->thumbnail($id);
})->throws(NotFoundHttpException::class);

test('thumbnail route is registered', function () {
    $route = cp_route('mux.thumbnail', 'test-id');

    expect($route)->toContain('/mux/thumbnail/test-id');
});
