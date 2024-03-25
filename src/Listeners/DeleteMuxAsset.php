<?php

namespace Daun\StatamicMux\Listeners;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Features\Mirror;
use Daun\StatamicMux\Listeners\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetDeleted;

class DeleteMuxAsset implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected MuxService $service
    ) {}

    public function handle(AssetDeleted $event)
    {
        if (!Mux::configured()) return;
        if (!Mirror::shouldMirror($event->asset)) return;

        $this->service->deleteMuxAsset($event->asset);
    }
}
