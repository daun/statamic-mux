<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;

trait ReadsMuxData
{
    /**
     * Guess/check which policy to use for this video
     */
    protected function guessRequestedPolicy(): ?MuxPlaybackPolicy
    {
        if ($policy = $this->params->get('policy')) {
            return MuxPlaybackPolicy::make($policy);
        }
        if ($this->params->get('signed')) {
            return MuxPlaybackPolicy::Signed;
        }
        if ($this->params->get('public')) {
            return MuxPlaybackPolicy::Public;
        }
        return null;
    }

    /**
     * Get the mux id of a video
     */
    protected function getMuxId($asset = null): ?string
    {
        return ($asset = $this->getAssetFromContext($asset))
            ? Mux::getMuxId($asset)
            : null;
    }

    /**
     * Get the playback id instance of a video
     */
    protected function getPlaybackId($asset = null): ?MuxPlaybackId
    {
        return ($asset = $this->getAssetFromContext($asset))
            ? Mux::getPlaybackId($asset, policy: $this->guessRequestedPolicy())
            : null;
    }

    /**
     * Get the playback url of a video
     */
    protected function getPlaybackUrl($asset = null, ?array $params = []): ?string
    {
        return ($playbackId = $this->getPlaybackId($asset))
            ? Mux::getPlaybackUrl($playbackId, params: $params)
            : null;
    }

    /**
     * Get the playback token of a signed video
     */
    protected function getPlaybackToken($asset = null, ?array $params = []): ?string
    {
        return ($playbackId = $this->getPlaybackId($asset))
            ? Mux::getPlaybackToken($playbackId, params: $params)
            : null;
    }

    /**
     * Get the thumbnail url of a video
     */
    protected function getThumbnailUrl($asset = null, ?array $params = []): ?string
    {
        return ($playbackId = $this->getPlaybackId($asset))
            ? Mux::getThumbnailUrl($playbackId, params: $params)
            : null;
    }

    /**
     * Get the animated GIF url of a video
     */
    protected function getGifUrl($asset = null, ?array $params = []): ?string
    {
        return ($playbackId = $this->getPlaybackId($asset))
            ? Mux::getGifUrl($playbackId, params: $params)
            : null;
    }

    /**
     * Get the placeholder data uri of a video
     */
    protected function getPlaceholderDataUri($asset = null, ?array $params = []): ?string
    {
        return ($playbackId = $this->getPlaybackId($asset))
            ? Mux::getPlaceholderDataUri($playbackId, params: $params)
            : null;
    }

    /**
     * Get the default playback modifiers
     */
    protected function getDefaultPlaybackModifiers(): array
    {
        return Mux::getDefaultPlaybackModifiers();
    }
}
