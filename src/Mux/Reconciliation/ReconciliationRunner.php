<?php

namespace Daun\StatamicMux\Mux\Reconciliation;

use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Support\Collection;

/**
 * A queued outcome means the job was dispatched, never that Mux finished it.
 *
 * @phpstan-type Outcome array{action: string, status: string, record: LocalAssetRecord|RemoteAssetRecord, mux_id: ?string, error: ?string, reupload?: bool, queued?: bool}
 */
class ReconciliationRunner
{
    public const SUCCESS = 'success';

    public const FAILURE = 'failure';

    public const SKIPPED = 'skipped';

    public function __construct(
        protected MuxService $service,
    ) {}

    /**
     * @param  iterable<LocalAssetRecord>  $records
     * @return Collection<int, Outcome>
     */
    public function relink(iterable $records): Collection
    {
        $outcomes = collect();

        foreach ($records as $record) {
            if (! $record->selected) {
                $outcomes->push($this->outcome('relink', self::FAILURE, $record, error: 'No re-link candidate was selected.'));

                continue;
            }

            try {
                $ok = $this->service->relinkMuxAsset(
                    $record->asset,
                    $record->selected->remote->asset(),
                    $record->proxySource,
                );
                $error = $ok ? null : 'The Mux asset could not be re-linked.';
            } catch (\Throwable $exception) {
                $ok = false;
                $error = $exception->getMessage();
            }

            $outcomes->push($this->outcome(
                'relink',
                $ok ? self::SUCCESS : self::FAILURE,
                $record,
                muxId: $record->selected->id(),
                error: $error,
            ));
        }

        return $outcomes;
    }

    /**
     * @param  iterable<LocalAssetRecord>  $records
     * @return Collection<int, Outcome>
     */
    public function upload(iterable $records, bool $force = false): Collection
    {
        $sync = Queue::isSync();
        $outcomes = collect();

        foreach ($records as $record) {
            $stale = $record->isStale();
            $reupload = $force && filled($record->muxId) && ! $stale;

            try {
                if ($stale) {
                    $this->service->clearMuxAsset($record->asset);
                }

                if ($sync) {
                    $ok = (bool) $this->service->createMuxAsset($record->asset, $reupload);
                    $error = $ok ? null : 'The asset could not be uploaded to Mux.';
                } else {
                    CreateMuxAssetJob::dispatch($record->asset, $reupload);
                    $ok = true;
                    $error = null;
                }
            } catch (\Throwable $exception) {
                $ok = false;
                $error = $exception->getMessage();
            }

            $outcomes->push($this->outcome(
                'upload',
                $ok ? self::SUCCESS : self::FAILURE,
                $record,
                error: $error,
                extra: ['reupload' => $reupload, 'queued' => ! $sync],
            ));
        }

        return $outcomes;
    }

    /**
     * @param  iterable<RemoteAssetRecord>  $records
     * @return Collection<int, Outcome>
     */
    public function prune(iterable $records): Collection
    {
        $sync = Queue::isSync();
        $outcomes = collect();

        foreach ($records as $record) {
            $muxId = $record->id();

            if (! $muxId) {
                $outcomes->push($this->outcome('prune', self::SKIPPED, $record));

                continue;
            }

            try {
                if ($sync) {
                    $ok = $this->service->deleteMuxAsset($record->remote->asset());
                    $error = $ok ? null : 'The Mux asset could not be deleted.';
                } else {
                    DeleteMuxAssetJob::dispatch($muxId);
                    $ok = true;
                    $error = null;
                }
            } catch (\Throwable $exception) {
                $ok = false;
                $error = $exception->getMessage();
            }

            $outcomes->push($this->outcome(
                'prune',
                $ok ? self::SUCCESS : self::FAILURE,
                $record,
                muxId: $muxId,
                error: $error,
                extra: ['queued' => ! $sync],
            ));
        }

        return $outcomes;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return Outcome
     */
    protected function outcome(
        string $action,
        string $status,
        LocalAssetRecord|RemoteAssetRecord $record,
        ?string $muxId = null,
        ?string $error = null,
        array $extra = [],
    ): array {
        return [
            'action' => $action,
            'status' => $status,
            'record' => $record,
            'mux_id' => $muxId,
            'error' => $error,
            ...$extra,
        ];
    }
}
