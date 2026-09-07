<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Illuminate\Support\Collection;
use Statamic\Facades\Asset;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\ActionController;

class ActionsController extends ActionController
{
    public function __construct(
        protected RemoteAssetCache $cache,
        protected MuxApi $mux,
        protected Reconciler $reconciler,
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
            ->groupBy(fn (array $item) => $item['container'])
            ->flatMap(fn ($group, $container) => Asset::query()
                // @phpstan-ignore-next-line method.notFound
                ->where('container', $container)
                ->whereIn('path', $group->pluck('path')->all())
                ->get()
            )->filter()
            ->map(fn ($asset) => MuxAsset::fromAsset($asset));
    }

    protected function getRemoteItems($items)
    {
        $cached = $this->cache->getIfAvailable();
        $index = $cached->keyBy(fn ($asset) => $asset->getId());
        $relinkable = $this->relinkableMuxIds($cached);
        $user = User::current();
        $dashboardBaseUrl = $user?->can('open mux dashboard') ? $this->mux->dashboardUrl() : null; // @phpstan-ignore method.notFound

        return $items->map(fn ($muxId) => new MuxLibraryItem(
            $muxId,
            $index->get($muxId),
            $dashboardBaseUrl,
            $relinkable->contains($muxId),
        ));
    }

    /**
     * Classify the cached snapshot once for the whole batch. A cold cache stays
     * conservative rather than triggering a full remote walk for visibility.
     *
     * @return Collection<int, mixed>
     */
    protected function relinkableMuxIds(Collection $cached): Collection
    {
        if ($cached->isEmpty()) {
            return collect();
        }

        return $this->reconciler->fromRemoteAssets($cached)
            ->relinkable()
            ->map(fn (LocalAssetRecord $record) => $record->selected?->id())
            ->filter()
            ->values();
    }
}
