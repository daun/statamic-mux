<?php

namespace Daun\StatamicMux\Controllers;

use Daun\StatamicMux\Mux\MuxService;
use Statamic\Assets\AssetContainer;
use Statamic\Contracts\Assets\QueryBuilder;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as AssetContainerService;
use Statamic\Http\Controllers\API\ApiController as StatamicApiController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Http\Resources\API\AssetResource;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;

class ApiController extends StatamicApiController
{
    use QueriesFilters;

    public function __construct(
        protected MuxService $mux
    ) {}

    public function assets(FilteredRequest $request)
    {
        // $statamicAssets = Asset::all();
        // $muxAssets = $this->mux->listMuxAssets();
        // return [
        //     'assets' => $statamicAssets,
        //     'mux' => $muxAssets,
        // ];

        $query = $this->indexQuery();
        $activeFilterBadges = $this->queryFilters($query, $request->filters);

        $sortField = request('sort');
        $sortDirection = request('order', 'asc');

        if (! $sortField && ! request('search')) {
            $sortField = 'basename';
            $sortDirection = 'asc';
        }

        if ($sortField) {
            $query->orderBy($sortField, $sortDirection);
        }


        return app(AssetResource::class)::collection(
            $this->filterSortAndPaginate($query)
        );
    }

    protected function indexQuery(): QueryBuilder
    {
        $query = Asset::query();

        $query->where('container', $this->container());
        $query->where('is_video', true);

        if ($search = request('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('basename', 'like', '%'.$search.'%');
                $query->orWhere('title', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    protected function container(): ?AssetContainer
    {
        $handle = request('container', 'assets');
        $container = AssetContainerService::findByHandle($handle);
        if (! $container) {
            throw new \Exception("Unknown asset container: [{$handle}]");
        }
        return $container;
    }
}
