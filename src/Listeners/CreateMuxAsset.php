<?php

namespace Daun\StatamicMux\Listeners;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Listeners\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetSaved;
use Statamic\Events\AssetUploaded;

class CreateMuxAsset implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected MuxService $service
    ) {
    }

    public function handle(AssetSaved|AssetUploaded|AssetReuploaded $event)
    {
        if ($this->shouldHandle($event)) {
            $force = $event instanceof AssetReuploaded;
            $this->service->createMuxAsset($event->asset, $force);
        }
    }

    protected function shouldHandle(AssetSaved|AssetUploaded|AssetReuploaded $event): bool
    {
        return Mux::configured() && MirrorField::shouldMirror($event->asset);
    }
}
