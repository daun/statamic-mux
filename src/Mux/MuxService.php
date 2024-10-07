<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\Actions\RequestPlaybackId;
use Daun\StatamicMux\Mux\Enums\MuxAudience;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use Daun\StatamicMux\Support\URL;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use MuxPhp\ApiException;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Blink;
use Statamic\Support\Traits\Hookable;

class MuxService
{
    use Hookable;

    public function __construct(
        protected Application $app,
        protected MuxApi $api,
        protected MuxUrls $urls,
        protected PlaceholderService $placeholders,
    ) {
    }

    /**
     * Get the Mux API client.
     */
    public function api(): MuxApi
    {
        return $this->api;
    }

    /**
     * Whether the service is configured.
     */
    public function configured(): bool
    {
        return config('mux.credentials.token_id') && config('mux.credentials.token_secret');
    }

    /**
     * Upload a video asset to Mux.
     */
    public function createMuxAsset(Asset|string $asset, bool $force = false): ?string
    {
        if (is_string($asset)) {
            $asset = AssetFacade::find($asset);
        }

        if ($asset) {
            $muxId = $this->app->make(CreateMuxAsset::class)->handle($asset, $force);
            if ($muxId) {
                MuxAsset::fromAsset($asset)->set('id', $muxId)->save();

                return $muxId;
            }
        } else {
            return null;
        }
    }

    /**
     * Delete a video asset from Mux.
     */
    public function deleteMuxAsset(Asset|string $asset): bool
    {
        if ($asset) {
            $deleted = $this->app->make(DeleteMuxAsset::class)->handle($asset);
            if ($deleted) {
                MuxAsset::fromAsset($asset)->clear()->save();

                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * List existing Mux assets
     */
    public function listMuxAssets(int $limit = 100, int $page = 1)
    {
        return collect($this->api->assets()->listAssets($limit, $page)->getData());
    }

    /**
     * Check if a video asset exists in Mux.
     */
    public function hasExistingMuxAsset(Asset $asset)
    {
        $exists = $this->muxAssetExists($this->getMuxId($asset));
        if ($exists) {
            return true;
        } else {
            MuxAsset::fromAsset($asset)->clear()->save();

            return false;
        }
    }

    /**
     * Check if an asset with given id exists on Mux.
     */
    public function muxAssetExists(?string $muxId): bool
    {
        if (! $muxId) {
            return false;
        }

        try {
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            $actualMuxId = $muxAssetResponse?->getId();

            return $muxId === $actualMuxId;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        }

        return false;
    }

    public function getMuxId(Asset $asset): ?string
    {
        return MuxAsset::fromAsset($asset)->id();
    }

    public function getPlaybackId(Asset $asset, ?MuxPlaybackPolicy $policy = null): mixed
    {
        return MuxAsset::fromAsset($asset)->playbackId($policy)?->id();
    }

    public function getPlaybackPolicy(Asset $asset, ?MuxPlaybackPolicy $policy = null): mixed
    {
        return MuxAsset::fromAsset($asset)->playbackId($policy)?->policy();
    }

    public function getOrRequestPlaybackId(Asset $asset, ?MuxPlaybackPolicy $policy = null): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset, $policy)) {
            return $playbackId;
        }

        $result = $this->app->make(RequestPlaybackId::class)->handle($asset, $policy);
        if ($result) {
            [$playbackId, $playbackPolicy] = $result;
            $muxAsset = MuxAsset::fromAsset($asset);
            $muxAsset->addPlaybackId($playbackId, $playbackPolicy);
            $muxAsset->save();

            return true;
        }

        return $playbackId ?: null;
    }

    public function getPlaybackUrl(Asset $asset, ?array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $params = $params + $this->getDefaultPlaybackModifiers();

            return $this->signUrl($asset, $this->urls->playback($playbackId), MuxAudience::Video, $params);
        } else {
            return null;
        }
    }

    public function getPlaybackToken(Asset $asset, ?array $params = []): ?string
    {
        $params = $params + $this->getDefaultPlaybackModifiers();
        $playbackId = $this->getPlaybackId($asset);

        return ($playbackId && $this->isSigned($asset))
            ? $this->urls->token($playbackId, MuxAudience::Video, $params)
            : null;
    }

    public function getDefaultPlaybackModifiers(): array
    {
        return Arr::wrap(config('mux.playback_modifiers', []));
    }

    public function getThumbnailUrl(Asset $asset, array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $format = $params['format'] ?? 'jpg';
            $params = Arr::except($params, 'format');

            return $this->signUrl($asset, $this->urls->thumbnail($playbackId, $format), MuxAudience::Thumbnail, $params);
        } else {
            return null;
        }
    }

    public function getGifUrl(Asset $asset, array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $format = $params['format'] ?? 'gif';
            $params = Arr::except($params, 'format');

            return $this->signUrl($asset, $this->urls->animated($playbackId, $format), MuxAudience::Gif, $params);
        } else {
            return null;
        }
    }

    public function getPlaceholderDataUri(Asset $asset, array $params = []): string
    {
        $fallback = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $thumbnail = $this->getThumbnailUrl($asset, ['width' => 100] + $params);
        if ($thumbnail) {
            $key = sprintf('%s-%s', $this->getMuxId($asset), md5(json_encode($params)));

            return $this->placeholders->forUrl($thumbnail, $key);
        } else {
            return $fallback;
        }
    }

    public function isSigned(Asset $asset): bool
    {
        return $this->api->hasSignedPlaybackPolicy($this->getPlaybackPolicy($asset));
    }

    public function isPublic(Asset $asset): bool
    {
        return $this->api->hasPublicPlaybackPolicy($this->getPlaybackPolicy($asset));
    }

    protected function signUrl(Asset $asset, string $url, MuxAudience $audience, ?array $params = [], ?int $expiration = null): ?string
    {
        $playbackId = $this->getPlaybackId($asset);

        return ($playbackId && $this->isSigned($asset))
            ? $this->urls->sign($url, $playbackId, $audience, $params, $expiration)
            : URL::withQuery($url, $params);
    }
}
