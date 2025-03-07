<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Illuminate\Support\Str;

trait RendersMuxPlayer
{
    protected $playbackAttributes = [
        'autoplay',
        'crossorigin',
        'loop',
        'muted',
        'poster',
        'preload',
        'volume',
        'playbackrate',
        'env-key',
        'debug',
        'no-volume-pref',
        'disable-tracking',
        'disable-cookies',
        'playback-id',
        'prefer-playback',
        'max-resolution',
        'min-resolution',
        'rendition-order',
        'program-start-time',
        'program-end-time',
        'asset-start-time',
        'asset-end-time',
        'metadata-video-id',
        'metadata-video-title',
        'metadata-viewer-user-id',
        'metadata-*',
        'beacon-collection-domain',
        'custom-domain',
        'stream-type',
        'default-stream-type',
        'target-live-window',
        'start-time',
        'default-hidden-captions',
        'default-duration',
        'primary-color',
        'secondary-color',
        'accent-color',
        'forward-seek-offset',
        'backward-seek-offset',
        'playback-token',
        'thumbnail-token',
        'storyboard-token',
        'drm-token',
        'storyboard-src',
        'thumbnail-time',
        'audio',
        'nohotkeys',
        'hotkeys',
        'playbackrates',
        'default-show-remaining-time',
        'title',
        'placeholder',
        'cast-receiver',
        'no-tooltips',
        'player-init-time',
        'proudly-display-mux-badge',
    ];

    protected function isPlaybackAttribute(string $param): bool
    {
        return isset($this->playbackAttributes[$param])
            || isset($this->playbackAttributes[Str::snake($param)]);
    }
}
