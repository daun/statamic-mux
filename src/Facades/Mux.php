<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Daun\StatamicMux\Mux\MuxApi api()
 * @method static bool configured()
 * @method static ?string getMuxId(\Statamic\Assets\Asset $asset)
 * @method static ?\Daun\StatamicMux\Data\MuxPlaybackId getPlaybackId(\Statamic\Assets\Asset $asset)
 * @method static ?string getPlaybackUrl(\Statamic\Assets\Asset $asset, ?array $params = [])
 * @method static ?string getPlaybackToken(\Statamic\Assets\Asset $asset, ?array $params = [])
 * @method static ?string getThumbnailUrl(\Statamic\Assets\Asset $asset, ?int $width = null, ?int $height = null, ?int $time = null)
 * @method static ?string getGifUrl(\Statamic\Assets\Asset $asset, ?int $width = null, ?int $height = null, ?int $start = null, ?int $end = null, ?int $fps = null)
 * @method static ?string getPlaceholderDataUri(\Statamic\Assets\Asset $asset, ?int $time = null)
 * @method static array getDefaultPlaybackModifiers()
 * @method static \Illuminate\Support\Collection<number, \MuxPhp\Models\Asset> listMuxAssets(int $limit = 100, int $page = 1)
 * @method static bool hasExistingMuxAsset(\Statamic\Assets\Asset $asset)
 * @method static bool muxAssetExists(string $muxId)
 * @method static ?string createMuxAsset(\Statamic\Assets\Asset|string $asset)
 * @method static bool deleteMuxAsset(\Statamic\Assets\Asset|string $asset)
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
