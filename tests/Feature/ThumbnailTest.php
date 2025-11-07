<?php

use Daun\StatamicMux\Thumbnails\ThumbnailService;

beforeEach(function () {
    $this->thumbnails = $this->app->make(ThumbnailService::class);
});

test('checks if enabled', function () {
    $thumbnails = $this->app->make(ThumbnailService::class);
    expect($thumbnails->enabled())->toBeTrue();
});

test('checks if disabled', function () {
    config(['mux.cp_thumbnails.enabled' => false]);
    $thumbnails = $this->app->make(ThumbnailService::class);
    expect($thumbnails->enabled())->toBeFalse();
});
