<?php

namespace Daun\StatamicMux\Controllers;

use Statamic\Facades\Asset;
use Statamic\Http\Controllers\CP\ActionController as Controller;

class AssetActionController extends Controller
{
    protected static $key = 'mux-asset';

    protected function getSelectedItems($items, $context)
    {
        return $items->map(function ($item) {
            return Asset::find($item);
        });
    }
}
