<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetDeletedFromMux;
use Daun\StatamicMux\Events\AssetDeletingFromMux;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Events\AssetUploadingToMux;
use Daun\StatamicMux\Features\Mirror;
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

        if (! $asset) {
            return null;
        }
        if (! $asset->isVideo()) {
            return null;
        }

        $existingMuxAsset = MuxAsset::fromAsset($asset);
        if (! $force && $existingMuxAsset->existsOnMux()) {
            return null;
        }

        if (AssetUploadingToMux::dispatch($asset) === false) {
            return false;
        }

        try {
            if ($this->app->isLocal() || $asset->container()->private()) {
                $muxId = $this->uploadMuxAsset($asset);
            } else {
                $muxId = $this->ingestMuxAsset($asset);
            }
        } catch (\Throwable $th) {
            throw new \Exception("Failed to upload video to Mux: {$th->getMessage()}");
        }

        if ($muxId) {
            AssetUploadedToMux::dispatch($asset, $muxId);
        }

        $muxAsset = new MuxAsset(['id' => $muxId], $asset);
        $muxAsset->save();

        return $muxId;
    }

    /**
     * Upload a video asset to Mux using a direct upload link.
     */
    protected function uploadMuxAsset(Asset $asset): ?string
    {
        $request = $this->api->createUploadRequest([
            'passthrough' => $this->getAssetIdentifier($asset),
        ]);
        $muxUpload = $this->api->directUploads()->createDirectUpload($request)->getData();
        $uploadId = $muxUpload->getId();

        $this->api->handleDirectUpload($muxUpload, $asset->contents());

        $muxUpload = $this->api->directUploads()->getDirectUpload($uploadId)->getData();
        $muxId = $muxUpload?->getAssetId();

        return $muxId;
    }

    /**
     * Upload a video asset to Mux using ingestion from a public url.
     */
    protected function ingestMuxAsset(Asset $asset): ?string
    {
        $request = $this->api->createAssetRequest([
            'input' => $this->api->input(['url' => $asset->absoluteUrl()]),
            'passthrough' => $this->getAssetIdentifier($asset),
        ]);
        $muxAssetResponse = $this->api->assets()->createAsset($request)->getData();
        $muxId = $muxAssetResponse?->getId();

        return $muxId;
    }

    /**
     * Delete a video asset from Mux.
     */
    public function deleteMuxAsset(Asset|string $asset): bool
    {
        if (! $asset) {
            return false;
        }

        if (is_string($asset)) {
            $muxId = $asset;
            try {
                $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
                if ($this->wasAssetCreatedByAddon($muxAssetResponse)) {
                    $this->api->assets()->deleteAsset($muxId);

                    return true;
                }
            } catch (\Throwable $th) {
            }

            return false;
        }

        if (! $asset->isVideo()) {
            return false;
        }

        $muxId = $this->getMuxId($asset);
        if (! $muxId) {
            return false;
        }

        if (AssetDeletingFromMux::dispatch($asset, $muxId) === false) {
            return false;
        }

        try {
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            if ($this->wasAssetCreatedByAddon($muxAssetResponse)) {
                $this->api->assets()->deleteAsset($muxId);
            } else {
                return false;
            }
        } catch (\Throwable $th) {
        }

        AssetDeletedFromMux::dispatch($asset, $muxId);

        $muxAsset = new MuxAsset(['id' => null], $asset);
        $muxAsset->clear();
        $muxAsset->save();

        return true;
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
    public function hasExistingMuxAsset(Asset $asset, bool $deleteIfMissing = true)
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

    /**
     * Get additional data to pass through to Mux.
     */
    protected function getAssetIdentifier(Asset $asset): string
    {
        return "statamic::{$asset->id()}";
    }

    /**
     * Check if this asset was created by this addon.
     */
    protected function wasAssetCreatedByAddon(mixed $muxAsset): bool
    {
        $identifier = $muxAsset['passthrough'] ?? $muxAsset ?? '';

        return is_string($identifier) && str_starts_with($identifier, 'statamic::');
    }

    protected function getMuxId(Asset $asset): ?string
    {
        return MuxAsset::fromAsset($asset)->id();
    }

    protected function getPlaybackId(Asset $asset): mixed
    {
        return MuxAsset::fromAsset($asset)->playbackId();
    }

    protected function getPlaybackPolicy(Asset $asset): mixed
    {
        return $this->get($asset, 'playback_policy');
    }

    protected function getOrRequestPlaybackId(Asset $asset): ?string
    {
        $muxId = $this->getMuxId($asset);
        if (! $muxId) {
            return null;
        }

        $playbackId = $this->getPlaybackId($asset);
        if ($playbackId) {
            return $playbackId;
        }

        if (! $this->hasExistingMuxAsset($asset)) {
            return null;
        }

        try {
            $muxAssetResponse = $this->api->assets()->getAsset($muxId)->getData();
            $playbackInstances = $muxAssetResponse->getPlaybackIds();
        } catch (\Throwable $th) {
        }

        $publicPlaybackInstances = array_filter(
            $playbackInstances ?? [],
            fn ($instance) => $this->api->hasPublicPlaybackPolicy($instance)
        );

        $playbackInstance = ($publicPlaybackInstances[0] ?? $playbackInstances[0] ?? null);

        $playbackId = $playbackInstance?->getId();
        $playbackPolicy = $playbackInstance?->getPolicy();

        $this->set($asset, ['playback_id' => $playbackId, 'playback_policy' => $playbackPolicy]);

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

            return $this->signUrl($asset, "https://stream.mux.com/{$playbackId}.m3u8", MuxAudience::Video, $params);
        } else {
            return null;
        }
    }

    public function playbackToken(Asset $asset, ?array $params = []): ?string
    {
        $params = $params + $this->playbackModifiers();

        return $this->getAudienceToken($asset, MuxAudience::Video, $params);
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

            return $this->signUrl($asset, "https://image.mux.com/{$playbackId}/thumbnail.{$format}", MuxAudience::Thumbnail, $params);
        } else {
            return null;
        }
    }

    public function gif(Asset $asset, array $params = []): ?string
    {
        if ($playbackId = $this->getOrRequestPlaybackId($asset)) {
            $format = $params['format'] ?? 'gif';
            $params = Arr::except($params, 'format');

            return $this->signUrl($asset, "https://image.mux.com/{$playbackId}/animated.{$format}", MuxAudience::Gif, $params);
        } else {
            return null;
        }
    }

    public function placeholder(Asset $asset, array $params = []): string
    {
        $fallback = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $thumbnail = $this->thumbnail($asset, ['width' => 100] + $params);
        if ($thumbnail) {
            $key = $this->muxId($asset).'-'.md5(json_encode($params));

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
        // ray('before save', $namespace, $asset->get($namespace));
        $asset->set($namespace, $data ?? []);
        $asset->saveQuietly();
        // ray('after save', $namespace, $asset->get($namespace));
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

        return Blink::once("mux-namespace-{$container}", fn () => Mirror::getMirrorField($asset));
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
        $token = $this->getAudienceToken($asset, $audience, $params, $expiration);
        if ($token) {
            return URL::withQuery($url, ['token' => $token]);
        } else {
            return URL::withQuery($url, $params);
        }
    }

    protected function getAudienceToken(Asset $asset, MuxAudience $audience, ?array $params, ?int $expiration = null): ?string
    {
        if (! $this->isSigned($asset)) {
            return null;
        }

        $playbackId = $this->getPlaybackId($asset);

        return $this->urls->getToken($playbackId, $audience, $params ?? [], $expiration);
    }
}
