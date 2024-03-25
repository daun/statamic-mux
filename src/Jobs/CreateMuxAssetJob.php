<?php

namespace Daun\StatamicMux\Jobs;

use Daun\StatamicMux\Mux\MuxService;

class CreateMuxAssetJob extends Job
{
    public function __construct(
        protected string $assetId,
        protected bool $force = false
    ) {
        parent::__construct();
    }

    public function handle(MuxService $service): void
    {
        $service->createMuxAsset($this->assetId, $this->force);
    }
}
