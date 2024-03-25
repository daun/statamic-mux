<?php

namespace Daun\StatamicMux\Listeners;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Features\Mirror;
use Daun\StatamicMux\Listeners\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetSaved;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;

class CreateMuxAsset implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected MuxService $service
    ) {}

    public function handle(AssetSaved|AssetUploaded|AssetReuploaded $event)
    {
        if (!Mux::configured()) return;
        if (!Mirror::shouldMirror($event->asset)) return;

        $force = $event instanceof AssetReuploaded;
        $this->service->createMuxAsset($event->asset, $force);
    }
}
