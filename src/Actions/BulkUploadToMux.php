<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Actions\Action;

use function Statamic\trans as __;
use function Statamic\trans_choice;

class BulkUploadToMux extends Action
{
    protected $icon = 'mux::cloud-upload';

    public static function title()
    {
        return __('Upload to Mux');
    }

    public function visibleTo($item)
    {
        // Never shown in single-row dropdowns -- handled by UploadToMux / ReUploadToMux
        return false;
    }

    public function visibleToBulk($items)
    {
        // Only show when items have different upload statuses
        return $items->every(fn ($item) => $item instanceof MuxAsset)
            && $items->map(fn ($item) => $item->exists())->unique()->count() > 1;
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Upload the selected videos to Mux?';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Upload to Mux';
    }

    public function fieldItems()
    {
        return [
            'force_reupload' => [
                'display' => __('Re-upload already mirrored assets'),
                'instructions' => __('Assets already on Mux will be re-uploaded. Their playback IDs will change.'),
                'type' => 'toggle',
                'default' => false,
            ],
        ];
    }

    public function run($items, $values)
    {
        $forceReupload = (bool) ($values['force_reupload'] ?? false);

        $cached = app(ListingReconciler::class)
            ->getCachedRemoteAssetsIfAvailable()
            ->keyBy(fn ($remote) => $remote->getId());

        $queued = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $asset = $item->asset();
            $muxAsset = MuxAsset::fromAsset($asset);
            $muxId = $muxAsset->id();
            $isOnMux = $muxId && $cached->has($muxId);

            if ($isOnMux && ! $forceReupload) {
                $skipped++;
                continue;
            }

            CreateMuxAssetJob::dispatch($asset, $isOnMux);
            $queued++;
        }

        if ($queued === 0) {
            throw new \Exception(__('No videos were queued. Enable ":toggle" to include already mirrored assets.', [
                'toggle' => __('Re-upload already mirrored assets'),
            ]));
        }

        $message = trans_choice(':count video queued for upload to Mux|:count videos queued for upload to Mux', $queued, ['count' => $queued]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice('(:count already mirrored asset skipped)|(:count already mirrored assets skipped)', $skipped, ['count' => $skipped]);
        }

        return $message;
    }
}
