# Events

A number of [events](https://laravel.com/docs/events) are dispatched when uploading videos
to Mux or deleting them. Use them to react to, or prevent, the upload and
deletion of specific videos.

## Example

::: code-group

```php [AppServiceProvider.php]
use App\Listeners\DoSomethingAfterMuxUpload;
use Daun\StatamicMux\Events\AssetUploadedToMux;

class AppServiceProvider extends ServiceProvider
{
    protected $listen = [
        AssetUploadedToMux::class => [DoSomethingAfterMuxUpload::class],
    ];
}
```

```php [DoSomethingAfterMuxUpload.php]
class DoSomethingAfterMuxUpload
{
    public function handle(AssetUploadedToMux $event)
    {
        //
    }
}
```

:::

## `AssetUploadingToMux`

**Dispatched before a video is uploaded to Mux.**

Return `false` to prevent it from being uploaded.

| Argument | Type | Description |
| -------- | ---- | ----------- |
| **`$event->asset`** | `Asset` | local video asset |

```php
class PreventUploadingLongVideos
{
    public function handle(AssetUploadingToMux $event)
    {
        if ($event->asset->duration() > 120) return false;
    }
}
```

## `AssetUploadedToMux`

**Dispatched after a video has been uploaded to Mux.**

| Argument | Type | Description |
| -------- | ---- | ----------- |
| **`$event->asset`** | `Asset` | local video asset |
| **`$event->muxId`** | `string` | id of the created Mux video |

```php
class LogVideoUpload
{
    public function handle(AssetUploadedToMux $event)
    {
        Log::info('Uploaded video to Mux: {path}', ['path' => $event->asset->path()]);
    }
}
```

## `AssetDeletingFromMux`

**Dispatched before a video is deleted from Mux.**

Return `false` to prevent it from being deleted.

| Argument | Type | Description |
| -------- | ---- | ----------- |
| **`$event->asset`** | `Asset` | local video asset |
| **`$event->muxId`** | `string` | id of the Mux video to be deleted |

```php
class PreventDeletingOldVideos
{
    public function handle(AssetDeletingFromMux $event)
    {
        if ($event->asset->lastModified()->diffInYears() >= 2) return false;
    }
}
```

## `AssetDeletedFromMux`

**Dispatched after a video has been deleted from Mux.**

| Argument | Type | Description |
| -------- | ---- | ----------- |
| **`$event->asset`** | `Asset` | local video asset |
| **`$event->muxId`** | `string` | id of the deleted Mux video |

```php
class LogVideoDeletion
{
    public function handle(AssetDeletedFromMux $event)
    {
        Log::info('Deleted video from Mux: {path}', ['path' => $event->asset->path()]);
    }
}
```
