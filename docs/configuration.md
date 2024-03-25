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

## Playback Policy

Videos uploaded to Mux can restrict access by requiring signed playback urls.
Learn more about [Secure Playback](/secure-playback).

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

## Encoding Tier

Mux offers two encoding tiers: `smart` for high visual quality or `baseline` for apps with simpler quality needs to save on bandwidth.
Learn more about [Choosing Encoding Tiers](https://docs.mux.com/guides/use-encoding-tiers).

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Encoding Tier
    |--------------------------------------------------------------------------
    */

    'encoding_tier' => env('MUX_ENCODING_TIER', 'smart'), // [!code focus]

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

    'playback_modifiers' => [ // [!code focus]
        'min_resolution' => '720p', // [!code focus]
        'max_resolution' => '1440p', // [!code focus]
    ],

];
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
