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
            $thumbnail = rescue(fn () => $this->service->generateForAsset($asset), null, report: false);

            if ($thumbnail) {
                return redirect($thumbnail);
            }
        }

        abort(404);
    }
}
