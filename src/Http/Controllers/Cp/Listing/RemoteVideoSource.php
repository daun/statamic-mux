<?php

namespace Daun\StatamicMux\Http\Controllers\Cp\Listing;

use Daun\StatamicMux\Support\Attribution;
use Illuminate\Support\Carbon;
use MuxPhp\Models\Asset;

/**
 * Thin adapter over a Mux SDK asset (the authoritative remote data), exposing
 * its awkward getters as the normalized values ListingReconciler::normalizeRow()
 * consumes.
 */
class RemoteVideoSource
{
    public function __construct(
        protected Asset $asset,
    ) {}

    public function id(): ?string
    {
        return $this->asset->getId();
    }

    public function duration(): ?float
    {
        return $this->asset->getDuration();
    }

    public function playbackIds(): array
    {
        // An errored asset never produced a usable playback, so we expose none.
        // This removes its public playback URLs, thumbnail and player/embed
        // actions everywhere the row is rendered.
        if ($this->processingStatus() === Asset::STATUS_ERRORED) {
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

    public function processingStatus(): ?string
    {
        return $this->asset->getStatus();
    }

    public function createdAt(): ?string
    {
        $timestamp = $this->asset->getCreatedAt();

        return $timestamp ? Carbon::createFromTimestamp($timestamp)->toIso8601String() : null;
    }

    public function isProxy(): bool
    {
        return Attribution::isProxy($this->asset->getPassthrough());
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
