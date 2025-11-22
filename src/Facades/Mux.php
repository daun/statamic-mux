<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Daun\StatamicMux\Mux\MuxApi api()
 * @method static bool configured()
 * @method static string|null createMuxAsset(\Statamic\Assets\Asset|string $asset, bool $force = false)
 * @method static bool updateMuxAsset(\Statamic\Assets\Asset|string $asset)
 * @method static bool deleteMuxAsset(\Statamic\Assets\Asset|string $asset)
 * @method static void hasExistingMuxAsset(\Statamic\Assets\Asset $asset)
 * @method static void listMuxAssets(int $limit = 100, int $page = 1)
 * @method static string|null getMuxId(\Statamic\Assets\Asset $asset)
 * @method static \Daun\StatamicMux\Data\MuxPlaybackId|null getPlaybackId(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, bool $requestIfMissing = true)
 * @method static string getPlaybackUrl(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string getThumbnailUrl(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string getGifUrl(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string|null getPlaceholderDataUri(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string|null getPlaybackToken(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string|null getThumbnailToken(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static string|null getStoryboardToken(\Daun\StatamicMux\Data\MuxPlaybackId $playbackId, array $params = [])
 * @method static \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null getDefaultPlaybackPolicy()
 * @method static array getDefaultPlaybackModifiers()
 * @method static void hook(string $name, \Closure $hook)
 *
 * @see \Daun\StatamicMux\Mux\MuxService
 */
class Mux extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MuxService::class;
    }
}
