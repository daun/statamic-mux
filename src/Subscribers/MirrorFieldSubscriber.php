<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetSaved;
use Statamic\Events\AssetUploaded;

class MirrorFieldSubscriber implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected MuxService $service
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            // AssetSaved::class => 'createMuxAsset',
            AssetUploaded::class => 'createMuxAsset',
            AssetReuploaded::class => 'createMuxAsset',
            AssetDeleted::class => 'deleteMuxAsset',
        ];
    }

    /**
     * Upload a mirrored asset to Mux.
     */
    public function createMuxAsset(AssetSaved|AssetUploaded|AssetReuploaded $event): void
    {
        if (MirrorField::shouldMirror($event->asset)) {
            $force = $event instanceof AssetReuploaded;
            $this->service->createMuxAsset($event->asset, $force);
        }
    }

    /**
     * Delete a mirrored asset from Mux.
     */
    public function deleteMuxAsset(AssetDeleted $event): void
    {
        if (MirrorField::shouldMirror($event->asset)) {
            $this->service->deleteMuxAsset($event->asset);
        }
    }
}
