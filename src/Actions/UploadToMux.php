<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Actions\Action;
use Statamic\Assets\Asset;

use function Statamic\trans as __;
use function Statamic\trans_choice;

class UploadToMux extends Action
{
    public static function title()
    {
        return __('Upload to Mux');
    }

    public function visibleTo($item)
    {
        if (! $item instanceof Asset) {
            return false;
        }

        if (! MirrorField::shouldMirror($item)) {
            return false;
        }

        $muxAsset = MuxAsset::fromAsset($item);

        // No mux ID stored — eligible for fresh upload
        if (! $muxAsset->exists()) {
            return true;
        }

        // Has a mux ID — check if it's in the reconciler cache
        // If not found in cache, the local data is stale and the asset needs re-uploading
        $muxId = $muxAsset->id();
        $cached = app(ListingReconciler::class)->getCachedRemoteAssetsIfAvailable();

        return $cached->every(fn ($remote) => $remote->getId() !== $muxId);
    }

    public function visibleToBulk($items)
    {
        return false;
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Are you sure you want to upload this video to Mux?|Are you sure you want to upload these :count videos to Mux?';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Upload|Upload :count videos';
    }

    public function run($items, $values)
    {
        foreach ($items as $item) {
            CreateMuxAssetJob::dispatch($item);
        }

        return trans_choice('Video queued for upload to Mux|:count videos queued for upload to Mux', $items->count(), ['count' => $items->count()]);
    }
}
