<?php

namespace Daun\StatamicMux\Events;

use Statamic\Assets\Asset;
use Statamic\Events\Event;

class AssetDeletedFromMux extends Event
{
    public function __construct(
        public Asset $asset,
        public string $muxId
    ) {}
}
