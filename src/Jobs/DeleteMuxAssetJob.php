<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Support\Queue;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset as MuxApiAsset;
use Statamic\Assets\Asset;
use Throwable;

class DeleteMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
