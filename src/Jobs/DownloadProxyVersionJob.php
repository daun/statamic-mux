<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

class DownloadProxyVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    public function handle(DownloadProxyVersion $action): void
    {
        // Check if we need to process this at all
        if (! $action->canHandle($this->asset, $this->proxyId)) {
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
    }

    private function getBackoffDelay(): int
    {
        $backoffDelays = [1, 3, 5, 10, 20, 30, 60, 120, 300, 600, 1200, 1800, 3600, 10800];
        $attempt = $this->attempts() - 1;

        return $backoffDelays[$attempt] ?? end($backoffDelays);
    }
}
