# Hooks

Hooks provide a way of intercepting and modifying data at various points in the package's workflow.

## Example

The best place to register hooks is in your service provider's `boot` method.

```php
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ActionName::hook('hook-name', function ($payload, $next) {
            // Manipulate the payload here
            [...]
            // Make sure to call $next() to continue the process
            return $next($payload);
        });
    }
}
```

## Asset Data

Transform the data sent to Mux when uploading a video. Change any of the available
[input parameters of the Mux assets endpoint](https://www.mux.com/docs/api-reference/video/assets/create-asset).
Most useful to dynamically set video quality or playback policy based on the properties of an asset itself.

```php
// Set the video quality based on the asset's height
CreateMuxAsset::hook('asset-data', function ($payload, $next) {
    if ($payload['asset']->height() >= 1080) {
        $payload['data']['video_quality'] = 'plus';
    }
    return $next($payload);
});
```

## Asset Metadata

Define custom metadata for videos uploaded to Mux. Currently supports title, creator id and external id
properties. Learn more about [adding metadata to Mux assets](https://www.mux.com/docs/guides/add-metadata-to-your-videos).

```php
// Pull metadata from custom blueprint fields on the asset
CreateMuxAsset::hook('asset-meta', function ($payload, $next) {
    $payload['meta'] = [
        'title' => $payload['asset']->get('caption'),
        'external_id' => md5($payload['asset']->url()),
    ];
    return $next($payload);
});
```
