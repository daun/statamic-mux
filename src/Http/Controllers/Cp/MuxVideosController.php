<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Illuminate\Routing\Controller;
use Statamic\Facades\User;

class MuxVideosController extends Controller
{
    public function index()
    {
        $user = User::current();
        abort_unless($user && $user->can('view mux'), 403); // @phpstan-ignore method.notFound

        /** @var view-string $view */
        $view = 'statamic-mux::cp.videos.index';

        return view($view, [
            'title' => __('Mux Videos'),
            'localEndpoint' => cp_route('mux.api.videos.local'),
            'remoteEndpoint' => cp_route('mux.api.videos.remote'),
            'refreshEndpoint' => cp_route('mux.api.videos.refresh'),
        ]);
    }
}
