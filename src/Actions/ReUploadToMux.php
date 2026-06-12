<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Actions\Action;
use Statamic\Assets\Asset;

use function Statamic\trans as __;
use function Statamic\trans_choice;

class ReUploadToMux extends Action
{
    protected $icon = 'mux::cloud-upload';

    public static function title()
    {
        return __('Reupload to Mux');
    }

    public function visibleTo($item)
    {
        $asset = $this->getMuxAsset($item);

        return $asset && ! $asset->isProxy() && $asset->exists();
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Are you sure you want to reupload this video to Mux? Existing playback ids and urls will change.|Are you sure you want to reupload these :count videos to Mux? Existing playback ids and urls will change.';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Reupload|Reupload :count videos';
    }

    public function run($items, $values)
    {
        collect($items)
            ->map(fn ($item) => $this->getMuxAsset($item)->asset ?? null)
            ->filter()
            ->each(fn ($muxAsset) => CreateMuxAssetJob::dispatchAsync($muxAsset, true));

        return trans_choice('Video queued for reupload|:count videos queued for reupload', $items->count(), ['count' => $items->count()]);
    }

    protected function getMuxAsset(mixed $item): ?MuxAsset
    {
        if ($item instanceof Asset && MirrorField::shouldMirror($item)) {
            $item = MuxAsset::fromAsset($item);
        }

        if ($item instanceof MuxAsset) {
            return $item;
        }

        return null;
    }
}
