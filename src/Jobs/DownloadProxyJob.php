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

class DownloadProxyJob implements ShouldQueue
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
        return now()->addDays(3);
    }

    public function backoff(): array
    {
        return collect()->range(1, 10)->map(fn ($i) => 3 ** $i)->all();
    }

    public function handle(DownloadProxyVersion $action): void
    {
        // Asset not ready? Release back for later processing
        if (! $action->ready($this->proxyId)) {
            return $this->release();
        }

        if ($action->handle($this->proxyId, $this->asset)) {
            ray('DownloadProxyJob::handle', $this->proxyId);
        }
    }
}
