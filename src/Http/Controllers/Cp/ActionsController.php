<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Data\MuxAsset;
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
        return request()->routeIs('mux.actions.remote*')
            ? $this->getRemoteItems($items)
            : $this->getLocalItems($items);
    }

    protected function getLocalItems($items)
    {
        return collect($items)
            ->map(function ($id) {
                [$container, $path] = explode('::', $id);
                return ['container' => $container, 'path' => $path];
            })
            ->groupBy->container
            ->flatMap(fn ($group, $container) => Asset::query()
                ->where('container', $container)
                ->whereIn('path', $group->map->path->all())
                ->get()
            )->filter()
            ->map(fn ($asset) => MuxAsset::fromAsset($asset));
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
