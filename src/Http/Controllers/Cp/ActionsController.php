<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\MuxLibraryItem;
use Daun\StatamicMux\Mux\MuxApi;
use Statamic\Facades\Asset;
use Statamic\Http\Controllers\CP\ActionController;

class ActionsController extends ActionController
{
    public function __construct(
        protected ListingReconciler $reconciler,
        protected MuxApi $mux,
    ) {}

    protected function getSelectedItems($items, $context)
    {
        if (request()->routeIs('mux.actions.remote*')) {
            return $this->getRemoteItems($items);
        }

        return $this->getLocalItems($items);
    }

    protected function getLocalItems($items)
    {
        return $items->map(fn ($id) => Asset::find($id))->filter();
    }

    protected function getRemoteItems($items)
    {
        $cached = $this->reconciler->getCachedRemoteAssetsIfAvailable()->keyBy(fn ($asset) => $asset->getId());
        $dashboardBaseUrl = $this->mux->dashboardUrl();

        return $items->map(fn ($muxId) => new MuxLibraryItem(
            $muxId,
            $cached->get($muxId),
            $dashboardBaseUrl,
        ));
    }
}
