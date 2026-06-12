<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Http\RedirectResponse;
use Statamic\Assets\Asset;
use Statamic\Facades\Asset as Assets;
use Statamic\Http\Controllers\CP\CpController;

class ThumbnailController extends CpController
{
    public function __construct(
        protected ThumbnailService $service,
    ) {}

    public function thumbnail(string $id): RedirectResponse
    {
        $this->authorize('manage mux');

        if (($asset = Assets::findById(base64_decode($id))) instanceof Asset) {
            if ($thumbnail = $this->service->generateForAsset($asset)) {
                return redirect($thumbnail);
            }
        }

        abort(404);
    }
}
