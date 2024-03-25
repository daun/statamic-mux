<?php

namespace Daun\StatamicMux\Events;

use Statamic\Contracts\Assets\Asset;
use Statamic\Events\Event;

class AssetDeletingFromMux extends Event
{
    public function __construct(
        public Asset $asset,
        public string $muxId
    ) {}

    /**
     * Dispatch and halt on first non-null listener response.
     */
    public static function dispatch()
    {
        return event(new static(...func_get_args()), [], true);
    }
}
