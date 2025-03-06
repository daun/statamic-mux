<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Daun\StatamicMux\Mux\MuxService;
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
        protected Asset $asset
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function handle(MuxService $service): void
    {
        if ($muxId = $service->getMuxId($this->asset)) {
            $status = $service->api()->getAssetStatus($muxId);
            if ($status === 'ready') {
                app(DownloadProxyVersion::class)->handle($muxId);
                $service->downloadProxy($muxId);
            }
        }

        $service->api()->
        $service->createMuxAsset($this->asset, $this->force);
    }
}
