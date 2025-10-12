<?php

namespace Daun\StatamicMux\Thumbnails;

use Daun\StatamicMux\Mux\MuxService;
use Statamic\Assets\Asset;

class ThumbnailService
{
    protected int $width = 400;

    public function __construct(
        protected MuxService $service,
    ) {}

    public function forAsset(Asset $asset): ?string
    {
        if (! $this->service->getMuxId($asset)) {
            return null;
        }

        // If playback id already exists, generate gif url immediately
        // Otherwise, delegate generation to custom route in the background
        $playbackId = $this->service->getPlaybackId($asset, requestIfMissing: false);

        return $playbackId
            ? $this->service->getGifUrl($playbackId, ['width' => $this->width])
            : cp_route('mux.thumbnail', base64_encode($asset->id()));
    }

    public function generateForAsset(Asset $asset): ?string
    {
        return ($playbackId = $this->service->getPlaybackId($asset))
            ? $this->service->getGifUrl($playbackId, ['width' => $this->width])
            : null;
    }
}
