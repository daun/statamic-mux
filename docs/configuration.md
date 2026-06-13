# Configuration

The addon's config file is published to `config/mux.php` on installation. Each available option is
described below.

## Credentials

The required Mux API credentials. Learn more about [Connecting Mux](/connecting-mux).

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    */

    'credentials' => [

        'token_id' => env('MUX_TOKEN_ID'), // [!code focus]

        'token_secret' => env('MUX_TOKEN_SECRET'), // [!code focus]

    ]
];
```

## Signing Keys

Signing keys are used to create secure playback urls. Required if the playback
policy of your videos is set to `signed`. Learn more about [Secure Playback](/secure-playback).

The `expiration` setting accepts either `int`s for seconds, a human duration string
like `1 hour` and `2 days`, or an ISO date interval like `P3M` for 3 months.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Signing keys
    |--------------------------------------------------------------------------
    */

    'signing_key' => [

        'key_id' => env('MUX_SIGNING_KEY_ID'), // [!code focus]

        'private_key' => env('MUX_SIGNING_PRIVATE_KEY'), // [!code focus]

        'expiration' => env('MUX_SIGNED_URL_EXPIRATION', '72 hours'), // [!code focus]

    ]
];
```

## Mirror Field Settings

Configure the behavior of assets mirrored to Mux through the [Mirror fieldtype](/upload). The `enabled` flag
turns mirroring on or off globally. The `sync_meta` flag controls whether
[Mux metadata](https://www.mux.com/docs/guides/add-metadata-to-your-videos) is updated
whenever the asset is updated in Statamic. Turn it off to only set the metadata once on creation.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Mirror Local Video Assets to Mux
    |--------------------------------------------------------------------------
    */

    'mirror' => [

        'enabled' => env('MUX_MIRROR_ENABLED', true), // [!code focus]

        'sync_meta' => true, // [!code focus]

    ],

];
```

## Control Panel Thumbnails

Configure how video thumbnails are rendered in the control panel. By default, the addon renders
animated GIF previews of the first five seconds, which helps editors identify videos but uses more
bandwidth than static images. Set `animated` to `false` to use static images, or `enabled` to `false`
to disable thumbnails entirely.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Control Panel Thumbnails
    |--------------------------------------------------------------------------
    */

    'cp_thumbnails' => [

        'enabled' => true, // [!code focus]

        'animated' => true, // [!code focus]

    ],

];
```

## Test Mode

Mux offers a test mode for evaluating their service without incurring charges for storage or streaming.
All videos uploaded in test mode are watermarked and deleted after 24 hours. This is recommended during
initial setup and on development machines.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    */

    'test_mode' => env('MUX_TEST_MODE', false), // [!code focus]
    
];
```

## Video Quality

Mux offers three quality levels. Learn more at
[Choosing Video Quality](https://docs.mux.com/guides/use-video-quality-levels).

- `basic`: lower bandwidth and cost, lower quality
- `plus`: consistently high quality, higher encoding cost
- `premium`: highest quality, for high-detail content such as sports broadcasts

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Video Quality
    |--------------------------------------------------------------------------
    */

    'video_quality' => env('MUX_VIDEO_QUALITY', 'plus'), // [!code focus]

];
```

You can set this to `null` to use the default quality setting of your Mux account if you have
defined one in the [Default Video Quality Settings](https://dashboard.mux.com/organizations/59g3uj/settings/video-quality)
of your Mux account dashboard.

## Playback Policy

Videos uploaded to Mux can restrict access by requiring signed playback urls.
Learn more about [Setting Up Secure Playback](/secure-playback).

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Playback Policy
    |--------------------------------------------------------------------------
    */

    'playback_policy' => env('MUX_PLAYBACK_POLICY', 'public'), // [!code focus]

];
```

## Playback Modifiers

Change the default playback behavior of video streams received from Mux.
Applies to any videos or players rendered using the built-in Antlers tags.
No modifiers are set by default; the entries below are examples.
Learn more in the Mux docs on [Modifying Playback Behavior](https://docs.mux.com/guides/modify-playback-behavior).

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Playback Modifiers
    |--------------------------------------------------------------------------
    */

    'playback_modifiers' => [

        'min_resolution' => '720p', // [!code focus]

        'max_resolution' => '1440p', // [!code focus]

    ],

];
```

## Storage Optimization

Define how the addon handles original video files. By default, the original files are kept on your asset
disk, which avoids depending solely on Mux for storage and lets you stream or download from your origin
server as a fallback.

To save storage space, set `store_placeholders` to `true` to replace each video file with a short clip
(default 10 seconds, set by `placeholder_length`). The clip is used for previewing in the control panel;
streaming and downloading the full video then requires Mux.

Videos shorter than `placeholder_length` keep their original file.

This feature requires a running [queue worker](https://laravel.com/docs/queues#running-the-queue-worker),
as processing can take time depending on file size.

```php
    /*
    |--------------------------------------------------------------------------
    | Optimize Storage
    |--------------------------------------------------------------------------
    */

    'storage' => [

        'store_placeholders' => true, // [!code focus]

        'placeholder_length' => 10, // [!code focus]

    ],
```

## Queue Driver

Define the queue connection and queue name used for uploads and other long-running requests to Mux.
Leave `connection` empty to use your app's default queue connection.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Queue Driver
    |--------------------------------------------------------------------------
    */

    'queue' => [

        'connection' => env('MUX_QUEUE_CONNECTION', null), // [!code focus]

        'queue' => env('MUX_QUEUE', null), // [!code focus]

    ],
];
```

## Logging

Configure the output and verbosity of the addon's logs.

To troubleshoot uploads and see how video files are processed, increase the log level to `debug`
temporarily. Set it back to `warning` in production to avoid excessive log output.

The addon creates its own log channel, writing to `storage/logs/mux.log`. To customize it, either define
your own `mux` channel in `config/logging.php`, or point the addon at a different channel via
`MUX_LOG_CHANNEL`.

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [

        'enabled' => env('MUX_LOG_ENABLED', true),

        'channel' => env('MUX_LOG_CHANNEL', 'mux'), // [!code focus]

        'level' => env('MUX_LOG_LEVEL', env('LOG_LEVEL', 'debug')), // [!code focus]

    ],
];
```
