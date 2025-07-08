<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

class CreateProxyVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    public function handle(CreateProxyVersion $action): void
    {
        // No Mux ID? Nothing to do
        if (! $action->canHandle($this->asset)) {
            return;
        }

        // Not ready? Release back for later processing
        if (! $action->isReady($this->asset)) {
            $this->release(5);
            return;
        }

        if ($proxyId = $action->handle($this->asset)) {
            // TODO: download proxy and replace asset file
        }
    }
}
