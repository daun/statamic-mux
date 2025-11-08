<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        if (! $this->canHandle($asset, $proxyId)) {
            return false;
        }

        if (! $this->isReady($asset, $proxyId)) {
            return false;
        }

        try {
            return $this->downloadRendition($asset, $proxyId);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            throw new \Exception("Failed to download proxy of Mux asset: {$th->getMessage()}");
        }
    }

    /**
     * Whether a proxy can be downloaded for this asset.
     */
    public function canHandle(Asset $asset, string $proxyId): bool
    {
        return $asset->isVideo()
            && $this->service->hasExistingMuxAsset($asset)
            && $this->api->assetExists($proxyId);
    }

    /**
     * Whether the proxy can already be downloaded.
     */
    public function isReady(Asset $asset, string $proxyId): bool
    {
        return $this->api->assetIsReady($proxyId)
            && $this->api->assetRenditionsAreReady($proxyId);
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
        $path = "mux/proxies/{$asset->basename()}";
        $disk = Storage::disk('local');

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
            Log::error($th->getMessage());
            MuxAsset::fromAsset($asset)->setProxy(false)->save();

            throw $th;
        } finally {
            $disk->delete($path);
        }

        return false;
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
