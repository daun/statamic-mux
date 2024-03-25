<?php

namespace Daun\StatamicMux\Data;

use Illuminate\Support\Collection;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Data\HasAugmentedData;

class MuxPlaybackIds extends Collection implements Augmentable
{
    use HasAugmentedData;

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
