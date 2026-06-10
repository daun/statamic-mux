<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\MuxLibraryItem;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Mux\MuxService;
use Statamic\Actions\Action;

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
        return $item instanceof MuxLibraryItem;
    }

    public function visibleToBulk($items)
    {
        return $items->every(fn ($item) => $item instanceof MuxLibraryItem);
    }

    public function authorize($user, $item)
    {
        return $user->can('manage mux');
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Are you sure you want to permanently delete this video from Mux?|Are you sure you want to permanently delete these :count videos from Mux?';
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
            $muxId = $item->id();

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
                ? __('Mux asset could not be deleted. It may not have been created by this addon.')
                : __('None of the :count Mux assets could be deleted.', ['count' => $total])
            );
        }

        if ($failed > 0) {
            $success = $total - $failed;

            return __(':success of :total Mux assets deleted. :failed could not be deleted.', [
                'success' => $success,
                'total' => $total,
                'failed' => $failed,
            ]);
        }

        return trans_choice('Mux asset deleted|:count Mux assets deleted', $total, ['count' => $total]);
    }
}
