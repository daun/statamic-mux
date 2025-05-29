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
use Statamic\Facades\CP\Toast;

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
        if (! MirrorField::shouldMirror($event->asset)) {
            return;
        }

        $force = $event instanceof AssetReuploaded;

        try {
            $this->service->createMuxAsset($event->asset, $force);
            Toast::info(__('statamic-mux::messages.toast.uploaded', ['file' => $event->asset->basename()]));
        } catch (\Throwable $th) {
            Toast::error(__('statamic-mux::messages.toast.upload_failed', ['error' => $th->getMessage()]));
        }
    }

    /**
     * Delete a mirrored asset from Mux.
     */
    public function deleteMuxAsset(AssetDeleted $event): void
    {
        if (! MirrorField::shouldMirror($event->asset)) {
            return;
        }

        $this->service->deleteMuxAsset($event->asset);
    }
}
