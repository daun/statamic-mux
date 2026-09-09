<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandOutput;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\Side;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;

class PruneCommand extends Command
{
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:prune
                        {--container= : Limit the command to a specific asset container}
                        {--dry-run : Perform a trial run with no removals and print a list of affected files}
                        {--json : Output a single machine-readable JSON object}';

    protected $description = 'Remove orphaned videos from Mux';

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $output = CommandOutput::for($this);
        $container = $this->option('container');
        $dryRun = (bool) $this->option('dry-run');
        $report = $output->report($dryRun);

        if (! $plan = $this->buildPlan($reconciler, $report, $container)) {
            return $output->finish($report);
        }

        $this->describeScope($report, $plan, $container);

        $prunable = $plan->prunable();
        $destructive = $plan->destructive();

        /** @var Collection<int, ReportRecord> $records */
        $records = $plan->scopedRemotes()->mapWithKeys(fn (RemoteAssetRecord $record) => [
            spl_object_id($record) => $this->remoteRecord($record, $this->actionFor($record->state, Side::Remote)),
        ]);

        foreach ($this->addUnscopableAdvisory($report, $plan, $container) as $record) {
            $records[spl_object_id($record)] = $this->remoteRecord($record, ReconciliationAction::Skip);
        }

        $this->addDestructiveAdvisory($report, $destructive, $dryRun);

        if (! $dryRun) {
            foreach ($runner->prune($prunable) as $outcome) {
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

            $this->addDestructiveOutcomeAdvisory($report, $destructive);
        }

        $report->recordMany($records->values());

        return $output->finish($report);
    }

    protected function addDestructiveAdvisory(CommandReport $report, Collection $destructive, bool $dryRun): void
    {
        if ($destructive->isEmpty()) {
            return;
        }

        $files = $destructive->pluck('attributedAssetId')->filter()->unique()->count();

        $report->advisory(Advisory::warn(
            'destructive-prune',
            "{$destructive->count()} orphans are the only Mux encoding of {$this->pluralize($files, 'local asset', 'local assets')} still in your containers. Prune ".($dryRun ? 'would' : 'will').' delete them.',
            $destructive->map(fn (RemoteAssetRecord $record) => sprintf(
                '%s  %s  %s  %s',
                $record->attributedAssetId ?? $record->asset?->id() ?? 'unknown source',
                $this->shortMuxId($record->id()),
                $record->remote->resolutionTier() ?? 'unknown',
                $this->shortDate($record),
            ))->values()->all(),
            'Re-link them first: php artisan mux:relink',
        ));
    }

    protected function addDestructiveOutcomeAdvisory(CommandReport $report, Collection $destructive): void
    {
        if ($destructive->isEmpty()) {
            return;
        }

        $report->advisory(Advisory::warn(
            'destructive-pruned',
            "{$destructive->count()} unlinked encodings were ".(Queue::isSync() ? 'removed.' : 'queued for removal.'),
        ));
    }
}
