<?php

namespace Daun\StatamicMux\Mux\Actions;

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Foundation\Application;
use Statamic\Assets\Asset;
use Statamic\Support\Traits\Hookable;

class RequestPlaybackId
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxService $service,
    ) {
    }

    /**
     * Request a new playback id for a video asset.
     */
    public function handle(Asset|string $asset): ?array
    {
        try {
            $muxId = $this->service->muxId($asset);
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            $playbackInstances = $muxAssetResponse->getPlaybackIds();
        } catch (\Throwable $th) {
        }

        $publicPlaybackInstances = array_filter(
            $playbackInstances ?? [],
            fn ($instance) => $this->api->hasPublicPlaybackPolicy($instance)
        );

        $playbackInstance = ($publicPlaybackInstances[0] ?? $playbackInstances[0] ?? null);

        $playbackId = $playbackInstance?->getId();
        $playbackPolicy = $playbackInstance?->getPolicy();

        return $playbackId ? [$playbackId, $playbackPolicy] : null;
    }
}
