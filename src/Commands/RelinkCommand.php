<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Concerns\HasCommandOutputStyles;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class RelinkCommand extends Command
{
    use HasCommandOutputStyles;
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:relink
                        {--container= : Limit the command to a specific asset container}
                        {--force : Relink candidates that failed media validation}
                        {--dry-run : Perform a trial run with no changes and print a list of affected files}';

    protected $description = 'Re-link local video assets to existing Mux encodings';

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if (! $plan = $this->buildPlan($reconciler, $this->option('container'))) {
            return self::FAILURE;
        }

        $safe = $plan->relinkable();
        $mismatches = $plan->local(ReconciliationState::MediaMismatch);
        $reviewable = $safe->concat($mismatches)->values();
        $selected = $force ? $plan->relinkable(force: true) : $safe;

        $this->renderPlan($plan, $safe, $mismatches, $reviewable, $force);

        if ($dryRun) {
            $this->warn('Performing dry run: no assets will be re-linked');

            return self::SUCCESS;
        }

        if ($reviewable->isEmpty()) {
            $this->info('<success>✓ Nothing to re-link</success>');

            return self::SUCCESS;
        }

        if (! $force && $this->isInteractiveTerminal()) {
            $mode = $this->choice(
                "How should these {$reviewable->count()} assets be handled?",
                ['a' => 'Relink all safe matches', 'e' => 'Review individually', 'n' => 'Cancel'],
                'n',
            );

            if ($mode === 'Cancel') {
                return self::SUCCESS;
            }

            if ($mode === 'Review individually') {
                $selected = $reviewable->filter(fn (LocalAssetRecord $record) => $this->confirmRecord($record))->values();
            }
        }

        if ($selected->isEmpty()) {
            $this->info('<success>✓ Nothing to re-link</success>');

            return self::SUCCESS;
        }

        $outcomes = $runner->relink($selected);

        foreach ($outcomes as $outcome) {
            $this->isSuccess($outcome)
                ? $this->line("Re-linked <name>{$outcome['record']->path()}</name> to <name>{$outcome['mux_id']}</name>")
                : $this->error("Failed to re-link {$outcome['record']->path()}: {$outcome['error']}");
        }

        $this->info('<success>✓ Re-linked '.$this->succeeded($outcomes)->count().' assets</success>');

        return $this->failures($outcomes)->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    protected function renderPlan(ReconciliationPlan $plan, $safe, $mismatches, $reviewable, bool $force): void
    {
        $this->line('Re-link plan for '.$reviewable->count().' '.($reviewable->count() === 1 ? 'asset' : 'assets'));
        $this->line(sprintf('  %3d safe matches', $safe->count()));

        if ($mismatches->isNotEmpty()) {
            $this->line(sprintf('  %3d media mismatches held%s', $mismatches->count(), $force ? ' — overridden by --force' : ''));
        }

        $held = [
            'still preparing' => $plan->countLocal(ReconciliationState::Preparing),
            'unknown processing status' => $plan->countLocal(ReconciliationState::UnknownStatus),
            'attribution conflict' => $plan->countRemote(ReconciliationState::AttributionConflict),
            'unattributable' => $plan->countRemote(ReconciliationState::Unattributable),
            'source has no Mux field' => $plan->countRemote(ReconciliationState::UnmanagedSource),
            'shared between assets' => $plan->countRemote(ReconciliationState::SharedReference),
        ];

        foreach (array_filter($held) as $label => $count) {
            $this->line(sprintf('  %3d held — %s', $count, $label));
        }

        foreach ($reviewable as $record) {
            $this->renderRecord($record, $this->getOutput()->isVerbose());
        }
    }

    protected function renderRecord(LocalAssetRecord $record, bool $verbose): void
    {
        if (! $selected = $record->selected) {
            return;
        }

        $suffix = $record->proxySource ? ' <comment>(placeholder source)</comment>' : '';
        $this->line("  <name>{$record->path()}</name> → {$this->shortMuxId($selected->id())}{$suffix}");

        if (! $verbose) {
            return;
        }

        $asset = $record->asset;
        $dimensions = $asset->width() && $asset->height() ? "{$asset->width()}×{$asset->height()}" : 'unknown';
        $duration = $asset->duration() !== null ? "{$asset->duration()}s" : 'unknown duration';
        $this->line("      Local     {$dimensions}  {$duration}");

        foreach ($record->candidates as $candidate) {
            $video = $candidate->remote;
            $role = $candidate === $selected ? 'Selected ' : 'Discarded';
            $reason = $candidate->reason ? "  {$candidate->reason}" : '';
            $this->line(sprintf(
                '      %s %s  %ss  %s  %s  %s  %s%s',
                $role,
                $candidate->id(),
                $video->duration(),
                $video->aspectRatioLabel() ?? 'unknown',
                $video->resolutionTier() ?? 'unknown',
                $video->status() ?? 'unknown',
                $video->createdAt()?->format('Y-m-d') ?? 'unknown date',
                $reason,
            ));
        }
    }

    protected function confirmRecord(LocalAssetRecord $record): bool
    {
        $this->newLine();
        $this->renderRecord($record, true);

        if ($record->state === ReconciliationState::MediaMismatch) {
            $this->warn("Media validation failed: {$record->reason}");

            return $this->confirm('Relink despite the media mismatch?', false);
        }

        return $this->confirm('Relink this asset?', false);
    }

    protected function isInteractiveTerminal(): bool
    {
        if (! $this->input->isInteractive() || ! function_exists('stream_isatty')) {
            return false;
        }

        return @stream_isatty(STDIN) && @stream_isatty(STDOUT);
    }
}
