<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Features\Queue;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    abstract public function handle(MuxService $service): void;
}
