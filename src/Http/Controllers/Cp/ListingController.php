<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Mux\MuxApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class ListingController extends CpController
{
    public function __construct(
        protected ListingReconciler $listing,
        protected MuxApi $mux,
    ) {}

    public function index()
    {
        return $this->mirrored();
    }

    public function mirrored()
    {
        return $this->listingView('mirrored', __('Mirrored Assets'));
    }

    public function library()
    {
        return $this->listingView('library', __('Mux Library'));
    }

    protected function listingView(string $listingPage, string $title)
    {
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

        return view('statamic-mux::cp.listing', [
            'title' => $title,
            'listingPage' => $listingPage,
            'localEndpoint' => cp_route('mux.listing.local'),
            'remoteEndpoint' => cp_route('mux.listing.remote'),
            'refreshEndpoint' => cp_route('mux.listing.refresh'),
            'commandEndpoint' => cp_route('mux.command'),
            'dashboardUrl' => $this->mux->dashboardUrl(),
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
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true],
            ['field' => 'status', 'label' => __('Status'), 'sortable' => true],
            ['field' => 'is_stale', 'label' => __('State'), 'sortable' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true],
            ['field' => 'created_at', 'label' => __('Mux Created'), 'sortable' => true],
            ['field' => '_actions', 'label' => '', 'sortable' => false, 'width' => '1%'],
        ];
    }

    protected function remoteColumns(): array
    {
        return [
            ['field' => 'thumbnail_url', 'label' => __('Thumbnail'), 'sortable' => false],
            ['field' => 'title', 'label' => __('Title'), 'sortable' => true],
            ['field' => 'state', 'label' => __('State'), 'sortable' => true],
            ['field' => 'status', 'label' => __('Status'), 'sortable' => true],
            ['field' => 'duration', 'label' => __('Duration'), 'sortable' => true],
            ['field' => 'playback_policy', 'label' => __('Policy'), 'sortable' => true],
            ['field' => 'created_at', 'label' => __('Created'), 'sortable' => true],
            ['field' => '_actions', 'label' => '', 'sortable' => false, 'width' => '1%'],
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
