<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Concerns\HasCommandOutputStyles;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class PruneCommand extends Command
{
    use HasCommandOutputStyles;
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:prune
                        {--container= : Limit the command to a specific asset container}
                        {--dry-run : Perform a trial run with no removals and print a list of affected files}';

    protected $description = 'Remove orphaned videos from Mux';

    protected const COUNT_LABELS = [
        'superseded' => 'superseded by a newer upload            safe to remove',
        'missing-source' => 'local asset no longer exists            safe to remove',
        'unlinked' => 'local asset exists but is unlinked      destructive unless re-linked first',
        'proxy-source' => 'placeholder clip source is unlinked     destructive unless re-linked first',
        'unmanaged-source' => 'local asset has no Mux field            cannot re-link',
        'errored' => 'errored unlinked encodings              safe to remove',
        'expired-proxy' => 'expired proxy placeholders              safe to remove',
        'orphaned-proxy' => 'proxies whose parent is gone            safe to remove',
        'foreign' => 'not created by this addon               ignored',
        'attribution-conflict' => 'attribution conflicts                   held',
        'unattributable' => 'unattributable addon assets             held',
        'shared-reference' => 'shared local references                 kept for review',
        'proxy-in-flight' => 'proxy placeholders in flight            skipped',
    ];

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $container = $this->option('container');
        $dryRun = (bool) $this->option('dry-run');
        $sync = Queue::isSync();

        if (! $plan = $this->buildPlan($reconciler, $container)) {
            return self::FAILURE;
        }

        $remotes = $plan->scopedRemotes();
        $prunable = $plan->prunable();
        $destructive = $plan->destructive();
        $unscopable = $plan->unscopable();

        if ($dryRun) {
            $this->warn('Performing dry run: no videos will be deleted');
            $this->newLine();
        }

        $this->renderDestructiveWarning($destructive, $dryRun ? 'would' : 'will');
        $this->renderStateCounts($remotes, self::COUNT_LABELS);
        $this->renderDiagnostics($plan);

        if ($unscopable->isNotEmpty()) {
            $this->warn("{$unscopable->count()} orphans could not be scoped to --container={$container} and were skipped");
            $this->line('Run without --container to review them.');
            $this->newLine();
        }

        if ($dryRun) {
            if ($this->getOutput()->isVerbose()) {
                foreach ($prunable->reject(fn ($r) => $destructive->containsStrict($r)) as $record) {
                    $this->line("Would remove <name>{$record->id()}</name> <comment>({$record->state->value})</comment>");
                }
            }

            $this->summarize($plan, $prunable->count(), 'would be removed', $unscopable->count());

            return self::SUCCESS;
        }

        if ($destructive->isNotEmpty()) {
            $this->warn('Prune will now remove the only ready Mux encodings of the live assets listed above.');
            $this->newLine();
        }

        $outcomes = $runner->prune($prunable);

        foreach ($this->succeeded($outcomes) as $outcome) {
            $this->line(($sync ? 'Removed' : 'Queued removal of')." <name>{$outcome['mux_id']}</name>");
        }

        $this->summarize($plan, $this->succeeded($outcomes)->count(), $sync ? 'removed' : 'queued for removal', $unscopable->count());

        if ($destructive->isNotEmpty()) {
            $this->warn("{$destructive->count()} unlinked encodings were ".($sync ? 'removed.' : 'queued for removal.'));
        }

        return $this->failures($outcomes)->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    protected function summarize($plan, int $removed, string $action, int $unscopable): void
    {
        $kept = $plan->countRemote(ReconciliationState::Linked, ReconciliationState::SharedReference);
        $ignored = $plan->countRemote(
            ReconciliationState::Foreign,
            ReconciliationState::Unattributable,
            ReconciliationState::AttributionConflict,
        );
        $skipped = $plan->countRemote(
            ReconciliationState::ProxyInFlight,
            ReconciliationState::Preparing,
            ReconciliationState::UnknownStatus,
            ReconciliationState::MediaMismatch,
            ReconciliationState::SharedReference,
        ) + $unscopable;

        $this->newLine();
        $this->info("<success>✓ {$kept} kept · {$removed} {$action} · {$ignored} ignored · {$skipped} skipped</success>");
    }
}
