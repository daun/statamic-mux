<?php

namespace Daun\StatamicMux\Data\Augmentables;

use Statamic\Data\AbstractAugmented;

class AugmentedMuxAsset extends AbstractAugmented
{
    public function keys()
    {
        return [
            'id',
            'playback_ids',
            'playback_id',
            'playback_policy',
            'exists',
        ];
    }
}
