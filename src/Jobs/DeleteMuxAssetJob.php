<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Concerns\FailsOnPermanentMuxErrors;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MuxPhp\Models\Asset as MuxApiAsset;
use Statamic\Assets\Asset;
use Throwable;

class DeleteMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use FailsOnPermanentMuxErrors;

    public int $tries = 3;

    public array $backoff = [3, 15];

    public function __construct(
        protected Asset|MuxApiAsset|string $asset
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function handle(DeleteMuxAsset $action): void
    {
        try {
            $action->handle($this->asset);
        } catch (Throwable $exception) {
            $this->failOnPermanentMuxError($exception);
        }
    }
}
