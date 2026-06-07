<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statamic\Facades\User;

class ListingController extends Controller
{
    public function __construct(
        protected ListingReconciler $listing,
    ) {}

    public function index()
    {
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

        return view('statamic-mux::cp.videos.index', [
            'title' => __('Mux Videos'),
            'localEndpoint' => cp_route('mux.api.videos.local'),
            'remoteEndpoint' => cp_route('mux.api.videos.remote'),
            'refreshEndpoint' => cp_route('mux.api.videos.refresh'),
        ]);
    }

    public function local(Request $request): JsonResponse
    {
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

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
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

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
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

        $assets = $this->listing->refreshRemoteAssets();

        return response()->json([
            'message' => __('Remote assets refreshed'),
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
        ];
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
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false, 'visible' => true],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true, 'visible' => true],
            ['field' => 'status', 'label' => __('Status'), 'sortable' => true, 'visible' => true],
            ['field' => 'is_stale', 'label' => __('State'), 'sortable' => true, 'visible' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true, 'visible' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true, 'visible' => true],
            ['field' => 'created_at', 'label' => __('Mux Created'), 'sortable' => true, 'visible' => true],
        ];
    }

    protected function remoteColumns(): array
    {
        return [
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false, 'visible' => true],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true, 'visible' => true],
            ['field' => 'state', 'label' => __('State'), 'sortable' => true, 'visible' => true],
            ['field' => 'status', 'label' => __('Status'), 'sortable' => true, 'visible' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true, 'visible' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true, 'visible' => true],
            ['field' => 'created_at', 'label' => __('Created'), 'sortable' => true, 'visible' => true],
        ];
    }

    protected function remoteFilters(): array
    {
        return [
            [
                'handle' => 'status',
                'label' => __('Status'),
                'type' => 'select',
                'options' => [
                    'ready' => __('Ready'),
                    'preparing' => __('Preparing'),
                    'errored' => __('Errored'),
                ],
            ],
            [
                'handle' => 'state',
                'label' => __('State'),
                'type' => 'select',
                'options' => [
                    'mirrored' => __('Mirrored'),
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
