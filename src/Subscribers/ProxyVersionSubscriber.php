<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Jobs\CreateProxyVersionJob;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Application;

class ProxyVersionSubscriber implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected Application $app,
        protected MuxService $service
    ) {}

    public function subscribe(): array
    {
        if (! config('mux.storage.store_placeholders', false)) {
            return [];
        }

        if (Queue::isSync()) {
            return [];
        }

        return [AssetUploadedToMux::class => 'createProxy'];
    }

    public function createProxy(AssetUploadedToMux $event): void
    {
        CreateProxyVersionJob::dispatch($event->asset);
    }
}
