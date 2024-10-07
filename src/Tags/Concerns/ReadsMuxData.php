<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Facades\Mux;

trait ReadsMuxData
{
    /**
     * Get the mux id of a video
     */
    protected function getMuxId($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getMuxId($asset) : null;
    }

    /**
     * Get the playback id of a video
     */
    protected function getPlaybackId($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaybackId($asset)?->id() : null;
    }

    /**
     * Get the playback url of a video
     */
    protected function getPlaybackUrl($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaybackUrl($asset) : null;
    }

    /**
     * Get the playback token of a signed video
     */
    protected function getPlaybackToken($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaybackToken($asset, $params) : null;
    }

    /**
     * Get the thumbnail url of a video
     */
    protected function getThumbnailUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getThumbnailUrl($asset, $params) : null;
    }

    /**
     * Get the animated GIF url of a video
     */
    protected function getGifUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getGifUrl($asset, $params) : null;
    }

    /**
     * Get the placeholder data uri of a video
     */
    protected function getPlaceholderDataUri($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaceholderDataUri($asset, $params) : null;
    }

    /**
     * Whether this video requires signed playback urls
     */
    protected function isSigned($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaybackId($asset)?->isSigned() : false;
    }

    /**
     * Whether this video generates public playback urls
     */
    protected function isPublic($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getPlaybackId($asset)?->isPublic() : false;
    }

    /**
     * Get the default playback modifiers
     */
    protected function getDefaultPlaybackModifiers(): array
    {
        return Mux::getDefaultPlaybackModifiers();
    }
}
