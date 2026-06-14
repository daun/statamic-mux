<?php

namespace Daun\StatamicMux\Events;

use Statamic\Assets\Asset;
use Statamic\Events\Event;

class AssetUploadingToMux extends Event
{
    public function __construct(
        public Asset $asset
    ) {}

    /**
     * Dispatch and halt on first non-null listener response.
     */
    public static function dispatch()
    {
        return event(new self(...func_get_args()), [], true);
    }
}
