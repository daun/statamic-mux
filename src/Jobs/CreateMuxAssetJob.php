<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

class CreateMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Asset|string $asset,
        protected bool $force = false
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function handle(CreateMuxAsset $action): void
    {
        $action->handle($this->asset, $this->force);
    }
}
