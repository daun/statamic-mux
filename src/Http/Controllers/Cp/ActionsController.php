<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Statamic\Facades\Asset;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\ActionController;

class ActionsController extends ActionController
{
    public function __construct(
        protected ListingReconciler $reconciler,
        protected MuxApi $mux,
    ) {}

    protected function getSelectedItems($items, $context)
    {
        return request()->routeIs('statamic.cp.mux.actions.remote.*')
            ? $this->getRemoteItems($items)
            : $this->getLocalItems($items);
    }

    protected function getLocalItems($items)
    {
        return collect($items)
            ->filter(fn ($id) => str_contains($id, '::'))
            ->map(function ($id) {
                [$container, $path] = explode('::', $id, 2);
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
        $user = User::current();
        $dashboardBaseUrl = $user?->can('open mux dashboard') ? $this->mux->dashboardUrl() : null; // @phpstan-ignore method.notFound

        return $items->map(fn ($muxId) => new MuxLibraryItem(
            $muxId,
            $cached->get($muxId),
            $dashboardBaseUrl,
        ));
    }
}
