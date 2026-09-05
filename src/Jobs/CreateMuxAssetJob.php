<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Concerns\DispatchesAsync;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;
use Throwable;

class CreateMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use DispatchesAsync;

    public int $tries = 3;

    public array $backoff = [3, 15];

    public function __construct(
        protected Asset|string $asset,
        protected bool $force = false
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function handle(CreateMuxAsset $action): void
    {
        try {
            $action->handle($this->asset, $this->force);
        } catch (Throwable $exception) {
            if (MuxApi::isPermanentError($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
