<?php

namespace Daun\StatamicMux\Data\Augmentables;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\Data\MuxPlaybackIds;
use Daun\StatamicMux\Facades\Mux;
use Statamic\Data\AbstractAugmented;

class AugmentedMuxAsset extends AbstractAugmented
{
    public function keys()
    {
        return [
            'id',
            'playback_ids',
            'playback_policy',
            'exists'
        ];
    }

    public function id()
    {
        return $this->data->id ?? null;
    }

    public function playbackId(): ?MuxPlaybackId
    {
        return $this->playbackIds()->public()
            ?: $this->playbackIds()->signed()
            ?: null;
    }

    public function playbackIds(): MuxPlaybackIds
    {
        return MuxPlaybackIds::make($this->data->playback_ids ?? []);
    }

    public function exists(): bool
    {
        return $this->id() && Mux::muxAssetExists($this->id());
    }
}
