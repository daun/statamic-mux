<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Data\MuxAsset;
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
            AssetUploaded::class => 'upload',
            AssetReuploaded::class => 'reupload',
            AssetDeleted::class => 'delete',
            AssetSaved::class => 'update',
        ];
    }

    /**
     * Upload a mirrored asset to Mux.
     */
    public function upload(AssetUploaded $event): void
    {
        if (MirrorField::shouldMirror($event->asset)) {
            $this->service->createMuxAsset($event->asset);
        }
    }

    /**
     * Reupload a mirrored asset to Mux.
     */
    public function reupload(AssetReuploaded $event): void
    {
        if (MirrorField::shouldMirror($event->asset)) {
            MuxAsset::fromAsset($event->asset)->setProxy(false)->save();
            $this->service->createMuxAsset($event->asset, force: true);
        }
    }

    /**
     * Delete a mirrored asset from Mux.
     */
    public function delete(AssetDeleted $event): void
    {
        if (MirrorField::shouldMirror($event->asset)) {
            $this->service->deleteMuxAsset($event->asset);
        }
    }

    /**
     * Update a mirrored asset on Mux.
     */
    public function update(AssetSaved $event): void
    {
        if (MirrorField::shouldMirror($event->asset) && MirrorField::shouldUpdateMeta()) {
            if ($this->service->getMuxId($event->asset)) {
                $this->service->updateMuxAsset($event->asset);
            }
        }
    }
}
