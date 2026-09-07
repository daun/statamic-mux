<?php

namespace Daun\StatamicMux\Jobs;

use DateTime;
use Daun\StatamicMux\Concerns\DispatchesAsync;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteReplacedMuxAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use DispatchesAsync;

    public function __construct(
        protected string $muxId
    ) {
        $this->connection = Queue::connection();
        $this->queue = Queue::queue();
    }

    public function retryUntil(): DateTime
    {
        return now()->addDay();
    }

    public function backoff(): array
    {
        return [60, 180, 300, 600, 1200, 1800, 3600, 10800];
    }

    public function handle(DeleteMuxAsset $action, MuxApi $api, ?RemoteAssetCache $cache = null): void
    {
        if (MirrorField::assetsByMuxId($this->muxId)->isNotEmpty()) {
            return;
        }

        $remoteAsset = $api->getAsset($this->muxId);

        // The deletion action evicts the cache itself; this branch never reaches it.
        if (! $remoteAsset) {
            $cache?->forget($this->muxId);

            return;
        }

        if ($remoteAsset->getStatus() === $remoteAsset::STATUS_PREPARING) {
            $this->release($this->getBackoffDelay());

            return;
        }

        $action->handle($remoteAsset);
    }

    private function getBackoffDelay(): int
    {
        $backoff = $this->backoff();
        $attempt = $this->attempts() - 1;

        return $backoff[$attempt] ?? end($backoff);
    }
}
