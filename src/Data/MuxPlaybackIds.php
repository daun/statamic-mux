<?php

namespace Daun\StatamicMux\Data;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class MuxPlaybackIds extends Collection implements Arrayable
{
    public function __construct($items = [])
    {
        $items = Collection::make($items)
            ->map(fn ($id, $policy) => MuxPlaybackId::make($id, $policy))
            ->filter();

        parent::__construct($items);
    }

    public function findWithPolicy(MuxPlaybackPolicy $policy): ?MuxPlaybackId
    {
        return $this->first(fn (MuxPlaybackId $playbackId) => $playbackId->hasPolicy($policy));
    }

    public function findPublic(): ?MuxPlaybackId
    {
        return $this->first(fn (MuxPlaybackId $playbackId) => $playbackId->isPublic());
    }

    public function findSigned(): ?MuxPlaybackId
    {
        return $this->first(fn (MuxPlaybackId $playbackId) => $playbackId->isSigned());
    }

    public function addPlaybackId(string $id, string $policy): ?MuxPlaybackId
    {
        if ($existing = $this->findWithPolicy(MuxPlaybackPolicy::make($policy))) {
            return $existing;
        }

        if ($playbackId = MuxPlaybackId::make($id, $policy)) {
            $this->put($policy, $playbackId);
        }

        return $playbackId ?? null;
    }

    public function toArray(): array
    {
        return array_reduce(
            $this->all(),
            fn ($all, $id) => [...$all, $id->policy() => $id->id()],
            []
        );
    }
}
