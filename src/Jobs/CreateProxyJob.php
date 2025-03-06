<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

class CreateProxyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $backoff = [1, 3, 5, 10, 20, 30, 60, 120, 300, 600, 1200, 1800, 3600, 10800];

    public function __construct(
        protected Asset $asset
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function retryUntil(): DateTime
    {
        return now()->addDay();
    }

    public function handle(MuxService $service, CreateProxyVersion $action): void
    {
        $muxId = $service->getMuxId($this->asset);

        // No Mux ID? Nothing to do here.
        if (! $muxId) {
            return;
        }

        // Asset not ready? Release back for later processing
        if (! $action->ready($muxId)) {
            return $this->release(3);
        }

        if ($proxyId = $action->handle($muxId)) {
            DownloadProxyJob::dispatch($this->asset, $proxyId);
        }
    }
}
