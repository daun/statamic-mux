<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Concerns\GeneratesAssetData;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Support\Collection;
use MuxPhp\Models\Asset as MuxApiAssetModel;
use MuxPhp\Models\PlaybackID;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;

class CreateMuxAsset
{
    use GeneratesAssetData;

    public function __construct(
        protected MuxService $service,
        protected MuxApi $api,
    ) {}

    /**
     * Upload a video asset to Mux.
     */
    public function handle(Asset $asset, bool $force = false): ?string
    {
        if (! $this->shouldHandle($asset, $force)) {
            return null;
        }

        if (AssetUploadingToMux::dispatch($asset) === false) {
            Log::debug(
                'Canceled video upload to Mux via event listener',
                ['asset' => $asset->id(), 'event' => 'AssetUploadingToMux'],
            );

            return null;
        }

        $previousMuxId = $this->service->getMuxId($asset);
        $otherAssets = $this->getAssetsWithIdenticalMuxId($asset);

        try {
            if ($this->assetIsPubliclyAccessible($asset)) {
                $muxAsset = $this->ingestAssetToMux($asset);
            } else {
                $muxAsset = $this->uploadAssetToMux($asset);
            }
        } catch (\Throwable $th) {
            Log::error(
                "Error uploading video to Mux: {$th->getMessage()}",
                ['asset' => $asset->id(), 'exception' => $th],
            );

            throw new \Exception("Error uploading video to Mux: {$th->getMessage()}", previous: $th);
        }

        if ($muxAsset) {
            $muxId = $muxAsset->getId();
            $playbackId = $this->getPlaybackId($muxAsset);

            Log::info(
                'Video uploaded to Mux',
                ['asset' => $asset->id(), 'mux_id' => $muxId, 'playback_id' => $playbackId?->getId(), 'playback_policy' => $playbackId?->getPolicy()],
            );

            MuxAsset::fromAsset($asset)
                ->clear()
                ->withId($muxId)
                ->withPlaybackId($playbackId->getId(), (string) $playbackId->getPolicy())
                ->save();

            if (! $otherAssets->count()) {
                $this->service->deleteMuxAsset($previousMuxId);
            }

            AssetUploadedToMux::dispatch($asset, $muxId);

            return $muxId;
        }

        return null;
    }

    /**
     * Whether a Mux asset can be created for this asset.
     */
    protected function shouldHandle(Asset $asset, bool $force = false): bool
    {
        $skip = match (true) {
            ! $asset->isVideo() => 'not a video asset',
            MuxAsset::fromAsset($asset)->isProxy() => 'asset is a proxy',
            ! $force && $this->service->hasExistingMuxAsset($asset) => 'asset already exists on Mux',
            default => null,
        };

        if ($skip) {
            Log::debug(
                "Skipping upload of asset to Mux: {$skip}",
                ['asset' => $asset->id(), 'reason' => $skip],
            );
        }

        return ! $skip;
    }

    /**
     * Determine whether an asset can be ingested from a public url.
     */
    protected function assetIsPubliclyAccessible(Asset $asset): bool
    {
        $filesystem = $asset->container()->disk()->filesystem()->getConfig();

        $public = true;

        if (empty($filesystem['url'] ?? null)) {
            $public = false;
        }

        if (($filesystem['visibility'] ?? null) !== 'public') {
            $public = false;
        }

        if (app()->isLocal() && $filesystem['driver'] === 'local') {
            $public = false;
        }

        Log::debug(
            'Asset is publicly accessible: '.($public ? 'yes' : 'no'),
            ['asset' => $asset->id(), 'public' => $public, 'filesystem' => $filesystem],
        );

        return $public;
    }

    /**
     * Upload a video asset to Mux using a direct upload link.
     */
    protected function uploadAssetToMux(Asset $asset): ?MuxApiAssetModel
    {
        $data = $this->getAssetData($asset) + $this->getAssetSettings($asset);
        $request = $this->api->createUploadRequest($data);
        $muxUpload = $this->api->directUploads()->createDirectUpload($request)->getData();

        Log::debug(
            'Uploading asset to Mux via direct upload',
            ['asset' => $asset->id(), 'upload_id' => $muxUpload->getId(), 'upload_url' => $muxUpload->getUrl()],
        );

        $this->api->client()->put($muxUpload->getUrl(), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $asset->stream(),
        ]);

        $muxUpload = $this->api->directUploads()->getDirectUpload($muxUpload->getId())->getData();
        $muxId = $muxUpload?->getAssetId();

        if (! $muxId) {
            Log::error(
                'Error retrieving Mux asset id from direct upload',
                ['asset' => $asset->id(), 'upload_id' => $muxUpload?->getId(), 'response' => $muxUpload],
            );

            return null;
        }

        return $this->api->assets()->getAsset($muxId)->getData();
    }

    /**
     * Upload a video asset to Mux using ingestion from a public url.
     */
    protected function ingestAssetToMux(Asset $asset): ?MuxApiAssetModel
    {
        $input = $this->api->input(['url' => $asset->absoluteUrl()]);
        $data = ['input' => $input] + $this->getAssetData($asset) + $this->getAssetSettings($asset);
        $request = $this->api->createAssetRequest($data);

        Log::debug(
            'Uploading asset to Mux via public url',
            ['asset' => $asset->id(), 'public_url' => $asset->absoluteUrl()],
        );

        return $this->api->assets()->createAsset($request)->getData();
    }

    /**
     * Get the playback id from a Mux asset data object.
     */
    protected function getPlaybackId(MuxApiAssetModel $data): ?PlaybackID
    {
        return collect($data->getPlaybackIds() ?? [])
            ->sort(fn ($id) => MuxPlaybackPolicy::make($id)?->isPublic() ? -1 : 0)
            ->first();
    }

    /**
     * Find all other assets using the same Mux id.
     */
    protected function getAssetsWithIdenticalMuxId(Asset $asset): Collection
    {
        $muxId = $this->service->getMuxId($asset);

        $containers = MirrorField::containers();
        $fields = $containers->map(fn ($container) => MirrorField::getHandle($container));
        if (! count($fields)) {
            return collect();
        }

        return Assets::query()
            ->whereIn('container', $containers->map->handle())
            ->whereNot(fn($q) =>  $q
                ->where('container', $asset->containerHandle())
                ->where('path', $asset->path())
            )
            ->where(fn ($q) => $fields->each(
                fn ($handle, $i) => $i === 0
                    ? $q->whereJsonContains("{$handle}.id", $muxId)
                    : $q->orWhereJsonContains("{$handle}.id", $muxId)
            ))
            ->get();
    }
}
