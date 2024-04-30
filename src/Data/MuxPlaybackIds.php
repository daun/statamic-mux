<?php

namespace Daun\StatamicMux\Data;

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

    public function public(): ?MuxPlaybackId
    {
        return $this->first(fn ($playbackId) => $playbackId->public());
    }

    public function signed(): ?MuxPlaybackId
    {
        return $this->first(fn ($playbackId) => $playbackId->signed());
    }
}
