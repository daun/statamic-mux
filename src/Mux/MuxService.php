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
use MuxPhp\Models\Asset as MuxApiAssetModel;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;
use Statamic\Support\Traits\Hookable;

class MuxService
{
    use Hookable;

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
            $asset = Assets::find($asset);
        }

        if ($asset) {
            if ($muxId = $this->app->make(CreateMuxAsset::class)->handle($asset, $force)) {
                MuxAsset::fromAsset($asset)->clear()->setId($muxId)->save();
            }
        }

        return $muxId ?? null;
    }

    /**
     * Delete a video asset from Mux.
     */
    public function deleteMuxAsset(Asset|string $asset): bool
    {
        if ($asset) {
            $deleted = $this->app->make(DeleteMuxAsset::class)->handle($asset);
            if ($deleted) {
                if ($asset instanceof Asset) {
                    MuxAsset::fromAsset($asset)->clear()->save();
                }

                return true;
            }
        }

        return false;
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
    }

    /**
     * Check if an asset with given id is ready on Mux.
     */
    public function muxAssetIsReady(string $muxId): bool
    {
        try {
            $status = $this->api->assets()->getAsset($muxId)->getData()?->getStatus();

            return $status === MuxApiAssetModel::STATUS_READY;
        } catch (ApiException $e) {
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

    public function getPlaybackUrl(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        $params = $params + $this->getDefaultPlaybackModifiers();

        return $this->signUrl($this->urls->playback($playbackId->id()), $playbackId, MuxAudience::Video, $params);
    }

    public function getThumbnailUrl(MuxPlaybackId $playbackId, array $params = []): ?string
    {
        $format = $params['format'] ?? 'jpg';
        $params = Arr::except($params, 'format');

        return $this->signUrl($this->urls->thumbnail($playbackId->id(), $format), $playbackId, MuxAudience::Thumbnail, $params);
    }

    public function getGifUrl(MuxPlaybackId $playbackId, array $params = []): ?string
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

    protected function signUrl(string $url, MuxPlaybackId $playbackId, MuxAudience $audience, array $params = [], ?int $expiration = null): ?string
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
