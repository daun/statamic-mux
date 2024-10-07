<?php

namespace Daun\StatamicMux\Data;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Illuminate\Support\Collection;

class MuxPlaybackIds extends Collection
{
    public function __construct($items = [])
    {
        $items = Collection::make($items)
            ->map(fn ($item) => MuxPlaybackId::make($item))
            ->filter();

        parent::__construct($items);
    }

    public function findWithPolicy(MuxPlaybackPolicy $policy): ?MuxPlaybackId
    {
        return $this->first(fn ($playbackId) => $playbackId->hasPolicy($policy));
    }

    public function findPublic(): ?MuxPlaybackId
    {
        return $this->first(fn ($playbackId) => $playbackId->isPublic());
    }

    public function findSigned(): ?MuxPlaybackId
    {
        return $this->first(fn ($playbackId) => $playbackId->isSigned());
    }

    public function addPlaybackId(array $item): ?MuxPlaybackId
    {
        if ($existing = $this->findWithPolicy(MuxPlaybackPolicy::make($item['policy'] ?? null))) {
            return $existing;
        }

        if ($playbackId = MuxPlaybackId::make($item)) {
            $this->push($playbackId);
        }

        return $playbackId ?? null;
    }

    public function toArray(): array
    {
        return $this->map(fn ($item) => $item->toArray())->all();
    }
}
