<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\MuxService;

class DeleteMuxAssetJob extends Job
{
    public function __construct(
        protected string $muxId
    ) {
        parent::__construct();
    }

    public function handle(MuxService $service): void
    {
        $service->deleteMuxAsset($this->muxId);
    }
}
