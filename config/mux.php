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
    | videos with that blueprint to Mux. The `enabled` flag below allows
    | toggling this feature without removing the field from the blueprint.
    |
    | The `sync_meta` option will update asset metadata (title and filename) on
    | Mux whenever changed in Statamic. Disable to save API calls if not needed.
    |
    */

    'mirror' => [

        'enabled' => env('MUX_MIRROR_ENABLED', true),

        'sync_meta' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Optimize Storage
    |--------------------------------------------------------------------------
    |
    | To preserve storage space, the addon can replace original video files with
    | a smaller placeholder version after uploading to Mux. A short 10s clip of
    | the video will remain available in the control panel for previewing.
    |
    | Note that this makes the original file unavailable for download or
    | playback without going through Mux.
    |
    */

    'storage' => [

        'store_placeholders' => false,

        'placeholder_length' => 10, // seconds

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
    | Video Quality
    |--------------------------------------------------------------------------
    |
    | The video quality applied when creating new assets. Set this to `null` to
    | use the default quality setting of your Mux account. Learn about quality
    | levels at https://docs.mux.com/guides/use-video-quality-levels.
    |
    | Options: - basic: simple use cases, no encoding cost (max 1080p)
    |          - plus: consistent quality, incurs encoding cost (max 2160p)
    |          - premium: optimized for premium content, highest cost (max 2160p)
    |
    */

    'video_quality' => env('MUX_VIDEO_QUALITY', 'plus'),

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

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for the addon by writing to a custom channel defined in
    | your app's `logging` config, or use the default 'mux' channel.
    |
    | Set a minimum log level to control the verbosity of logs:
    | debug, info, notice, warning, error, critical, alert, emergency
    |
    */

    'logging' => [

        'enabled' => env('MUX_LOG_ENABLED', true),

        // Which channel to use; can be a stack or a specific channel
        'channel' => env('MUX_LOG_CHANNEL', 'mux'),

        'level' => env('MUX_LOG_LEVEL', 'warning'),

    ],
];
