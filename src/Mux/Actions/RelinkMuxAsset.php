<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Events\AssetRelinkedToMux;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use MuxPhp\Models\Asset as MuxApiAssetModel;
use Statamic\Assets\Asset;

class RelinkMuxAsset
{
    public function __construct(
        protected MuxService $service,
        protected RequestPlaybackId $playbackIds,
    ) {}

    public function handle(Asset $asset, MuxApiAssetModel $remote, bool $proxySource = false): bool
    {
        $muxId = $remote->getId();

        if (! $muxId) {
            Log::warning(
                'Cannot relink asset to Mux: remote asset has no id',
                ['asset' => $asset->id()],
            );

            return false;
        }

        $field = MirrorField::getHandle($asset);

        if (! $field) {
            Log::warning(
                'Cannot relink asset to Mux: asset has no mirror field',
                ['asset' => $asset->id(), 'mux_id' => $muxId],
            );

            return false;
        }

        $playbackId = $this->resolvePlaybackId($muxId, $asset);

        $original = $asset->get($field);

        $muxAsset = MuxAsset::fromAsset($asset, $field)
            ->clear()
            ->withId($muxId)
            ->withDuration($this->duration($remote));

        if ($playbackId) {
            $muxAsset->withPlaybackId($playbackId->id(), $playbackId->policy());
        }

        if ($proxySource) {
            $muxAsset->withProxy(true);
        }

        try {
            $muxAsset->save();
        } catch (\Throwable $th) {
            $this->restore($asset, $field, $original);

            Log::error(
                "Error relinking asset to Mux: {$th->getMessage()}",
                ['asset' => $asset->id(), 'mux_id' => $muxId, 'exception' => $th],
            );

            return false;
        }

        Log::info(
            'Relinked asset to existing Mux asset',
            ['asset' => $asset->id(), 'mux_id' => $muxId, 'playback_id' => $playbackId?->id(), 'is_proxy' => $proxySource],
        );

        AssetRelinkedToMux::dispatch($asset, $muxId);

        return true;
    }

    protected function resolvePlaybackId(string $muxId, Asset $asset): ?MuxPlaybackId
    {
        return $this->playbackIds->request(
            $muxId,
            $this->service->getDefaultPlaybackPolicy(),
            ['asset' => $asset->id()],
        );
    }

    protected function duration(MuxApiAssetModel $remote): ?float
    {
        $duration = $remote->getDuration();

        return is_numeric($duration) ? (float) $duration : null;
    }

    protected function restore(Asset $asset, string $field, mixed $original): void
    {
        try {
            $asset->set($field, $original);
            $asset->saveQuietly();
        } catch (\Throwable $th) {
            Log::error(
                "Error restoring local Mux data after failed relink: {$th->getMessage()}",
                ['asset' => $asset->id(), 'exception' => $th],
            );
        }
    }
}
