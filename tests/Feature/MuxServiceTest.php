<?php

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxClient;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->app->bind(MuxClient::class, fn () => $this->guzzler->getClient());
    $this->api = $this->app->make(MuxApi::class);
    $this->app->bind(MuxApi::class, fn () => $this->api);

    $this->service = $this->app->make(MuxService::class);

    $this->addMirrorFieldToAssetBlueprint();
    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->webm = $this->uploadTestFileToTestContainer('test.webm');
    $this->jpg = $this->uploadTestFileToTestContainer('test.jpg');

    MirrorField::clear($this->mp4);
    MirrorField::clear($this->webm);
    MirrorField::clear($this->jpg);

    Stache::clear();
});

test('returns an api instance', function () {
    expect($this->service->api())->toBeInstanceOf(MuxApi::class);
});

test('returns the configuration state', function () {
    expect($this->service->configured())->toBeBool();
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => 'test', 'mux.credentials.token_secret' => null]);
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => null, 'mux.credentials.token_secret' => 'test']);
    expect($this->service->configured())->toBeFalse();

    config(['mux.credentials.token_id' => 'test', 'mux.credentials.token_secret' => 'test']);
    expect($this->service->configured())->toBeTrue();
});

test('returns the default playback policy', function () {
    config(['mux.playback_policy' => null]);
    expect($this->service->getDefaultPlaybackPolicy())->toBeNull();

    config(['mux.playback_policy' => 'public']);
    expect($this->service->getDefaultPlaybackPolicy())->toBeInstanceOf(MuxPlaybackPolicy::class);
    expect($this->service->getDefaultPlaybackPolicy()->isPublic())->toBeTrue();

    config(['mux.playback_policy' => 'signed']);
    expect($this->service->getDefaultPlaybackPolicy())->toBeInstanceOf(MuxPlaybackPolicy::class);
    expect($this->service->getDefaultPlaybackPolicy()->isSigned())->toBeTrue();
});

test('returns the default playback modifiers', function () {
    config(['mux.playback_modifiers' => null]);
    expect($this->service->getDefaultPlaybackModifiers())->toBeArray()->toHaveCount(0);

    config(['mux.playback_modifiers' => []]);
    expect($this->service->getDefaultPlaybackModifiers())->toBeArray()->toHaveCount(0);

    config(['mux.playback_modifiers' => ['width' => 100, 'height' => 100]]);
    expect($this->service->getDefaultPlaybackModifiers())->toEqual(['width' => 100, 'height' => 100]);
});

test('sends API request to list assets', function () {
    $this->guzzler->expects($this->once())
        ->ray()
        ->get('https://api.mux.com/video/v1/assets')
        ->withQuery([
            'limit' => 100,
            'page' => 1,
        ])
        ->willRespondJson([
            'next_cursor' => 'tF601CUtCLmnYuHW01Vwl6BWcWTNv001uoaiK4C01jqk1acX802plAjZhTQ',
            'data' => [
                [
                    'tracks' => [
                        [
                            'type' => 'video',
                            'max_width' => 1920,
                            'max_height' => 800,
                            'max_frame_rate' => 24,
                            'id' => 'HK01Bq7FrEQmIu3QpRiZZ98HQOOZjm6BYyg17eEunlyo',
                            'duration' => 734.166667
                        ],
                        [
                            'type' => 'audio',
                            'max_channels' => 2,
                            'id' => 'nNKHJqw2G9cE019AoK16CJr3O27gGnbtW4w525hJWqWw',
                            'duration' => 734.143991
                        ]
                    ],
                    'status' => 'ready',
                    'playback_ids' => [
                        [
                            'policy' => 'public',
                            'id' => '85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc'
                        ]
                    ],
                    'max_stored_resolution' => 'HD',
                    'resolution_tier' => '1080p',
                    'max_stored_frame_rate' => 24,
                    'master_access' => 'none',
                    'id' => '8jd7M77xQgf2NzuocJRPYdSdEfY5dLlcRwFARtgQqU4',
                    'encoding_tier' => 'baseline',
                    'video_quality' => 'basic',
                    'duration' => 734.25,
                    'created_at' => '1609869152',
                    'aspect_ratio' => '12:5'
                ],
                [
                    'tracks' => [
                        [
                            'type' => 'video',
                            'max_width' => 1920,
                            'max_height' => 1080,
                            'max_frame_rate' => 29.97,
                            'id' => 'RiyQPM31a1SPtfI802bEP2zD02F5FQVNL801FRHeE5t01G4',
                            'duration' => 23.8238
                        ],
                        [
                            'type' => 'audio',
                            'max_channels' => 2,
                            'id' => 'LvINTciHVoC017knMCH01y9pSi5OrDLCRaBPNDAoNJcmg',
                            'duration' => 23.823792
                        ]
                    ],
                    'status' => 'ready',
                    'playback_ids' => [
                        [
                            'policy' => 'public',
                            'id' => 'vAFLI2eKFFicXX00iHBS2vqt5JjJGg5HV6fQ4Xijgt1I'
                        ]
                    ],
                    'max_stored_resolution' => 'HD',
                    'resolution_tier' => '1080p',
                    'max_stored_frame_rate' => 29.97,
                    'master_access' => 'none',
                    'id' => 'lJ4bGGsp7ZlPf02nMg015W02iHQLN9XnuuLRBsPS00xqd68',
                    'encoding_tier' => 'smart',
                    'video_quality' => 'plus',
                    'duration' => 23.857167,
                    'created_at' => '1609868768',
                    'aspect_ratio' => '16:9'
                ]
            ]
        ]);

    $muxAssets = $this->service->listMuxAssets();

    $this->guzzler->assertHistoryCount(1);

    expect($muxAssets)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($muxAssets)->toHaveLength(2);
    expect($muxAssets[0])->toBeInstanceOf(\MuxPhp\Models\Asset::class);
    expect($muxAssets[0]->getId())->toBe('8jd7M77xQgf2NzuocJRPYdSdEfY5dLlcRwFARtgQqU4');
    expect($muxAssets[1])->toBeInstanceOf(\MuxPhp\Models\Asset::class);
    expect($muxAssets[1]->getId())->toBe('lJ4bGGsp7ZlPf02nMg015W02iHQLN9XnuuLRBsPS00xqd68');
});

test('paginates API request to list assets', function () {
    $this->guzzler->expects($this->once())
        ->ray()
        ->get('https://api.mux.com/video/v1/assets')
        ->withQuery([
            'limit' => 100,
            'page' => 1,
        ])
        ->willRespondJson([
            'next_cursor' => 'tF601CUtCLmnYuHW01Vwl6BWcWTNv001uoaiK4C01jqk1acX802plAjZhTQ',
            'data' => [
                [
                    'tracks' => [
                        [
                            'type' => 'video',
                            'max_width' => 1920,
                            'max_height' => 800,
                            'max_frame_rate' => 24,
                            'id' => 'HK01Bq7FrEQmIu3QpRiZZ98HQOOZjm6BYyg17eEunlyo',
                            'duration' => 734.166667
                        ],
                        [
                            'type' => 'audio',
                            'max_channels' => 2,
                            'id' => 'nNKHJqw2G9cE019AoK16CJr3O27gGnbtW4w525hJWqWw',
                            'duration' => 734.143991
                        ]
                    ],
                    'status' => 'ready',
                    'playback_ids' => [
                        [
                            'policy' => 'public',
                            'id' => '85g23gYz7NmQu02YsY81ihuod6cZMxCp017ZrfglyLCKc'
                        ]
                    ],
                    'max_stored_resolution' => 'HD',
                    'resolution_tier' => '1080p',
                    'max_stored_frame_rate' => 24,
                    'master_access' => 'none',
                    'id' => '8jd7M77xQgf2NzuocJRPYdSdEfY5dLlcRwFARtgQqU4',
                    'encoding_tier' => 'baseline',
                    'video_quality' => 'basic',
                    'duration' => 734.25,
                    'created_at' => '1609869152',
                    'aspect_ratio' => '12:5'
                ]
            ]
        ]);

    $this->guzzler->expects($this->once())
        ->ray()
        ->get('https://api.mux.com/video/v1/assets')
        ->withQuery([
            'limit' => 100,
            'page' => 2,
        ])
        ->willRespondJson([
            'next_cursor' => 'tF601CUtCLmnYuHW01Vwl6BWcWTNv001uoaiK4C01jqk1acX802plAjZhTQ',
            'data' => [
                [
                    'tracks' => [
                        [
                            'type' => 'video',
                            'max_width' => 1920,
                            'max_height' => 1080,
                            'max_frame_rate' => 29.97,
                            'id' => 'RiyQPM31a1SPtfI802bEP2zD02F5FQVNL801FRHeE5t01G4',
                            'duration' => 23.8238
                        ],
                        [
                            'type' => 'audio',
                            'max_channels' => 2,
                            'id' => 'LvINTciHVoC017knMCH01y9pSi5OrDLCRaBPNDAoNJcmg',
                            'duration' => 23.823792
                        ]
                    ],
                    'status' => 'ready',
                    'playback_ids' => [
                        [
                            'policy' => 'public',
                            'id' => 'vAFLI2eKFFicXX00iHBS2vqt5JjJGg5HV6fQ4Xijgt1I'
                        ]
                    ],
                    'max_stored_resolution' => 'HD',
                    'resolution_tier' => '1080p',
                    'max_stored_frame_rate' => 29.97,
                    'master_access' => 'none',
                    'id' => 'lJ4bGGsp7ZlPf02nMg015W02iHQLN9XnuuLRBsPS00xqd68',
                    'encoding_tier' => 'smart',
                    'video_quality' => 'plus',
                    'duration' => 23.857167,
                    'created_at' => '1609868768',
                    'aspect_ratio' => '16:9'
                ]
            ]
        ]);

    $this->guzzler->expects($this->once())
        ->ray()
        ->get('https://api.mux.com/video/v1/assets')
        ->withQuery([
            'limit' => 100,
            'page' => 3,
        ])
        ->willRespondJson([
            'next_cursor' => null,
            'data' => []
        ]);

    $muxAssets = $this->service->listMuxAssets(200);

    $this->guzzler->assertHistoryCount(3);

    expect($muxAssets)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($muxAssets)->toHaveLength(2);
    expect($muxAssets[0])->toBeInstanceOf(\MuxPhp\Models\Asset::class);
    expect($muxAssets[0]->getId())->toBe('8jd7M77xQgf2NzuocJRPYdSdEfY5dLlcRwFARtgQqU4');
});
