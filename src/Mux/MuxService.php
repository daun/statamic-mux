<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Concerns\ProcessesHooks;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\Actions\RequestPlaybackId;
use Daun\StatamicMux\Mux\Actions\UpdateMuxAsset;
use Daun\StatamicMux\Mux\Enums\MuxAudience;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use Daun\StatamicMux\Support\URL;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;

class MuxService
{
    use ProcessesHooks;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxUrls $urls,
        protected PlaceholderService $placeholders,
    ) {}

    /**
     * Get the Mux API client.
     */
    public function api(): MuxApi
    {
        return $this->api;
    }

    /**
     * Whether the Mux service is configured with the required credentials.
     */
    public function configured(): bool
    {
        return $this->api->configured();
    }

    /**
     * Upload a video asset to Mux.
     */
    public function createMuxAsset(Asset|string $asset, bool $force = false): ?string
    {
        if (is_string($asset)) {
            $asset = Assets::find($asset);
        }

        if (! $asset) {
            return null;
        }

        return $this->app->make(CreateMuxAsset::class)->handle($asset, $force);
    }

    /**
     * Update data of a video asset on Mux.
     */
    public function updateMuxAsset(Asset|string $asset): bool
    {
        if (is_string($asset)) {
            $asset = Assets::find($asset);
        }

        if (! $asset) {
            return false;
        }

        return $this->app->make(UpdateMuxAsset::class)->handle($asset);
    }

    /**
     * Delete a video asset from Mux.
     */
    public function deleteMuxAsset(Asset|string $asset): bool
    {
        if (! $asset) {
            return false;
        }

        return $this->app->make(DeleteMuxAsset::class)->handle($asset);
    }

    /**
     * Check if a video asset exists in Mux.
     */
    public function hasExistingMuxAsset(Asset $asset)
    {
        $muxId = $this->getMuxId($asset);
        if ($muxId && $this->api->assetExists($muxId)) {
            return true;
        } else {
            MuxAsset::fromAsset($asset)->clear()->save();

            return false;
        }
    }

    /**
     * List existing Mux assets
     */
    public function listMuxAssets(int $limit = 100, int $page = 1, bool $all = false)
    {
        // Paginate to fetch all assets
        if ($all) {
            $assets = collect();
            $new = null;

            do {
                $new = $this->api->assets()->listAssets(100, $page)->getData();
                $assets->push(...$new);
                $page++;
            } while ($new !== [] && ($limit <= 0 || $assets->count() < $limit));

            return $assets;
        }

        return collect($this->api->assets()->listAssets($limit, $page)->getData());
    }

    public function getMuxId(Asset $asset): ?string
    {
        return MuxAsset::fromAsset($asset)->id();
    }

    public function getPlaybackId(Asset $asset, ?MuxPlaybackPolicy $policy = null, bool $requestIfMissing = true): ?MuxPlaybackId
    {
        $policy = $this->sanitizePlaybackPolicy($policy);

        return $requestIfMissing
            ? $this->getOrRequestPlaybackId($asset, $policy)
            : $this->getExistingPlaybackId($asset, $policy);
    }

    protected function getExistingPlaybackId(Asset $asset, ?MuxPlaybackPolicy $policy = null): ?MuxPlaybackId
    {
        $policy = $this->sanitizePlaybackPolicy($policy);

        return MuxAsset::fromAsset($asset)->playbackId($policy);
    }

    protected function getOrRequestPlaybackId(Asset $asset, ?MuxPlaybackPolicy $policy = null): ?MuxPlaybackId
    {
        if (! $this->getMuxId($asset)) {
            return null;
        }

        $policy = $this->sanitizePlaybackPolicy($policy);

        if ($playbackId = $this->getExistingPlaybackId($asset, $policy)) {
            return $playbackId;
        }

        return $this->app->make(RequestPlaybackId::class)->handle($asset, $policy);
    }

    public function getPlaybackUrl(MuxPlaybackId $playbackId, array $params = []): string
    {
        $params = $params + $this->getDefaultPlaybackModifiers();

        return $this->signUrl($this->urls->playback($playbackId->id()), $playbackId, MuxAudience::Video, $params);
    }

    public function getThumbnailUrl(MuxPlaybackId $playbackId, array $params = []): string
    {
        $format = $params['format'] ?? 'jpg';
        $params = Arr::except($params, 'format');

        return $this->signUrl($this->urls->thumbnail($playbackId->id(), $format), $playbackId, MuxAudience::Thumbnail, $params);
    }

    public function getGifUrl(MuxPlaybackId $playbackId, array $params = []): string
    {
        $format = $params['format'] ?? 'gif';
        $params = Arr::except($params, 'format');

        return $this->signUrl($this->urls->animated($playbackId->id(), $format), $playbackId, MuxAudience::Gif, $params);
    }

    public function getPlaceholderDataUri(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        $fallback = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $thumbnail = $this->getThumbnailUrl($playbackId, ['width' => 100] + $params);
        if ($thumbnail) {
            $key = sprintf('%s-%s', $playbackId->id(), md5(json_encode($params)));

            return $this->placeholders->forUrl($thumbnail, $key);
        } else {
            return $fallback;
        }
    }

    protected function getToken(MuxPlaybackId $playbackId, MuxAudience $audience, array $params = []): ?string
    {
        $params = $params + $this->getDefaultPlaybackModifiers();

        return $playbackId->isSigned()
            ? $this->urls->token($playbackId->id(), $audience, $params)
            : null;
    }

    public function getPlaybackToken(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        return $this->getToken($playbackId, MuxAudience::Video, $params);
    }

    public function getThumbnailToken(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        return $this->getToken($playbackId, MuxAudience::Thumbnail, $params);
    }

    public function getStoryboardToken(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        return $this->getToken($playbackId, MuxAudience::Storyboard, $params);
    }

    protected function signUrl(string $url, MuxPlaybackId $playbackId, MuxAudience $audience, array $params = [], ?int $expiration = null): string
    {
        return $playbackId->isSigned()
            ? $this->urls->sign($url, $playbackId->id(), $audience, $params, $expiration)
            : URL::withQuery($url, $params);
    }

    protected function sanitizePlaybackPolicy(?MuxPlaybackPolicy $policy): MuxPlaybackPolicy
    {
        return $policy ?? $this->getDefaultPlaybackPolicy() ?? MuxPlaybackPolicy::Public;
    }

    public function getDefaultPlaybackPolicy(): ?MuxPlaybackPolicy
    {
        return MuxPlaybackPolicy::make(config('mux.playback_policy'));
    }

    public function getDefaultPlaybackModifiers(): array
    {
        return Arr::wrap(config('mux.playback_modifiers', []));
    }
}
