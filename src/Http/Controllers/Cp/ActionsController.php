<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\MuxLibraryItem;
use Daun\StatamicMux\Mux\MuxApi;
use Statamic\Http\Controllers\CP\ActionController;

class ActionsController extends ActionController
{
    public function __construct(
        protected ListingReconciler $reconciler,
        protected MuxApi $mux,
    ) {}

    protected function getSelectedItems($items, $context)
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
