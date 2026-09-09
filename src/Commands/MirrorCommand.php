<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandOutput;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Enums\Side;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;

class MirrorCommand extends Command
{
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:mirror
                        {--container= : Limit the command to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}
                        {--json : Output a single machine-readable JSON object}';

    protected $description = 'Mirror local video assets with Mux';

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $output = CommandOutput::for($this);
        $container = $this->option('container');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $report = $output->report($dryRun);

        if (! $plan = $this->buildPlan($reconciler, $report, $container)) {
            return $output->finish($report);
        }

        $this->describeScope($report, $plan, $container);

        [$relinks, $uploads, $prunable] = $this->steps($plan, $force);

        /** @var Collection<int, ReportRecord> $records */
        $records = collect();

        foreach ($relinks as $record) {
            $records[spl_object_id($record)] = $this->localRecord($record, ReconciliationAction::Relink);
        }

        foreach ($uploads as $record) {
            $records[spl_object_id($record)] = $this->localRecord(
                $record,
                filled($record->muxId) ? ReconciliationAction::Reupload : ReconciliationAction::Upload,
            );
        }

        // An encoding a pending re-link points at is kept, never pruned.
        $reserved = $relinks->map(fn (LocalAssetRecord $record) => $record->selected?->id())->filter();

        foreach ($prunable as $record) {
            $records[spl_object_id($record)] = $reserved->contains($record->id())
                ? $this->remoteRecord($record, ReconciliationAction::Keep, 'kept for the asset it is re-linked to')
                : $this->remoteRecord($record, ReconciliationAction::Prune);
        }

        foreach ($this->remainingLocals($plan, $relinks, $uploads) as $record) {
            $records[spl_object_id($record)] = $this->localRecord($record, $this->localAction($record));
        }

        foreach ($this->remainingRemotes($plan, $prunable) as $record) {
            $records[spl_object_id($record)] = $this->remoteRecord($record, ...$this->remoteAction($record));
        }

        foreach ($this->addUnscopableAdvisory($report, $plan, $container) as $record) {
            $records[spl_object_id($record)] = $this->remoteRecord($record, ReconciliationAction::Skip);
        }

        $this->addAdvisories($report, $plan, $relinks, $force);

        if ($dryRun) {
            $report->recordMany($records->values());

            return $output->finish($report);
        }

        $relinked = $runner->relink($relinks);

        foreach ($relinked as $outcome) {
            $key = spl_object_id($outcome['record']);

            if ($this->isSuccess($outcome)) {
                $records[$key] = $records[$key]->succeeded();
            } else {
                $records[$key] = $records[$key]->failed($outcome['error']);
                $report->failure(ReportFailure::make($outcome['error'], ReconciliationAction::Relink, $outcome['record']->path()));
            }
        }

        $uploaded = $runner->upload($uploads, $force);

        foreach ($uploaded as $outcome) {
            $key = spl_object_id($outcome['record']);

            if ($this->isSuccess($outcome)) {
                $records[$key] = $records[$key]->succeeded();
            } else {
                $records[$key] = $records[$key]->failed($outcome['error']);
                $report->failure(ReportFailure::make($outcome['error'], $records[$key]->action, $outcome['record']->path()));
            }
        }

        // Pruning an encoding a file still needs is unrecoverable, so a failed re-link aborts the prune.
        if ($this->failures($relinked)->isNotEmpty()) {
            foreach ($prunable as $record) {
                $key = spl_object_id($record);

                if ($records[$key]->action === ReconciliationAction::Prune) {
                    $records[$key] = $records[$key]->withAction(ReconciliationAction::Hold, 'prune aborted after a failed re-link');
                }
            }

            $report->recordMany($records->values());
            $report->abort('Prune aborted — '.$this->pluralize($this->failures($relinked)->count(), 'asset', 'assets').' could not be re-linked.');

            return $output->finish($report);
        }

        $relinkedIds = $this->succeededMuxIds($relinked);
        $pruned = $runner->prune($prunable->reject(fn (RemoteAssetRecord $record) => $relinkedIds->contains($record->id())));

        foreach ($pruned as $outcome) {
            $key = spl_object_id($outcome['record']);

            $records[$key] = match ($outcome['status']) {
                ReconciliationRunner::SUCCESS => $records[$key]->succeeded(),
                ReconciliationRunner::SKIPPED => $records[$key]->withAction(ReconciliationAction::Skip),
                default => $records[$key]->failed($outcome['error']),
            };

            if ($outcome['status'] === ReconciliationRunner::FAILURE) {
                $report->failure(ReportFailure::make($outcome['error'], ReconciliationAction::Prune, $outcome['mux_id']));
            }
        }

        $report->recordMany($records->values());

        return $output->finish($report);
    }

    /**
     * Force skips re-linking, but still repairs placeholder sources: uploading a
     * placeholder clip would overwrite the full master on Mux.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    protected function steps(ReconciliationPlan $plan, bool $force): array
    {
        if (! $force) {
            return [$plan->relinkable(), $plan->uploadable(), $plan->prunable()];
        }

        $relinks = $plan->local(ReconciliationState::ProxySource);
        $uploads = $plan->scopedLocals()->reject(
            fn (LocalAssetRecord $record) => MuxAsset::fromAsset($record->asset)->isProxy()
                || $record->state === ReconciliationState::ProxySource
        )->values();

        $protected = $uploads->flatMap(fn (LocalAssetRecord $record) => $record->candidates->map->id())->filter()->unique();
        $prunable = $plan->prunable()->reject(fn (RemoteAssetRecord $record) => $protected->contains($record->id()))->values();

        return [$relinks, $uploads, $prunable];
    }

    protected function remainingLocals(ReconciliationPlan $plan, Collection $relinks, Collection $uploads): Collection
    {
        $handled = $relinks->concat($uploads);

        return $plan->scopedLocals()->reject(fn (LocalAssetRecord $record) => $handled->containsStrict($record))->values();
    }

    protected function remainingRemotes(ReconciliationPlan $plan, Collection $prunable): Collection
    {
        return $plan->scopedRemotes()->reject(fn (RemoteAssetRecord $record) => $prunable->containsStrict($record))->values();
    }

    protected function localAction(LocalAssetRecord $record): ReconciliationAction
    {
        return MuxAsset::fromAsset($record->asset)->isProxy()
            ? ReconciliationAction::Skip
            : $this->actionFor($record->state, Side::Local);
    }

    /**
     * A prunable encoding kept out of this run is held, not pruned: force mode
     * retains the old encoding until its replacement upload has succeeded.
     *
     * @return array{0: ReconciliationAction, 1: ?string}
     */
    protected function remoteAction(RemoteAssetRecord $record): array
    {
        return $record->state->isPrunable()
            ? [ReconciliationAction::Hold, 'retained until the replacement upload succeeds']
            : [$this->actionFor($record->state, Side::Remote), null];
    }

    protected function addAdvisories(CommandReport $report, ReconciliationPlan $plan, Collection $relinks, bool $force): void
    {
        $proxySources = $plan->local(ReconciliationState::ProxySource);

        if ($proxySources->isNotEmpty()) {
            $report->advisory(Advisory::warn(
                'proxy-source-conflict',
                $this->pluralize($proxySources->count(), 'local placeholder clip has', 'local placeholder clips have').' a full Mux encoding. Uploading the clips would replace the masters.',
                $proxySources->map(fn (LocalAssetRecord $record) => "{$record->path()} → {$this->shortMuxId($record->selected?->id())}")->values()->all(),
            ));
        }

        if ($relinks->isNotEmpty()) {
            $report->advisory(Advisory::info(
                'relink-first',
                "Re-linking runs before uploading. Without it these {$relinks->count()} encodings would be recreated.",
            ));
        }

        if ($force) {
            $report->advisory(Advisory::warn(
                'force-mirror',
                'Force mode skips ordinary re-linking. Placeholder sources are repaired; other encodings are retained until replacements succeed.',
            ));
        }

        $this->addDiagnosticAdvisories($report, $plan);
    }
}
