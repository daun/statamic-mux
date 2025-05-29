<?php

namespace Daun\StatamicMux\Tags;

use Daun\StatamicMux\Tags\Concerns\GetsAssetFromContext;
use Daun\StatamicMux\Tags\Concerns\ReadsMuxData;
use Daun\StatamicMux\Tags\Concerns\RendersMuxPlayer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Tags\Tags;

class MuxTags extends Tags
{
    use GetsAssetFromContext;
    use ReadsMuxData;
    use RendersMuxPlayer;

    protected static $handle = 'mux';

    /**
     * Tag {{ mux:[field] }}
     *
     * Where `field` is the variable containing the video asset
     */
    public function wildcard($field)
    {
        if (! $this->context->has($field)) {
            throw new \Exception("Variable [{$field}] does not exist in context.");
        }

        $item = $this->context->value($field);

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
            if (! $playbackId) {
                return [];
            }

            $data = [
                'mux_id' => $muxId,
                'playback_id' => $playbackId?->id(),
                'playback_policy' => $playbackId?->policy(),
                'playback_modifiers' => ($playbackModifiers = $this->getPlaybackModifiers()),
                'playback_url' => $this->getPlaybackUrl($asset),
                'thumbnail' => $this->getThumbnailUrl($asset),
                'gif' => $this->getGifUrl($asset),
                'placeholder' => $this->getPlaceholderDataUri($asset),
                'is_public' => $playbackId?->isPublic(),
                'is_signed' => $playbackId?->isSigned(),
            ];

            if ($playbackId?->isSigned()) {
                $data = $data + [
                    'playback_token' => $this->getPlaybackToken($asset, $playbackModifiers),
                    'thumbnail_token' => $this->getThumbnailToken($asset),
                    'storyboard_token' => $this->getStoryboardToken($asset),
                ];
            }

            return array_merge($asset->toAugmentedArray(), $data);
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
        return $this->component('mux-video');
    }

    /**
     * Tag {{ mux:player }}
     *
     * Return a rendered <mux-player> component of a video.
     */
    public function player(): ?string
    {
        return $this->component('mux-player');
    }

    /**
     * Tag {{ mux:embed }}
     *
     * Return a rendered <iframe> html embed.
     */
    public function embed(): ?string
    {
        return $this->component('mux-embed');
    }

    /**
     * Render a custom-element view.
     */
    protected function component(string $view): ?string
    {
        $data = $this->generate();
        if (empty($data)) {
            return null;
        }

        $playbackModifiers = collect($this->getPlaybackModifiers());
        $playerAttributes = collect($this->getPlayerAttributes());

        $htmlAttributes = collect($this->params->all())
            ->except($this->assetParams)
            ->except($playbackModifiers->keys())
            ->except(['script', 'public', 'signed', 'background'])
            ->when($this->params->bool('background'), fn ($attr) => $attr->merge(['autoplay' => true, 'loop' => true, 'muted' => true])
            );

        $viewdata = $this->context
            ->merge($data)
            ->merge(['script' => $this->params->bool('script', false)])
            ->merge(['attributes' => $this->toHtmlAttributes($htmlAttributes)])
            ->merge(['playback_modifiers' => $this->toHtmlAttributes($playbackModifiers)])
            ->merge(['player_query' => Arr::query($playbackModifiers->merge($playerAttributes)->all())]);

        return view("statamic-mux::{$view}", $viewdata)->render();
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
        return $this->getPlaybackId()?->id();
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
        return $this->getThumbnailUrl(params: $this->params->except($this->assetParams)->all());
    }

    /**
     * Tag {{ mux:gif [width] [height] [start] [end] [fps] }}
     *
     * Return the animated GIF url of a video.
     */
    public function gif(): ?string
    {
        return $this->getGifUrl(params: $this->params->except($this->assetParams)->all());
    }

    /**
     * Tag {{ mux:placeholder [time] }}
     *
     * Return a blurry placeholder data url of a video.
     */
    public function placeholder(): ?string
    {
        return $this->getPlaceholderDataUri(params: $this->params->except($this->assetParams)->all());
    }

    /**
     * Turn query_params into html-attributes (snake to kebab case)
     */
    protected function toHtmlAttributes(mixed $params): array
    {
        return collect($params)
            ->keyBy(fn ($_, $key) => Str::replace('_', '-', $key))
            ->filter(fn ($_, $key) => $key)
            ->all();
    }
}
