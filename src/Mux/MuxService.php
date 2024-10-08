<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
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
     * Check if a video asset exists in Mux.
     */
    public function hasExistingMuxAsset(Asset $asset)
    {
        $muxId = $this->getMuxId($asset);
        if ($muxId && $this->muxAssetExists($muxId)) {
            return true;
        } else {
            MuxAsset::fromAsset($asset)->clear()->save();

            return false;
        }
    }

    /**
     * Check if an asset with given id exists on Mux.
     */
    public function muxAssetExists(string $muxId): bool
    {
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

    /**
     * List existing Mux assets
     */
    public function listMuxAssets(int $limit = 100, int $page = 1)
    {
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

        $result = $this->app->make(RequestPlaybackId::class)->handle($asset, $policy);
        if ($result) {
            [$id, $policy] = $result;
            if ($id && $policy) {
                $muxAsset = MuxAsset::fromAsset($asset);
                $playbackId = $muxAsset->addPlaybackId($id, $policy);
                $muxAsset->save();

                return $playbackId;
            }
        }

        return null;
    }

    public function getPlaybackUrl(Asset $asset, ?MuxPlaybackPolicy $policy = null, array $params = []): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset, $policy)) {
            $params = $params + $this->getDefaultPlaybackModifiers();

            return $this->signUrl($this->urls->playback($playbackId->id()), $playbackId, MuxAudience::Video, $params);
        } else {
            return null;
        }
    }

    public function getPlaybackToken(Asset $asset, ?MuxPlaybackPolicy $policy = null, array $params = []): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset, $policy)) {
            $params = $params + $this->getDefaultPlaybackModifiers();

            return $playbackId->isSigned()
                ? $this->urls->token($playbackId->id(), MuxAudience::Video, $params)
                : null;
        } else {
            return null;
        }
    }

    public function getThumbnailUrl(Asset $asset, ?MuxPlaybackPolicy $policy = null, array $params = []): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset, $policy)) {
            $format = $params['format'] ?? 'jpg';
            $params = Arr::except($params, 'format');

            return $this->signUrl($this->urls->thumbnail($playbackId->id(), $format), $playbackId, MuxAudience::Thumbnail, $params);
        } else {
            return null;
        }
    }

    public function getGifUrl(Asset $asset, ?MuxPlaybackPolicy $policy = null, array $params = []): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset, $policy)) {
            $format = $params['format'] ?? 'gif';
            $params = Arr::except($params, 'format');

            return $this->signUrl($this->urls->animated($playbackId->id(), $format), $playbackId, MuxAudience::Gif, $params);
        } else {
            return null;
        }
    }

    public function getPlaceholderDataUri(Asset $asset, ?MuxPlaybackPolicy $policy = null, array $params = []): string
    {
        $fallback = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $thumbnail = $this->getThumbnailUrl($asset, $policy, ['width' => 100] + $params);
        if ($thumbnail) {
            $key = sprintf('%s-%s', $this->getMuxId($asset), md5(json_encode($params)));

            return $this->placeholders->forUrl($thumbnail, $key);
        } else {
            return $fallback;
        }
    }

    protected function signUrl(string $url, MuxPlaybackId $playbackId, MuxAudience $audience, array $params = [], ?int $expiration = null): ?string
    {
        return $playbackId->isSigned()
            ? $this->urls->sign($url, $playbackId->id(), $audience, $params, $expiration)
            : URL::withQuery($url, $params);
    }

    protected function sanitizePlaybackPolicy(?MuxPlaybackPolicy $policy): MuxPlaybackPolicy
    {
        $default = $this->getDefaultPlaybackPolicy();

        return MuxPlaybackPolicy::make($policy) ?? MuxPlaybackPolicy::make($default) ?? MuxPlaybackPolicy::Public;
    }

    public function getDefaultPlaybackPolicy(): ?string
    {
        return config('mux.playback_policy', null);
    }

    public function getDefaultPlaybackModifiers(): array
    {
        return Arr::wrap(config('mux.playback_modifiers', []));
    }
}
