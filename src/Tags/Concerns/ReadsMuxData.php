<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Daun\StatamicMux\Facades\Mux;

trait ReadsMuxData
{

    /**
     * Get the mux id of a video
     */
    protected function getMuxId($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::muxId($asset) : null;
    }

    /**
     * Get the playback id of a video
     */
    protected function getPlaybackId($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::playbackId($asset) : null;
    }

    /**
     * Get the playback url of a video
     */
    protected function getPlaybackUrl($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::playbackUrl($asset) : null;
    }

    /**
     * Get the playback token of a signed video
     */
    protected function getPlaybackToken($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::playbackToken($asset, $params) : null;
    }

    /**
     * Get the thumbnail url of a video
     */
    protected function getThumbnailUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::thumbnail($asset, $params) : null;
    }

    /**
     * Get the animated GIF url of a video
     */
    protected function getGifUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::gif($asset, $params) : null;
    }

    /**
     * Get the placeholder data uri of a video
     */
    protected function getPlaceholderUri($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::placeholder($asset, $params) : null;
    }

    /**
     * Whether this video requires signed playback urls
     */
    protected function isSigned($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::isSigned($asset) : false;
    }

    /**
     * Whether this video generates public playback urls
     */
    protected function isPublic($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);
        return $asset ? Mux::isPublic($asset) : false;
    }

    /**
     * Get the default playback modifiers
     */
    protected function getDefaultPlaybackModifiers(): array
    {
        return Mux::playbackModifiers();
    }
}
