<?php

namespace Daun\StatamicMux\Thumbnails;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Mux\MuxService;
use Statamic\Assets\Asset;

class ThumbnailService
{
    protected bool $enabled;
    protected bool $animated;
    protected int $width = 400;

    public function __construct(
        protected MuxService $service,
    ) {
        $this->enabled = (bool) config('mux.cp_thumbnails.enabled', true);
        $this->animated = (bool) config('mux.cp_thumbnails.animated', true);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function forAsset(Asset $asset): ?string
    {
        if (! $this->service->getMuxId($asset)) {
            return null;
        }

        // If playback id already exists, generate gif url immediately
        // Otherwise, delegate generation to custom route in the background
        return ($playbackId = $this->service->getPlaybackId($asset, requestIfMissing: false))
            ? $this->getThumbnailUrl($playbackId)
            : cp_route('mux.thumbnail', base64_encode($asset->id()));
    }

    public function generateForAsset(Asset $asset): ?string
    {
        return ($playbackId = $this->service->getPlaybackId($asset))
            ? $this->getThumbnailUrl($playbackId)
            : null;
    }

    protected function getThumbnailUrl(MuxPlaybackId $playbackId): string
    {
        return $this->animated
            ? $this->service->getGifUrl($playbackId, ['width' => $this->width])
            : $this->service->getThumbnailUrl($playbackId, ['width' => $this->width]);
    }
}
