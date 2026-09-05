<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Concerns\FailsOnPermanentMuxErrors;
use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;
use Throwable;

class DownloadProxyVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use FailsOnPermanentMuxErrors;

    public function __construct(
        protected Asset $asset,
        protected string $proxyId
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function retryUntil(): DateTime
    {
        return now()->addDay();
    }

    public function backoff(): array
    {
        return [1, 3, 5, 10, 20, 30, 60, 120, 300, 600, 1200, 1800, 3600, 10800];
    }

    public function handle(DownloadProxyVersion $action): void
    {
        try {
            // Check if we need to process this at all
            if (! $action->shouldHandle($this->asset, $this->proxyId)) {
                return;
            }

            // Not ready? Release back for later processing
            if (! $action->isReady($this->asset, $this->proxyId)) {
                $this->release($this->getBackoffDelay());

                return;
            }

            if ($downloaded = $action->handle($this->asset, $this->proxyId)) {
                DeleteMuxAssetJob::dispatch($this->proxyId);
            }
        } catch (Throwable $exception) {
            $this->failOnPermanentMuxError($exception);
        }
    }

    private function getBackoffDelay(): int
    {
        $backoff = $this->backoff();
        $attempt = $this->attempts() - 1;

        return $backoff[$attempt] ?? end($backoff);
    }
}
