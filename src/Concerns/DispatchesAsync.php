<?php

namespace Daun\StatamicMux\Concerns;

use Daun\StatamicMux\Support\Queue;
use Illuminate\Foundation\Bus\PendingDispatch;

trait DispatchesAsync
{
    /**
     * Dispatch the job async on the queue.
     *
     * Uses dispath or dispatchAfterResponse depending on the configured queue.
     *
     * @param  mixed  ...$arguments
     * @return PendingDispatch
     */
    public static function dispatchAsync(...$arguments)
    {
        return Queue::isSync()
            ? static::dispatchAfterResponse(...$arguments)
            : static::dispatch(...$arguments);
    }
}
