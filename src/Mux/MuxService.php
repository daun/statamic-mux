<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\Actions\RequestPlaybackId;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use Daun\StatamicMux\Support\MirrorField;
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
                $muxAsset = new MuxAsset(['id' => $muxId], $asset);
                $muxAsset->save();

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
                $muxAsset = new MuxAsset(['id' => null], $asset);
                $muxAsset->clear();
                $muxAsset->save();

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
            $this->clear($asset);

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

            return $muxAssetResponse?->getId() === $muxId;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        }

        return false;
    }

    protected function getMuxId(Asset $asset): ?string
    {
        return MuxAsset::fromAsset($asset)->id();
    }

    protected function getPlaybackId(Asset $asset): mixed
    {
        return MuxAsset::fromAsset($asset)->playbackId()?->id();
    }

    protected function getPlaybackPolicy(Asset $asset): mixed
    {
        return MuxAsset::fromAsset($asset)->playbackId()?->policy();
    }

    protected function getOrRequestPlaybackId(Asset $asset): ?string
    {
        if ($playbackId = $this->getPlaybackId($asset)) {
            return $playbackId;
        }

        $result = $this->app->make(RequestPlaybackId::class)->handle($asset);
        if ($result) {
            [$playbackId, $playbackPolicy] = $result;
            $this->set($asset, ['playback_id' => $playbackId, 'playback_policy' => $playbackPolicy]);

            $muxAsset = new MuxAsset(['id' => null], $asset);
            $muxAsset->clear();
            $muxAsset->save();

            return true;
        }

        return $playbackId ?: null;
    }

    public function muxId(Asset $asset): ?string
    {
        return $this->getMuxId($asset);
    }

    public function playbackId(Asset $asset): ?string
    {
        return $this->getOrRequestPlaybackId($asset);
    }

    public function playbackUrl(Asset $asset, ?array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $params = $params + $this->playbackModifiers();

            return $this->sign($asset, $this->urls->playback($playbackId), MuxAudience::Video, $params);
        } else {
            return null;
        }
    }

    public function playbackToken(Asset $asset, ?array $params = []): ?string
    {
        $params = $params + $this->playbackModifiers();
        $playbackId = $this->getPlaybackId($asset);

        return ($playbackId && $this->isSigned($asset))
            ? $this->urls->token($playbackId, MuxAudience::Video, $params)
            : null;
    }

    public function playbackModifiers(): array
    {
        return Arr::wrap(config('mux.playback_modifiers', []));
    }

    public function thumbnail(Asset $asset, array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $format = $params['format'] ?? 'jpg';
            $params = Arr::except($params, 'format');

            return $this->sign($asset, $this->urls->thumbnail($playbackId, $format), MuxAudience::Thumbnail, $params);
        } else {
            return null;
        }
    }

    public function gif(Asset $asset, array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $format = $params['format'] ?? 'gif';
            $params = Arr::except($params, 'format');

            return $this->sign($asset, $this->urls->animated($playbackId, $format), MuxAudience::Gif, $params);
        } else {
            return null;
        }
    }

    public function placeholder(Asset $asset, array $params = []): string
    {
        $fallback = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $thumbnail = $this->thumbnail($asset, ['width' => 100] + $params);
        if ($thumbnail) {
            $key = sprintf('%s-%s', $this->muxId($asset), md5(json_encode($params)));

            return $this->placeholders->forUrl($thumbnail, $key);
        } else {
            return $fallback;
        }
    }

    protected function data(Asset $asset): mixed
    {
        $namespace = $this->namespace($asset);

        return $asset->get($namespace, []);
    }

    protected function save(Asset $asset, ?array $data): void
    {
        $namespace = $this->namespace($asset);
        $asset->set($namespace, $data ?? []);
        $asset->saveQuietly();
    }

    protected function set(Asset $asset, ?array $data): void
    {
        $this->save($asset, ($data ?? []) + $this->data($asset));
    }

    protected function get(Asset $asset, string $key, mixed $default = null): mixed
    {
        return $this->data($asset)[$key] ?? $default;
    }

    protected function clear(Asset $asset): void
    {
        $namespace = $this->namespace($asset);
        $asset->set($namespace, []);
        $asset->saveQuietly();
    }

    public function namespace(Asset $asset): ?string
    {
        $container = $asset->container()->handle();

        return Blink::once("mux-namespace-{$container}", fn () => MirrorField::getFromBlueprint($asset)?->handle());
    }

    public function isSigned(Asset $asset): bool
    {
        return $this->api->hasSignedPlaybackPolicy($this->getPlaybackPolicy($asset));
    }

    public function isPublic(Asset $asset): bool
    {
        return $this->api->hasPublicPlaybackPolicy($this->getPlaybackPolicy($asset));
    }

    protected function sign(Asset $asset, string $url, MuxAudience $audience, ?array $params = [], ?int $expiration = null): ?string
    {
        $playbackId = $this->getPlaybackId($asset);

        return ($playbackId && $this->isSigned($asset))
            ? $this->urls->sign($url, $playbackId, $audience, $params, $expiration)
            : URL::withQuery($url, $params);
    }
}
