<?php

namespace Daun\StatamicMux\Features;

class Queue
{
    public static function connection(): ?string
    {
        return config('mux.queue.connection') ?? config('queue.default');
    }

    public static function queue(): ?string
    {
        return config('mux.queue.queue', 'default');
    }
}
