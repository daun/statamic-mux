<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Data\MuxAsset;
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

    public function __construct(
        protected Asset $asset
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(15);
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
            return $this->release(5);
        }

        if ($proxyId = $action->handle($muxId)) {
            MuxAsset::fromAsset($this->asset)->set('proxy', $proxyId)->save();
        }
    }
}
