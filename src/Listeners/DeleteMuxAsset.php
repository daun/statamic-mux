<?php

namespace Daun\StatamicMux\Listeners;

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Listeners\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetDeleted;

class DeleteMuxAsset implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected MuxService $service
    ) {
    }

    public function handle(AssetDeleted $event)
    {
        if ($this->shouldHandle($event)) {
            $this->service->deleteMuxAsset($event->asset);
        }
    }

    protected function shouldHandle(AssetDeleted $event): bool
    {
        return Mux::configured() && MirrorField::shouldMirror($event->asset);
    }
}
