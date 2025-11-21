<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use MuxPhp\Models\Asset as MuxAssetModel;
use Statamic\Assets\Asset;

class DownloadProxyVersion
{
    public function __construct(
        protected Application $app,
        protected MuxService $service,
        protected MuxApi $api,
        protected MuxUrls $urls,
    ) {}

    /**
     * Download a previously generated proxy version of a Mux asset.
     */
    public function handle(Asset $asset, string $proxyId): bool
    {
        if (! $this->shouldHandle($asset, $proxyId)) {
            return false;
        }

        if (! $this->isReady($asset, $proxyId)) {
            return false;
        }

        try {
            $this->downloadRendition($asset, $proxyId);

            Log::info(
                'Downloaded proxy version of Mux asset',
                ['asset' => $asset->id(), 'proxy_id' => $proxyId],
            );

            return true;
        } catch (\Throwable $th) {
            Log::error(
                "Error downloading proxy version of Mux asset: {$th->getMessage()}",
                ['asset' => $asset->id(), 'proxy_id' => $proxyId, 'exception' => $th],
            );

            throw new \Exception("Error downloading proxy version of Mux asset: {$th->getMessage()}", previous: $th);
        }
    }

    /**
     * Whether a proxy can be downloaded for this asset.
     */
    public function shouldHandle(Asset $asset, string $proxyId): bool
    {
        $skip = match (true) {
            ! $asset->isVideo() => 'not a video asset',
            ! $this->service->hasExistingMuxAsset($asset) => 'no existing Mux asset',
            ! $this->api->assetExists($proxyId) => 'proxy asset does not exist on Mux',
            default => null,
        };

        if ($skip) {
            Log::debug(
                "Skipping download of proxy version: {$skip}",
                ['asset' => $asset->id(), 'reason' => $skip, 'proxy_id' => $proxyId],
            );
        }

        return ! $skip;
    }

    /**
     * Whether the proxy can already be downloaded.
     */
    public function isReady(Asset $asset, string $proxyId): bool
    {
        $unready = match (true) {
            ! $this->api->assetIsReady($proxyId) => 'Mux asset is not ready',
            ! $this->api->assetRenditionsAreReady($proxyId) => 'Mux asset renditions are not ready',
            default => null,
        };

        if ($unready) {
            Log::debug(
                "Delaying download of proxy version: {$unready}",
                ['asset' => $asset->id(), 'proxy_id' => $proxyId, 'reason' => $unready],
            );
        }

        return ! $unready;
    }

    /**
     * Download the rendition and replace the original file.
     */
    protected function downloadRendition(Asset $asset, string $proxyId): bool
    {
        $muxId = $this->service->getMuxId($asset);
        $originalData = $this->api->assets()->getAsset($muxId)->getData();
        $duration = $originalData?->getDuration() ?? null;

        $data = $this->api->assets()->getAsset($proxyId)->getData();
        $playbackId = $this->getPlaybackId($data);
        $rendition = $this->getRenditionName($data);

        $url = $this->urls->download($playbackId, $rendition, $asset->filename());

        Log::debug(
            'Downloading proxy rendition from Mux url',
            ['asset' => $asset->id(), 'url' => $url, 'playback_id' => $playbackId, 'rendition' => $rendition],
        );

        try {
            $contents = Http::get($url)->body();
            $asset->disk()->put($asset->path(), $contents);
            $asset->writeMeta([...$asset->generateMeta(), 'duration' => $duration]);
            $asset->save();

            MuxAsset::fromAsset($asset)
                ->setProxy(true)
                ->setDuration($duration)
                ->save();

            return true;
        } catch (\Throwable $th) {
            MuxAsset::fromAsset($asset)->setProxy(false)->save();

            throw $th;
        }
    }

    protected function getPlaybackId(MuxAssetModel $data): ?string
    {
        $playbackIds = $data->getPlaybackIds();
        $playbackId = $playbackIds[0] ?? null;

        return $playbackId?->getId() ?? null;
    }

    protected function getRenditionName(MuxAssetModel $data): ?string
    {
        $renditions = $data->getStaticRenditions()?->getFiles();
        $rendition = $renditions[0] ?? null;

        return $rendition?->getName() ?? null;
    }
}
