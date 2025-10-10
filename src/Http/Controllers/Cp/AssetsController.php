<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Http\RedirectResponse;
use Statamic\Facades\Asset as Assets;
use Statamic\Http\Controllers\CP\CpController;

class AssetsController extends CpController
{
    public function __construct(protected MuxService $service)
    {
    }

    protected function thumbnail(string $container, string $path): RedirectResponse
    {
        if ($asset = Assets::findById("{$container}::{$path}")) {
            if ($playbackId = $this->service->getPlaybackId($asset)) {
                return redirect($this->service->getGifUrl($playbackId, ['width' => 400]));
            }
        }

        abort(404);
    }
}
