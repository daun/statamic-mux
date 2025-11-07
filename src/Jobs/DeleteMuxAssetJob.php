<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

class DeleteMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Asset|string $asset
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function handle(DeleteMuxAsset $action): void
    {
        $action->handle($this->asset);
    }
}
