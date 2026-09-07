<?php

namespace Daun\StatamicMux\Actions;

use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Statamic\Actions\Action;

use function Statamic\trans as __;

class RelinkToAsset extends Action
{
    protected $icon = 'link';

    public static function title()
    {
        return __('Re-link to local asset');
    }

    /**
     * Advisory only: eligibility is derived once per batch when the items are
     * built, and re-validated against a fresh plan before running.
     */
    public function visibleTo($item)
    {
        return $item instanceof MuxLibraryItem && $item->isRelinkable();
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
        return __('Re-link this existing Mux encoding to its attributed local asset?');
    }

    public function buttonText()
    {
        return __('Re-link');
    }

    public function run($items, $values)
    {
        $item = $items->first();
        if (! $item instanceof MuxLibraryItem) {
            throw new \RuntimeException(__('The selected Mux asset cannot be re-linked.'));
        }

        $service = app(MuxService::class);
        $plan = app(Reconciler::class)->plan();
        $target = $this->findTarget($plan->locals, $item->id());

        if (! $target?->selected) {
            throw new \RuntimeException(__('The Mux asset is no longer a safe re-link candidate.'));
        }

        if (! $service->relinkMuxAsset($target->asset, $target->selected->remote->asset(), $target->proxySource)) {
            throw new \RuntimeException(__('The Mux asset could not be re-linked.'));
        }

        return __('Mux encoding re-linked to :asset.', ['asset' => $target->path()]);
    }

    protected function findTarget($locals, string $muxId): ?LocalAssetRecord
    {
        return $locals->first(fn (LocalAssetRecord $record) => $record->canRelink()
            && $record->selected?->id() === $muxId);
    }
}
