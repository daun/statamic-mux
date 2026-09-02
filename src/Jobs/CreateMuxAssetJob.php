<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Concerns\DispatchesAsync;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Daun\StatamicMux\Support\Queue;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MuxPhp\ApiException;
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
            if ($this->isPermanentClientError($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    private function isPermanentClientError(Throwable $exception): bool
    {
        do {
            $status = match (true) {
                $exception instanceof ApiException => $exception->getCode(),
                $exception instanceof RequestException && $exception->hasResponse() => $exception->getResponse()?->getStatusCode(),
                default => null,
            };

            if ($status >= 400 && $status < 500 && ! in_array($status, [408, 429], true)) {
                return true;
            }
        } while ($exception = $exception->getPrevious());

        return false;
    }
}
