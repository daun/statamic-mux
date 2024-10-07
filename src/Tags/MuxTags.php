<?php

namespace Daun\StatamicMux\Tags;

use Daun\StatamicMux\Tags\Concerns\GetsAssetFromContext;
use Daun\StatamicMux\Tags\Concerns\ReadsMuxData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Tags\Concerns\RendersAttributes;
use Statamic\Tags\Tags;

class MuxTags extends Tags
{
    use GetsAssetFromContext;
    use ReadsMuxData;
    use RendersAttributes;

    protected static $handle = 'mux';

    /**
     * Tag {{ mux:[field] }}
     *
     * Where `field` is the variable containing the video asset
     */
    public function __call($method, $args)
    {
        $tag = explode(':', $this->tag, 2)[1];

        $item = $this->context->value($tag);

        if ($this->isPair) {
            return $this->generate($item);
        } else {
            return $this->getPlaybackId($item);
        }
    }

    /**
     * Tag {{ mux }}.
     *
     * Alternate syntax, where you pass the ID or path directly as a parameter or tag pair content
     */
    public function index()
    {
        if ($this->isPair) {
            return $this->generate();
        } else {
            return $this->getPlaybackId();
        }
    }

    /**
     * Tag {{ mux:generate }} ... {{ /mux:generate }}.
     *
     * Generate Mux playback id and make variables available within the pair.
     */
    public function generate($asset = null): array
    {
        $asset = $this->getAssetFromContext($asset);
        if (! $asset) {
            return [];
        }

        try {
            $muxId = $this->getMuxId($asset);
            $playbackId = $this->getPlaybackId($asset);
            $playbackModifiers = $this->getDefaultPlaybackModifiers();
            $playbackToken = $this->getPlaybackToken($asset, $playbackModifiers);
            $playbackIdSigned = $playbackToken ? "{$playbackId}?token={$playbackToken}" : $playbackId;

            $data = [
                'mux_id' => $muxId,
                'playback_id' => $playbackId,
                'playback_id_signed' => $playbackIdSigned,
                'playback_token' => $playbackToken,
                'playback_url' => $this->getPlaybackUrl($asset),
                'thumbnail' => $this->getThumbnailUrl($asset),
                'placeholder' => $this->getPlaceholderDataUri($asset),
                'gif' => $this->getGifUrl($asset),
                'is_public' => $this->isPublic($asset),
                'is_signed' => $this->isSigned($asset),
            ];

            if ($asset instanceof Augmentable) {
                return array_merge($asset->toAugmentedArray(), $data);
            } else {
                return $data;
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }

        return [];
    }

    /**
     * Tag {{ mux:video }}
     *
     * Return a rendered <mux-video> component of a video.
     */
    public function video(): ?string
    {
        $asset = $this->getAssetFromContext();
        $playbackId = $this->getPlaybackId($asset);
        if (! $playbackId) {
            return null;
        }

        if ($token = $this->getPlaybackToken($asset)) {
            $playbackId = "{$playbackId}?token={$token}";
        } else {
            $playbackAttributes = $this->toHtmlAttributes($this->getDefaultPlaybackModifiers());
        }

        $attributes = collect([
            'playsinline' => true,
            'preload' => 'metadata',
            'poster' => $this->getThumbnailUrl($asset),
            'width' => $asset->width(),
            'height' => $asset->height(),
        ])->merge(
            $this->params->bool('background') ? [
                'autoplay' => true,
                'loop' => true,
                'muted' => true,
            ] : []
        )->merge(
            $playbackAttributes ?? []
        )->merge(
            collect($this->params->all())->except([...$this->assetParams, 'background', 'script'])
        )->whereNotNull()->all();

        $script = $this->params->bool('script')
            ? '<script async src="https://unpkg.com/@mux/mux-video@0"></script>'
            : '';

        return vsprintf(
            '<mux-video playback-id="%s" %s></mux-video> %s',
            [$playbackId, $this->renderAttributes($attributes), $script]
        );
    }

    /**
     * Tag {{ mux:player }}
     *
     * Return a rendered <mux-player> component of a video.
     */
    public function player(): ?string
    {
        $asset = $this->getAssetFromContext();
        $playbackId = $this->getPlaybackId($asset);
        if (! $playbackId) {
            return null;
        }

        if ($token = $this->getPlaybackToken($asset)) {
            $playbackAttributes = ['playback-token' => $token];
        } else {
            $playbackAttributes = $this->toHtmlAttributes($this->getDefaultPlaybackModifiers());
        }

        $attributes = collect([
            'preload' => 'metadata',
            'width' => $asset->width(),
            'height' => $asset->height(),
        ])->merge(
            $playbackAttributes
        )->merge(
            collect($this->params->all())->except([...$this->assetParams, 'script'])
        )->whereNotNull()->all();

        $script = $this->params->bool('script')
            ? '<script async src="https://unpkg.com/@mux/mux-player@2"></script>'
            : '';

        return vsprintf(
            '<mux-player playback-id="%s" %s></mux-player> %s',
            [$playbackId, $this->renderAttributes($attributes), $script]
        );
    }

    /**
     * Tag {{ mux:id }}
     *
     * Return the mux id of a video.
     */
    public function id(): ?string
    {
        return $this->getMuxId();
    }

    /**
     * Tag {{ mux:playback_id }}
     *
     * Return the playback id of a video.
     */
    public function playbackId(): ?string
    {
        return $this->getPlaybackId();
    }

    /**
     * Tag {{ mux:playback_url }}
     *
     * Return the playback url of a video.
     */
    public function playbackUrl(): ?string
    {
        return $this->getPlaybackUrl();
    }

    /**
     * Tag {{ mux:thumbnail [width] [height] [time] }}
     *
     * Return the thumbnail url of a video.
     */
    public function thumbnail(): ?string
    {
        $params = collect($this->params->all())->except($this->assetParams)->all();

        return $this->getThumbnailUrl(null, $params);
    }

    /**
     * Tag {{ mux:gif [width] [height] [start] [end] [fps] }}
     *
     * Return the animated GIF url of a video.
     */
    public function gif(): ?string
    {
        $params = collect($this->params->all())->except($this->assetParams)->all();

        return $this->getGifUrl(null, $params);
    }

    /**
     * Tag {{ mux:placeholder [time] }}
     *
     * Return a blurry placeholder data url of a video.
     */
    public function placeholder(): ?string
    {
        $params = collect($this->params->all())->except($this->assetParams)->all();

        return $this->getPlaceholderDataUri(null, $params);
    }

    /**
     * Turn query_params into html-attributes (snake to kebab case)
     */
    protected function toHtmlAttributes(mixed $params): Collection
    {
        return collect($params)->keyBy(fn ($_, $key) => Str::replace('_', '-', $key));
    }
}
