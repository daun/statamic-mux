<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;

class ListingCacheSubscriber
{
    public function __construct(
        protected ListingReconciler $reconciler
    ) {}

    public function subscribe(): array
    {
        return [
            AssetUploadedToMux::class => 'invalidateListingCache',
        ];
    }

    public function invalidateListingCache(AssetUploadedToMux $event): void
    {
        $this->reconciler->invalidateRemoteAssets();
    }
}
