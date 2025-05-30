# Hooks

Hooks allow intercepting and modifying data at various points in the package's workflow.

## Example

The best place to register hooks is in your service provider's `boot` method.

```php
use Daun\StatamicMux\Facades\Mux;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mux::hook('hook-name', function ($payload, $next) {
            // Manipulate the payload here
            [...]
            // Make sure to call $next() to continue the process
            return $next($payload);
        });
    }
}
```

## Available Hooks

### Asset Settings

Settings to apply when uploading a video to Mux, e.g. video quality or playback policy. Change any of the available
[input parameters of the Mux assets endpoint](https://www.mux.com/docs/api-reference/video/assets/create-asset)
based on the properties of the asset itself.

```php
// Set the video quality based on the asset's height
Mux::hook('asset-settings', function ($payload, $next) {
    if ($payload->asset->height() >= 1080) {
        $payload->settings['video_quality'] = 'plus';
    }
    return $next($payload);
});
```

### Asset Metadata

Define custom metadata for videos uploaded to Mux. Currently supports title, creator id and external id
properties. Learn more about [adding metadata to Mux assets](https://www.mux.com/docs/guides/add-metadata-to-your-videos).

```php
// Pull metadata from custom blueprint fields on the asset
Mux::hook('asset-meta', function ($payload, $next) {
    $payload->meta = [
        'title' => $payload->asset->get('caption'),
        'external_id' => md5($payload->asset->url()),
    ];
    return $next($payload);
});
```
