<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    public function subscribe(): array
    {
        return [
            AssetUploaded::class => 'createMuxAsset',
            AssetReuploaded::class => 'createMuxAsset',
            AssetDeleted::class => 'deleteMuxAsset',
            AssetSaved::class => 'updateMuxAsset',
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
     * Update a mirrored asset on Mux.
     */
    public function updateMuxAsset(AssetSaved $event): void
    {
        if (MirrorField::shouldMirror($event->asset) && MirrorField::shouldUpdateMeta()) {
            if ($this->service->getMuxId($event->asset)) {
                $this->service->updateMuxAsset($event->asset);
            }
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
