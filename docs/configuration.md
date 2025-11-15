# Configuration

The addon config will be published to `config/mux.php` on installation. Read on
for details on each available config option.

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

## Test Mode

Mux offers a test mode for evaluating their service without incurring charges for storage or streaming.
All videos uploaded in test mode are watermarked and deleted after 24 hours.

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

- `basic` for apps with simpler needs that need to save on bandwidth & cost
- `plus` for conistently high quality output, but incurs encoding cost
- `premium` for premium high-detail content like sports broadcasts

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

Define how the addon handles original video files. In most cases, you'll want to
stick with the default behavior and keep the original video files around to ensure
long-term independence from any one video provider.

If you need to save storage space on the server and are fine with having the
original files on Mux only, you can configure the addon to replace video files
with a smaller placeholder version. This will store a short 10s clip of the
video for previewing in the backend, but requires Mux for streaming and
downloading the full video.

Any videos shorter than the defined placeholder length will keep the original.

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

Define the queue driver to be used for uploads and other long-running requests to Mux.
Leave it empty to use the default queue settings of your app.

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
