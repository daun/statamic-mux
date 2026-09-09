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
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;

class UploadCommand extends Command
{
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:upload
                        {--container= : Limit the command to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}
                        {--json : Output a single machine-readable JSON object}';

    protected $description = 'Upload local video assets to Mux';

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

        [$uploads, $skipped] = $plan->scopedLocals()->partition(function (LocalAssetRecord $record) use ($force) {
            if (MuxAsset::fromAsset($record->asset)->isProxy()) {
                return false;
            }

            return $force || ! $record->muxId || $record->isStale();
        });

        foreach ($skipped as $record) {
            $report->record($this->localRecord($record, $this->skippedAction($record)));
        }

        $this->addAdvisories($report, $plan, $uploads, $force);

        /** @var Collection<string, ReportRecord> $records */
        $records = $uploads->mapWithKeys(fn (LocalAssetRecord $record) => [
            $record->path() => $this->localRecord($record, $this->uploadAction($record)),
        ]);

        if (! $dryRun) {
            foreach ($runner->upload($uploads, $force) as $outcome) {
                $id = $outcome['record']->path();

                if ($this->isSuccess($outcome)) {
                    $records[$id] = $records[$id]->succeeded();

                    continue;
                }

                $records[$id] = $records[$id]->failed($outcome['error']);
                $report->failure(ReportFailure::make($outcome['error'], $records[$id]->action, $id));
            }
        }

        $report->recordMany($records->values());

        return $output->finish($report);
    }

    protected function uploadAction(LocalAssetRecord $record): ReconciliationAction
    {
        return filled($record->muxId) ? ReconciliationAction::Reupload : ReconciliationAction::Upload;
    }

    /** Proxy placeholder clips are never uploaded, not even with --force. */
    protected function skippedAction(LocalAssetRecord $record): ReconciliationAction
    {
        return MuxAsset::fromAsset($record->asset)->isProxy()
            ? ReconciliationAction::Skip
            : $this->actionFor($record->state, Side::Local);
    }

    protected function addAdvisories(CommandReport $report, $plan, Collection $uploads, bool $force): void
    {
        $replacements = $uploads->filter(
            fn (LocalAssetRecord $record) => ! $record->muxId && $record->candidates->isNotEmpty()
        );

        if ($replacements->isNotEmpty()) {
            $count = $replacements->count();

            $report->advisory(Advisory::warn(
                'upload-replacement',
                "{$count} ".($count === 1 ? 'asset already has' : 'assets already have').' attributable Mux encodings. Uploading will create replacements.',
                $replacements->map(fn (LocalAssetRecord $record) => "{$record->path()} → {$this->shortMuxId($record->selected?->id() ?? $record->candidates->first()?->id())}")->values()->all(),
                'Re-use them instead: php artisan mux:relink',
            ));
        }

        $proxySources = $uploads->filter(
            fn (LocalAssetRecord $record) => $record->state === ReconciliationState::ProxySource
        );

        if ($proxySources->isNotEmpty()) {
            $report->advisory(Advisory::warn(
                'proxy-source-conflict',
                $this->pluralize($proxySources->count(), 'local placeholder clip has', 'local placeholder clips have').' a full Mux encoding. Uploading would replace the full master with the short placeholder clip.',
                $proxySources->map(fn (LocalAssetRecord $record) => "{$record->path()} → {$this->shortMuxId($record->selected?->id())}")->values()->all(),
                'Re-link them first: php artisan mux:relink',
            ));
        }

        if ($force) {
            $report->advisory(Advisory::info(
                'force-upload',
                'Force mode re-uploads linked assets. Placeholder clips are never re-uploaded.',
            ));
        }

        // Encodings a re-link could still reuse are not orphans.
        $orphans = $plan->prunable()->count() - $plan->destructive()->count();

        if ($orphans > 0) {
            $report->advisory(Advisory::info(
                'orphaned-encodings',
                "{$orphans} orphaned Mux ".($orphans === 1 ? 'encoding is' : 'encodings are').' no longer needed.',
                hint: 'Remove them: php artisan mux:prune',
            ));
        }
    }
}
