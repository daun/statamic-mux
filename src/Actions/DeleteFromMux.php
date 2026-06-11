<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Actions\Action;
use Statamic\Assets\Asset;

use function Statamic\trans as __;
use function Statamic\trans_choice;

class DeleteFromMux extends Action
{
    protected $dangerous = true;

    protected $icon = 'trash';

    public static function title()
    {
        return __('Delete from Mux');
    }

    public function visibleTo($item)
    {
        return $item instanceof MuxLibraryItem || ($item instanceof MuxAsset && $item->exists());
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Permanently delete this video from Mux?|Permanently delete these :count videos from Mux?';
    }

    public function buttonText()
    {
        /** @translation */
        return 'Delete|Delete :count videos';
    }

    public function run($items, $values)
    {
        $service = app(MuxService::class);
        $reconciler = app(ListingReconciler::class);
        $failures = collect();

        foreach ($items as $item) {
            $muxId = $this->getMuxId($item);
            if (! $muxId) {
                continue;
            }

            try {
                $deleted = $service->deleteMuxAsset($muxId);
            } catch (\Throwable $e) {
                $failures->push($muxId);

                continue;
            }

            if ($deleted) {
                $reconciler->forgetRemoteAsset($muxId);
            } else {
                $failures->push($muxId);
            }
        }

        $total = $items->count();
        $failed = $failures->count();

        if ($failed === $total) {
            throw new \Exception($total === 1
                ? __('Mux video cannot be deleted. It may not have been created by this addon.')
                : __('None of the :count Mux videos can be deleted.', ['count' => $total])
            );
        }

        if ($failed > 0) {
            $success = $total - $failed;

            return __(':success of :total Mux videos queued for deletion. :failed cannot be deleted.', [
                'success' => $success,
                'total' => $total,
                'failed' => $failed,
            ]);
        }

        return trans_choice('Mux video queued for deletion|:count Mux videos queued for deletion', $total, ['count' => $total]);
    }

    protected function getMuxId(mixed $item): ?string
    {
        if ($item instanceof Asset && MirrorField::shouldMirror($item)) {
            $item = MuxAsset::fromAsset($item);
        }

        if ($item instanceof MuxAsset) {
            return $item->id();
        }

        if ($item instanceof MuxLibraryItem) {
            return $item->id();
        }

        return null;
    }
}
