<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\CpAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class ListingController extends CpController
{
    public function __construct(
        protected ListingReconciler $listing,
        protected MuxService $service,
        protected MuxApi $api,
    ) {}

    public function index()
    {
        $this->authorize('manage mux');

        if (! $this->service->configured()) {
            return Inertia::render('EmptyPage');
        }

        return redirect()->route('statamic.cp.mux.assets');
    }

    public function assets()
    {
        $this->authorize('manage mux');

        if (! $this->service->configured()) {
            return redirect()->route('statamic.cp.mux.index');
        }

        return Inertia::render('MuxAssetsPage', [
            'endpoint' => cp_route('mux.listing.local'),
            'commandEndpoint' => cp_route('mux.command'),
            'actionUrl' => cp_route('mux.actions.run'),
            'assetEditorChunks' => CpAssets::assetEditorChunkUrls(),
        ]);
    }

    public function library()
    {
        $this->authorize('view mux library');

        if (! $this->service->configured()) {
            return redirect()->route('statamic.cp.mux.index');
        }

        $user = User::current();

        return Inertia::render('MuxLibraryPage', [
            'endpoint' => cp_route('mux.listing.remote'),
            'refreshEndpoint' => cp_route('mux.listing.refresh'),
            'commandEndpoint' => cp_route('mux.command'),
            'actionUrl' => cp_route('mux.actions.remote.run'),
            'dashboardUrl' => $user?->can('view mux dashboard') ? $this->api->dashboardUrl() : null, // @phpstan-ignore method.notFound
        ]);
    }

    public function local(Request $request): JsonResponse
    {
        $this->authorize('manage mux');

        if (! $this->service->configured()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'columns' => $this->localColumns(),
                ],
            ]);
        }

        $result = $this->listing->getLocalVideos(
            $this->extractParams($request),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => [
                ...$result['meta'],
                'columns' => $this->localColumns(),
            ],
        ]);
    }

    public function remote(Request $request): JsonResponse
    {
        $this->authorize('view mux library');

        if (! $this->service->configured()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'columns' => $this->remoteColumns(),
                ],
            ]);
        }

        $result = $this->listing->getRemoteVideos(
            $this->extractParams($request),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => [
                ...$result['meta'],
                'columns' => $this->remoteColumns(),
                'filters' => $this->remoteFilters(),
            ],
        ]);
    }

    public function refresh(): JsonResponse
    {
        $this->authorize('trigger mux sync');

        $assets = $this->listing->refreshRemoteAssets();

        return response()->json([
            'message' => __('Mux Library refreshed'),
            'count' => $assets->count(),
        ]);
    }

    protected function extractParams(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'sort' => $request->input('sort'),
            'order' => $request->input('order', 'asc'),
            'page' => (int) $request->input('page', 1),
            'perPage' => (int) $request->input('perPage', 25),
            'filters' => $this->parseFilters($request->input('filters', '')),
            'rows' => $this->parseRows($request->input('rows')),
        ];
    }

    protected function parseRows(mixed $rows): array
    {
        if (is_array($rows)) {
            return collect($rows)
                ->flatten()
                ->map(fn ($row) => trim((string) $row))
                ->filter()
                ->values()
                ->all();
        }

        if (! $rows) {
            return [];
        }

        return collect(preg_split('/[,|]/', (string) $rows))
            ->map(fn ($row) => trim($row))
            ->filter()
            ->values()
            ->all();
    }

    protected function parseFilters(string $filters): array
    {
        if (! $filters) {
            return [];
        }

        $decoded = json_decode(base64_decode($filters), true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)->map(function ($value, $field) {
            return [
                'field' => $field,
                'value' => $value,
            ];
        })->values()->all();
    }

    protected function localColumns(): array
    {
        return [
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true],
            ['field' => 'mirror_status', 'label' => __('Status'), 'sortable' => true],
            ['field' => 'processing_status', 'label' => __('Processing'), 'sortable' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true],
            ['field' => 'created_at', 'label' => __('Created'), 'sortable' => true],
        ];
    }

    protected function remoteColumns(): array
    {
        return [
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true],
            ['field' => 'match_status', 'label' => __('Status'), 'sortable' => true],
            ['field' => 'processing_status', 'label' => __('Processing'), 'sortable' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true],
            ['field' => 'created_at', 'label' => __('Created'), 'sortable' => true],
        ];
    }

    protected function remoteFilters(): array
    {
        return [
            [
                'handle' => 'processing_status',
                'label' => __('Processing Status'),
                'type' => 'select',
                'options' => [
                    'ready' => __('Ready'),
                    'preparing' => __('Preparing'),
                    'errored' => __('Errored'),
                ],
            ],
            [
                'handle' => 'match_status',
                'label' => __('Sync Status'),
                'type' => 'select',
                'options' => [
                    'mirrored' => __('Mirrored'),
                    'proxy' => __('Placeholder'),
                    'orphaned' => __('Orphaned'),
                    'duplicated' => __('Duplicated'),
                ],
            ],
            [
                'handle' => 'playback_policy',
                'label' => __('Playback Policy'),
                'type' => 'select',
                'options' => [
                    'public' => __('Public'),
                    'signed' => __('Signed'),
                ],
            ],
            [
                'handle' => 'resolution_tier',
                'label' => __('Resolution'),
                'type' => 'select',
                'options' => [
                    '1080p' => '1080p',
                    '1440p' => '1440p',
                    '2160p' => '2160p',
                ],
            ],
            [
                'handle' => 'duration_range',
                'label' => __('Duration'),
                'type' => 'select',
                'options' => [
                    'short' => __('Short (< 1 min)'),
                    'medium' => __('Medium (1-10 min)'),
                    'long' => __('Long (> 10 min)'),
                ],
            ],
            [
                'handle' => 'is_test',
                'label' => __('Test Asset'),
                'type' => 'select',
                'options' => [
                    '1' => __('Yes'),
                    '0' => __('No'),
                ],
            ],
        ];
    }
}
