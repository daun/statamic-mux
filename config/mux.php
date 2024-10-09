<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | The Mux API credentials. Required for use of this addon.
    |
    | Create a new token at https://dashboard.mux.com/settings/access-tokens
    |
    */

    'credentials' => [

        'token_id' => env('MUX_TOKEN_ID'),

        'token_secret' => env('MUX_TOKEN_SECRET'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Signing Keys
    |--------------------------------------------------------------------------
    |
    | Signing keys are used to create secure playback urls.
    | Required if the playback policy of your videos is set to 'signed'.
    |
    | Learn about policies at https://docs.mux.com/guides/secure-video-playback
    |
    | Create a new key at https://dashboard.mux.com/settings/signing-keys
    |
    | Expiration accepts ints (seconds) or strings (CarbonInterval format)
    |
    */

    'signing_key' => [

        'key_id' => env('MUX_SIGNING_KEY_ID'),

        'private_key' => env('MUX_SIGNING_PRIVATE_KEY'),

        'expiration' => env('MUX_SIGNED_URL_EXPIRATION', '72 hours'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Mirror Local Video Assets to Mux
    |--------------------------------------------------------------------------
    |
    | Adding a `mux_mirror` field to an asset blueprint will upload any local
    | videos with that blueprint to Mux. The flag below allows disabling this
    | feature without removing the existing field from the blueprint.
    |
    */

    'mirror' => [

        'enabled' => env('MUX_MIRROR_ENABLED', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    |
    | Upload new assets as test assets to evaluate Mux and this addon without
    | incurring charges. All videos are watermarked and deleted after 24 hours.
    |
    */

    'test_mode' => env('MUX_TEST_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Playback Policy
    |--------------------------------------------------------------------------
    |
    | The playback policy applied when creating new assets: 'public' or 'signed'
    |
    | Learn about policies at https://docs.mux.com/guides/secure-video-playback
    |
    */

    'playback_policy' => env('MUX_PLAYBACK_POLICY', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Video Quality
    |--------------------------------------------------------------------------
    |
    | The video quality applied when creating new assets: 'basic' or 'plus'.
    | Set this to `null` to use the default quality setting of your Mux account.
    |
    | Learn about qualities at https://docs.mux.com/guides/use-video-quality-levels.
    |
    */

    'video_quality' => env('MUX_VIDEO_QUALITY', 'plus'),

    /*
    |--------------------------------------------------------------------------
    | Playback Modifiers
    |--------------------------------------------------------------------------
    |
    | Default playback options passed as params to the playback url.
    |
    | See https://docs.mux.com/guides/modify-playback-behavior for details.
    |
    */

    'playback_modifiers' => [

        // 'min_resolution' => '720p',

        // 'max_resolution' => '1440p',

        // 'redundant_streams' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Driver
    |--------------------------------------------------------------------------
    |
    | Define the queue to use for processing upload/delete jobs.
    | Leave empty to use the default connection and queue of your app.
    |
    */

    'queue' => [

        'connection' => env('MUX_QUEUE_CONNECTION', null),

        'queue' => env('MUX_QUEUE', 'default'),

    ],
];
