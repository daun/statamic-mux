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

class ReUploadToMux extends Action
{
    public static function title()
    {
        return __('Re-upload to Mux');
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

        if (! $muxAsset->exists()) {
            return false;
        }

        // Only show if the stored mux ID is confirmed in the reconciler cache
        $muxId = $muxAsset->id();
        $cached = app(ListingReconciler::class)->getCachedRemoteAssetsIfAvailable();

        return $cached->contains(fn ($remote) => $remote->getId() === $muxId);
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
        return 'Are you sure you want to re-upload this video to Mux? The existing Mux asset will be replaced and its playback ID will change.|Are you sure you want to re-upload these :count videos to Mux? The existing Mux assets will be replaced and their playback IDs will change.';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Re-upload|Re-upload :count videos';
    }

    public function run($items, $values)
    {
        foreach ($items as $item) {
            CreateMuxAssetJob::dispatch($item, true);
        }

        return trans_choice('Video queued for re-upload to Mux|:count videos queued for re-upload to Mux', $items->count(), ['count' => $items->count()]);
    }
}
