<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Daun\StatamicMux\Mux\MuxApi api()
 * @method static bool muxAssetExists(string $muxId)
 * @method static string|null createMuxAsset(\Statamic\Assets\Asset|string $asset, bool $force = false)
 * @method static bool deleteMuxAsset(\Statamic\Assets\Asset|string $asset)
 * @method static void listMuxAssets(int $limit = 100, int $page = 1)
 * @method static void hasExistingMuxAsset(\Statamic\Assets\Asset $asset)
 * @method static string|null getMuxId(\Statamic\Assets\Asset $asset)
 * @method static \Daun\StatamicMux\Data\MuxPlaybackId|null getPlaybackId(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, bool $requestIfMissing = true)
 * @method static string|null getPlaybackUrl(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, array $params = [])
 * @method static string|null getPlaybackToken(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, array $params = [])
 * @method static string|null getThumbnailUrl(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, array $params = [])
 * @method static string|null getGifUrl(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, array $params = [])
 * @method static string getPlaceholderDataUri(\Statamic\Assets\Asset $asset, \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null $policy = null, array $params = [])
 * @method static \Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy|null getDefaultPlaybackPolicy()
 * @method static array getDefaultPlaybackModifiers()
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
