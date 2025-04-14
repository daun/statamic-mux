<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Illuminate\Support\Str;

trait RendersMuxPlayer
{
    protected $playbackModifiers = [
        'max_resolution',
        'min_resolution',
        'rendition_order',
        'redundant_streams',
        'default_subtitles_lang',
        'roku_trick_play',
    ];

    protected $playerAttributes = [
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
        // 'max-resolution',
        // 'min-resolution',
        // 'rendition-order',
        'program-start-time',
        'program-end-time',
        'asset-start-time',
        'asset-end-time',
        'metadata-video-id',
        'metadata-video-title',
        'metadata-viewer-user-id',
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
    ];

    protected function isPlaybackModifier(string $param): bool
    {
        return in_array($param, $this->playbackModifiers)
            || in_array(Str::snake($param), $this->playbackModifiers);
    }

    protected function isPlayerAttribute(string $param): bool
    {
        return in_array($param, $this->playerAttributes)
            || in_array(Str::snake($param), $this->playerAttributes);
    }
}
