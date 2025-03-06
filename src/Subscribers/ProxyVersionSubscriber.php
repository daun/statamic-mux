<?php

namespace Daun\StatamicMux\Subscribers;

use Daun\StatamicMux\Concerns\UsesAddonQueue;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Events\AssetUploadedToMux;
use Daun\StatamicMux\Jobs\CreateProxyJob;
use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Application;

class ProxyVersionSubscriber implements ShouldQueue
{
    use UsesAddonQueue;

    public function __construct(
        protected Application $app,
        protected MuxService $service
    ) {}

    public function subscribe(): array
    {
        if (! $this->shouldHandle()) {
            return [];
        }

        return [
            AssetUploadedToMux::class => 'createProxy',
        ];
    }

    /**
     * Create a proxy version of the uploaded asset.
     */
    public function createProxy(AssetUploadedToMux $event): void
    {
        CreateProxyJob::dispatchAfterResponse($event->asset);
        if ($proxyId = $this->app->make(CreateProxyVersion::class)->handle($event->muxId)) {
            MuxAsset::fromAsset($event->asset)->set('proxy', $proxyId)->save();
        }
    }

    protected function shouldHandle(): bool
    {
        return config('mux.storage.store_placeholders', false);
    }
}
