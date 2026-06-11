<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Actions\Action;
use Statamic\Assets\Asset;

use function Statamic\trans as __;
use function Statamic\trans_choice;

class UploadToMux extends Action
{
    protected $icon = 'mux::cloud-upload';

    public static function title()
    {
        return __('Upload to Mux');
    }

    public function visibleTo($item)
    {
        $asset = $this->getMuxAsset($item);

        return $asset && ! $asset->exists();
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Upload this video to Mux?|Upload these :count videos to Mux?';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Upload|Upload :count videos';
    }

    public function run($items, $values)
    {
        collect($items)
            ->map(fn ($item) => $this->getMuxAsset($item)->asset ?? null)
            ->filter()
            ->each(fn ($muxAsset) => CreateMuxAssetJob::dispatchAsync($muxAsset));

        return trans_choice('Video queued for upload to Mux|:count videos queued for upload to Mux', $items->count(), ['count' => $items->count()]);
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
