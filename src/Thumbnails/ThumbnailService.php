<?php

namespace Daun\StatamicMux\Thumbnails;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Mux\MuxService;
use Statamic\Assets\Asset;
use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;
use Statamic\Http\Resources\CP\Assets\FolderAsset as FolderAssetResource;

class ThumbnailService
{
    protected int $size = 400;

    public function __construct(
        public MuxService $service,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('mux.cp_thumbnails.enabled', true);
    }

    public function animated(): bool
    {
        return (bool) config('mux.cp_thumbnails.animated', true);
    }

    public function forAsset(Asset $asset): ?string
    {
        if (! $this->service->getMuxId($asset)) {
            return null;
        }

        // If playback id already exists, generate gif url immediately
        // Otherwise, delegate generation to custom route in the background
        return ($playbackId = $this->service->getPlaybackId($asset, requestIfMissing: false))
            ? $this->forPlaybackId($playbackId, $asset->orientation())
            : cp_route('mux.thumbnail', base64_encode($asset->id()));
    }

    public function generateForAsset(Asset $asset): ?string
    {
        return ($playbackId = $this->service->getPlaybackId($asset))
            ? $this->forPlaybackId($playbackId, $asset->orientation())
            : null;
    }

    public function createHooks(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $self = $this;

        AssetResource::hook('asset', function ($payload, $next) use ($self) {
            /** @phpstan-ignore-next-line */
            $resource = $this->resource;

            if ($resource instanceof Asset && $self->service->getMuxId($resource)) {
                $payload->data->thumbnail = $self->forAsset($resource) ?? $payload->data->thumbnail;
            }

            return $next($payload);
        });

        FolderAssetResource::hook('asset', function ($payload, $next) use ($self) {
            /** @phpstan-ignore-next-line */
            $resource = $this->resource;

            if ($resource instanceof Asset && $self->service->getMuxId($resource)) {
                $payload->data->thumbnail = $self->forAsset($resource) ?? $payload->data->thumbnail;
            }

            return $next($payload);
        });
    }

    public function forPlaybackId(MuxPlaybackId $playbackId, string $orientation = 'landscape', ?int $size = null): string
    {
        $size ??= $this->size;

        $params = $orientation === 'landscape'
            ? ['width' => $size, 'format' => 'webp']
            : ['height' => $size, 'format' => 'webp'];

        return $this->animated()
            ? $this->service->getGifUrl($playbackId, $params)
            : $this->service->getThumbnailUrl($playbackId, $params);
    }
}
