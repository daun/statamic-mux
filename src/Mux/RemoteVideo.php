<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Support\Attribution;
use Illuminate\Support\Carbon;
use MuxPhp\Models\Asset;

/**
 * Thin adapter over a Mux SDK asset (the authoritative remote data), exposing
 * its awkward getters as normalized values.
 */
class RemoteVideo
{
    public function __construct(
        protected Asset $asset,
    ) {}

    public static function make(Asset $asset): self
    {
        return new self($asset);
    }

    public function asset(): Asset
    {
        return $this->asset;
    }

    public function id(): ?string
    {
        return $this->asset->getId();
    }

    public function duration(): ?float
    {
        $duration = $this->asset->getDuration();

        return is_numeric($duration) ? (float) $duration : null;
    }

    public function status(): ?string
    {
        return $this->asset->getStatus();
    }

    public function isReady(): bool
    {
        return $this->status() === Asset::STATUS_READY;
    }

    public function createdAt(): ?Carbon
    {
        $timestamp = $this->asset->getCreatedAt();

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    public function resolutionTier(): ?string
    {
        return $this->asset->getResolutionTier();
    }

    public function maxResolutionTier(): ?string
    {
        return $this->asset->getMaxResolutionTier();
    }

    public function isTest(): bool
    {
        return (bool) $this->asset->getTest();
    }

    public function title(): ?string
    {
        return $this->asset->getMeta()?->getTitle();
    }

    public function passthrough(): ?string
    {
        return $this->asset->getPassthrough();
    }

    public function creatorId(): ?string
    {
        return $this->metaValue($this->asset->getMeta()?->getCreatorId());
    }

    public function externalId(): ?string
    {
        return $this->metaValue($this->asset->getMeta()?->getExternalId());
    }

    public function isProxy(): bool
    {
        return Attribution::isProxy($this->passthrough());
    }

    public function proxyParentId(): ?string
    {
        return Attribution::proxyParentId($this->passthrough());
    }

    /**
     * Display aspect ratio as a single number. The SDK reports it as "16:9".
     */
    public function aspectRatio(): ?float
    {
        $value = $this->asset->getAspectRatio();

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, ':')) {
            [$width, $height] = array_map('floatval', explode(':', $value, 2));

            return $height > 0 ? $width / $height : null;
        }

        return null;
    }

    /**
     * Raw aspect ratio string, for display.
     */
    public function aspectRatioLabel(): ?string
    {
        return $this->asset->getAspectRatio();
    }

    public function playbackIds(): array
    {
        // An errored asset never produced a usable playback, so we expose none.
        // This removes its public playback URLs, thumbnail and player/embed
        // actions everywhere the row is rendered.
        if ($this->status() === Asset::STATUS_ERRORED) {
            return [];
        }

        return collect($this->asset->getPlaybackIds() ?? [])
            ->map(fn ($playbackId) => [
                'id' => $playbackId->getId(),
                'policy' => static::normalizePolicy($playbackId->getPolicy()),
            ])
            ->filter(fn ($playbackId) => $playbackId['id'] !== null)
            ->values()
            ->all();
    }

    protected function metaValue(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }

    /**
     * The SDK may hand us a policy as a backed enum, a value object, or a string.
     */
    protected static function normalizePolicy(mixed $policy): ?string
    {
        if ($policy === null) {
            return null;
        }

        if ($policy instanceof \BackedEnum) {
            return (string) $policy->value;
        }

        if (is_object($policy) && method_exists($policy, 'getValue')) {
            return $policy->getValue();
        }

        return (string) $policy;
    }
}
