<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use MuxPhp\Models\PlaybackID;
use Statamic\Assets\Asset;
use Statamic\Support\Traits\Hookable;

class RequestPlaybackId
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxService $service,
    ) {}

    /**
     * Request a new playback id for a video asset.
     */
    public function handle(Asset $asset, ?MuxPlaybackPolicy $policy = null): array
    {
        $muxId = MuxAsset::fromAsset($asset)->id();

        if ($muxId) {
            $playbackId = $this->get($muxId, $policy) ?? $this->create($muxId, $policy);

            return [$playbackId?->getId(), $playbackId?->getPolicy()];
        }

        return [null, null];
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
            Log::error($th->getMessage());
            return null;
        }

        return collect($playbackIds ?? [])
            ->filter(fn ($id) => ! $policy || MuxPlaybackPolicy::make($id)?->is($policy))
            ->sort(fn ($id) => MuxPlaybackPolicy::make($id)?->isPublic() ? -1 : 0)
            ->first();
    }

    /**
     * Create a new playback id.
     */
    protected function create(string $muxId, ?MuxPlaybackPolicy $policy = null): ?PlaybackID
    {
        try {
            $request = $this->api->createPlaybackIdRequest(['policy' => $policy?->value]);
            return $this->api->assets()->createAssetPlaybackId($muxId, $request)->getData();
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return null;
        }
    }
}
