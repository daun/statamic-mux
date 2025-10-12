<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Thumbnails\ThumbnailService;
use Illuminate\Http\RedirectResponse;
use Statamic\Facades\Asset as Assets;
use Statamic\Http\Controllers\CP\CpController;

class AssetsController extends CpController
{
    public function __construct(
        protected ThumbnailService $service,
    ) {}

    protected function thumbnail(string $id): RedirectResponse
    {
        if ($asset = Assets::findById(base64_decode($id))) {
            if ($thumbnail = $this->service->generateForAsset($asset)) {
                return redirect($thumbnail);
            }
        }

        abort(404);
    }
}
