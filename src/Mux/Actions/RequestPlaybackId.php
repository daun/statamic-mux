<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use MuxPhp\Models\PlaybackID;
use Statamic\Assets\Asset;

class RequestPlaybackId
{
    public function __construct(
        protected MuxApi $api,
        protected MuxService $service,
    ) {}

    /**
     * Request a new playback id for a video asset.
     */
    public function handle(Asset $asset, ?MuxPlaybackPolicy $policy = null): ?MuxPlaybackId
    {
        if (! $this->shouldHandle($asset)) {
            return null;
        }

        $muxId = $this->service->getMuxId($asset);

        try {
            if ($result = $this->get($muxId, $policy)) {
                Log::info(
                    'Reused existing playback id from Mux asset',
                    ['asset' => $asset->id(), 'mux_id' => $muxId, 'playback_id' => $result->getId(), 'policy' => $result->getPolicy()],
                );
            } elseif ($result = $this->create($muxId, $policy)) {
                Log::info(
                    'Created new playback id for Mux asset',
                    ['asset' => $asset->id(), 'mux_id' => $muxId, 'playback_id' => $result->getId(), 'policy' => $result->getPolicy()],
                );
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            Log::error(
                "Error generating playback id for Mux asset: {$th->getMessage()}",
                ['asset' => $asset->id(), 'mux_id' => $muxId, 'exception' => $th],
            );

            throw new \Exception("Error generating playback id for Mux asset: {$th->getMessage()}", previous: $th);
        }

        try {
            $muxAsset = MuxAsset::fromAsset($asset);
            $playbackId = $muxAsset->addPlaybackId($result->getId(), (string) $result->getPolicy());
            $muxAsset->save();
        } catch (\Throwable $th) {
            Log::error(
                "Error saving playback id to Mux asset: {$th->getMessage()}",
                ['asset' => $asset->id(), 'mux_id' => $muxId, 'exception' => $th],
            );
        }

        return $playbackId ?? null;
    }

    /**
     * Determine if the action should handle the asset.
     */
    protected function shouldHandle(Asset $asset): bool
    {
        if (! $asset->isVideo()) {
            return false;
        }

        if (! $this->service->getMuxId($asset)) {
            return false;
        }

        return true;
    }

    /**
     * Get an existing playback id if it exists.
     */
    protected function get(string $muxId, ?MuxPlaybackPolicy $policy = null): ?PlaybackID
    {
        try {
            $response = $this->api->assets()->getAsset($muxId)->getData();
            $playbackIds = $response->getPlaybackIds();
        } catch (\Throwable $th) {
            Log::error(
                "Error fetching existing playback ids of Mux asset: {$th->getMessage()}",
                ['mux_id' => $muxId, 'exception' => $th],
            );

            return null;
        }

        $existingIds = collect($playbackIds ?? [])
            ->filter(fn ($id) => ! $policy || MuxPlaybackPolicy::make($id)?->is($policy))
            ->sort(fn ($id) => MuxPlaybackPolicy::make($id)?->isPublic() ? -1 : 0);

        Log::debug(
            'Existing playback ids of Mux asset',
            ['mux_id' => $muxId, 'playback_ids' => $existingIds->map(fn ($id) => $id->getId())->filter()->values()->all()],
        );

        return $existingIds->first();
    }

    /**
     * Create a new playback id.
     */
    protected function create(string $muxId, ?MuxPlaybackPolicy $policy = null): ?PlaybackID
    {
        $options = ['policy' => $policy?->value];

        $request = $this->api->createPlaybackIdRequest($options);

        return $this->api->assets()->createAssetPlaybackId($muxId, $request)->getData();
    }
}
