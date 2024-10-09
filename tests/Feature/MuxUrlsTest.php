<?php

use Carbon\Carbon;
use Daun\StatamicMux\Mux\Enums\MuxAudience;
use Daun\StatamicMux\Mux\MuxUrls;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow('2021-01-01 00:00:00');
    JWT::$timestamp = Carbon::now()->timestamp;

    $this->urls = $this->app->make(MuxUrls::class);

    $this->keyId = 'key-id';
    $this->privateKey = 'private-key';
});

test('converts expiration to timestamp', function () {
    expect($this->urls->timestamp())->toBeInt();

    expect($this->urls->timestamp('1 day'))->toBe(Carbon::now()->add('1 day')->timestamp);
    expect($this->urls->timestamp('1 week'))->toBe(Carbon::now()->add('1 week')->timestamp);
    expect($this->urls->timestamp('1 month'))->toBe(Carbon::now()->add('1 month')->timestamp);
    expect($this->urls->timestamp(1))->toBe(Carbon::now()->add('1 second')->timestamp);
    expect($this->urls->timestamp(5))->toBe(Carbon::now()->add('5 seconds')->timestamp);
});

test('token throws when missing key id', function () {
    $urls = $this->app->make(MuxUrls::class);
    expect(fn() => $urls->token('playback-id', MuxAudience::Gif))->toThrow(\Exception::class);

    // config(['mux.signing_key.key_id' => $this->keyId]);
    // config(['mux.signing_key.private_key' => base64_encode($this->privateKey)]);

    // $urls = $this->app->make(MuxUrls::class);
    // expect($urls->token('playback-id', MuxAudience::Gif))->toBeString();
});

test('generates playback url', function () {
    expect(Str::containsAll($this->urls->playback('playback-id'), ['stream.mux.com', 'm3u8', 'playback-id']))->toBeTrue();
});

test('generates thumbnail url', function () {
    expect(Str::containsAll($this->urls->thumbnail('playback-id'), ['image.mux.com', 'thumbnail.jpg', 'playback-id']))->toBeTrue();
    expect(Str::containsAll($this->urls->thumbnail('playback-id', 'jpg'), ['image.mux.com', 'thumbnail.jpg', 'playback-id']))->toBeTrue();
    expect(Str::containsAll($this->urls->thumbnail('playback-id', 'png'), ['image.mux.com', 'thumbnail.png', 'playback-id']))->toBeTrue();
    expect(Str::containsAll($this->urls->thumbnail('playback-id', 'webp'), ['image.mux.com', 'thumbnail.webp', 'playback-id']))->toBeTrue();
});

test('generates animated gif url', function () {
    expect(Str::containsAll($this->urls->animated('playback-id'), ['image.mux.com', 'animated.gif', 'playback-id']))->toBeTrue();
    expect(Str::containsAll($this->urls->animated('playback-id', 'webp'), ['image.mux.com', 'animated.webp', 'playback-id']))->toBeTrue();
});
