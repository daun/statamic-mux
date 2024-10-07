<?php

namespace Daun\StatamicMux\Tags\Concerns;

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
        $asset = $this->getAssetFromContext($asset);

        return $asset ? Mux::getMuxId($asset) : null;
    }

    /**
     * Get the playback id of a video
     */
    protected function getPlaybackId($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaybackId($asset, policy: $policy)?->id() : null;
    }

    /**
     * Get the playback url of a video
     */
    protected function getPlaybackUrl($asset = null): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaybackUrl($asset, policy: $policy) : null;
    }

    /**
     * Get the playback token of a signed video
     */
    protected function getPlaybackToken($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaybackToken($asset, policy: $policy, params: $params) : null;
    }

    /**
     * Get the thumbnail url of a video
     */
    protected function getThumbnailUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getThumbnailUrl($asset, policy: $policy, params: $params) : null;
    }

    /**
     * Get the animated GIF url of a video
     */
    protected function getGifUrl($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getGifUrl($asset, policy: $policy, params: $params) : null;
    }

    /**
     * Get the placeholder data uri of a video
     */
    protected function getPlaceholderDataUri($asset = null, ?array $params = []): ?string
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaceholderDataUri($asset, policy: $policy, params: $params) : null;
    }

    /**
     * Whether this video requires signed playback urls
     */
    protected function isSigned($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaybackId($asset, policy: $policy)?->isSigned() : false;
    }

    /**
     * Whether this video generates public playback urls
     */
    protected function isPublic($asset = null): bool
    {
        $asset = $this->getAssetFromContext($asset);
        $policy = $this->guessRequestedPolicy();

        return $asset ? Mux::getPlaybackId($asset, policy: $policy)?->isPublic() : false;
    }

    /**
     * Get the default playback modifiers
     */
    protected function getDefaultPlaybackModifiers(): array
    {
        return Mux::getDefaultPlaybackModifiers();
    }
}
