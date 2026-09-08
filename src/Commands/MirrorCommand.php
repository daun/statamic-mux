<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Concerns\HasCommandOutputStyles;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;

class MirrorCommand extends Command
{
    use HasCommandOutputStyles;
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:mirror
                        {--container= : Limit the command to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}';

    protected $description = 'Mirror local video assets with Mux';

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $sync = Queue::isSync();

        if (! $plan = $this->buildPlan($reconciler, $this->option('container'))) {
            return self::FAILURE;
        }

        [$relinks, $uploads, $prunable] = $this->steps($plan, $force);

        if ($dryRun) {
            $this->warn('Performing dry run: no changes will be made');
            $this->newLine();
        }

        $this->renderPlan($plan, $relinks, $uploads, $prunable, $force);

        if ($dryRun) {
            return self::SUCCESS;
        }

        $relinked = $runner->relink($relinks);

        foreach ($relinked as $outcome) {
            $this->isSuccess($outcome)
                ? $this->line("Re-linked <name>{$outcome['record']->path()}</name> to <name>{$outcome['mux_id']}</name>")
                : $this->error("Failed to re-link {$outcome['record']->path()}: {$outcome['error']}");
        }

        $uploaded = $runner->upload($uploads, $force);

        foreach ($uploaded as $outcome) {
            $this->isSuccess($outcome)
                ? $this->line(($sync ? ($outcome['reupload'] ? 'Reuploaded' : 'Uploaded') : 'Queued upload of')." <name>{$outcome['record']->path()}</name>")
                : $this->error("Failed to upload {$outcome['record']->path()}: {$outcome['error']}");
        }

        // Pruning an encoding a file still needs is unrecoverable, so a failed re-link aborts the prune.
        if ($this->failures($relinked)->isNotEmpty()) {
            $this->error('Prune aborted because one or more local assets could not be re-linked.');

            return self::FAILURE;
        }

        $relinkedIds = $this->succeededMuxIds($relinked);
        $pruned = $runner->prune(
            $prunable->reject(fn (RemoteAssetRecord $record) => $relinkedIds->contains($record->id()))
        );

        foreach ($this->reportable($pruned) as $outcome) {
            $this->isSuccess($outcome)
                ? $this->line(($sync ? 'Removed' : 'Queued removal of')." <name>{$outcome['mux_id']}</name>")
                : $this->error("Failed to prune {$outcome['mux_id']}: {$outcome['error']}");
        }

        $this->newLine();
        $this->info('<success>✓ Mirror reconciliation complete</success>');

        return $this->failures($uploaded)->isEmpty() && $this->failures($pruned)->isEmpty()
            ? self::SUCCESS
            : self::FAILURE;
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

    protected function renderPlan(ReconciliationPlan $plan, Collection $relinks, Collection $uploads, Collection $prunable, bool $force): void
    {
        $reuploads = $uploads->filter(fn (LocalAssetRecord $record) => filled($record->muxId))->count();

        $this->line("Plan for {$plan->locals->count()} local videos and {$plan->remotes->count()} Mux assets");
        $this->newLine();
        $this->line(sprintf('  %3d re-link    existing ready Mux encoding found', $relinks->count()));
        $this->line(sprintf('  %3d upload     local assets requiring an upload', $uploads->count() - $reuploads));
        $this->line(sprintf('  %3d re-upload  linked or stale local assets', $reuploads));
        $this->line(sprintf(
            '  %3d prune      %d superseded · %d source deleted',
            $prunable->count(),
            $prunable->where('state', ReconciliationState::Superseded)->count(),
            $prunable->where('state', ReconciliationState::MissingSource)->count(),
        ));
        $this->line(sprintf('  %3d ignore     ownership or attribution is not safe', $plan->countRemote(
            ReconciliationState::Foreign,
            ReconciliationState::Unattributable,
            ReconciliationState::AttributionConflict,
        )));
        $this->line(sprintf('  %3d skip       proxy placeholders in flight', $plan->countRemote(ReconciliationState::ProxyInFlight)));

        $holds = [
            ...$this->holdCounts($plan, 'local', [
                ReconciliationState::Preparing,
                ReconciliationState::MediaMismatch,
                ReconciliationState::UnknownStatus,
                ReconciliationState::NonReadyLinked,
            ]),
            ...$this->holdCounts($plan, 'remote', [
                ReconciliationState::AttributionConflict,
                ReconciliationState::Unattributable,
                ReconciliationState::SharedReference,
            ]),
        ];

        foreach ($holds as $label => $count) {
            $this->line(sprintf('  %3d hold       %s', $count, $label));
        }

        $proxySources = $plan->local(ReconciliationState::ProxySource);

        if ($proxySources->isNotEmpty()) {
            $this->newLine();
            $this->warn('⚠ '.$this->pluralize($proxySources->count(), 'local placeholder clip has', 'local placeholder clips have').' a full Mux encoding. Uploading the clips would replace the masters.');
            foreach ($proxySources as $record) {
                $this->line("  {$record->path()} → {$record->selected?->id()}");
            }
        }

        if ($relinks->isNotEmpty()) {
            $this->newLine();
            $this->warn("⚠ Re-linking runs before uploading. Without it these {$relinks->count()} encodings would be recreated.");
        }

        if ($force) {
            $this->newLine();
            $this->warn('Force mode skips ordinary re-linking. Placeholder sources are repaired; other encodings are retained until replacements succeed.');
        }

        $this->renderDiagnostics($plan);

        if ($this->getOutput()->isVerbose()) {
            $this->renderRecordList('Re-link', $relinks);
            $this->renderRecordList('Upload', $uploads);
            $this->renderRecordList('Prune', $prunable);
        }

        $this->newLine();
    }

    /** @return array<string, int> */
    protected function holdCounts(ReconciliationPlan $plan, string $side, array $states): array
    {
        return collect($states)
            ->mapWithKeys(fn (ReconciliationState $state) => [
                $state->value => $side === 'local' ? $plan->countLocal($state) : $plan->countRemote($state),
            ])
            ->filter()
            ->all();
    }
}
