<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Events\AssetRelinkedToMux;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Mux\RemoteAssetCache;

class ListingCacheSubscriber
{
    public function __construct(
        protected RemoteAssetCache $cache
    ) {}

    public function subscribe(): array
    {
        return [
            AssetUploadedToMux::class => 'invalidateListingCache',
            AssetRelinkedToMux::class => 'invalidateListingCache',
        ];
    }

    public function invalidateListingCache(AssetUploadedToMux|AssetRelinkedToMux $event): void
    {
        $this->cache->invalidate();
    }
}
