<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandOutput;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Console\Formatter\OutputFormatter;

class RelinkCommand extends Command
{
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:relink
                        {--container= : Limit the command to a specific asset container}
                        {--force : Relink candidates that failed media validation}
                        {--dry-run : Perform a trial run with no changes and print a list of affected files}
                        {--json : Output a single machine-readable JSON object}';

    protected $description = 'Re-link local video assets to existing Mux encodings';

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

        $safe = $plan->relinkable();
        $mismatches = $plan->local(ReconciliationState::MediaMismatch);
        $selected = $force ? $plan->relinkable(force: true) : $safe;

        /** @var Collection<int, ReportRecord> $records */
        $records = collect();

        foreach ($safe as $record) {
            $records[spl_object_id($record)] = $this->localRecord($record, ReconciliationAction::Relink);
        }

        foreach ($mismatches as $record) {
            $records[spl_object_id($record)] = $this->localRecord(
                $record,
                $force && $record->selected ? ReconciliationAction::Relink : ReconciliationAction::Hold,
            );
        }

        foreach ($this->heldRemotes($plan) as $record) {
            $records[spl_object_id($record)] = $this->remoteRecord($record, ReconciliationAction::Hold);
        }

        $this->addAdvisories($report, $plan, $force);

        if ($dryRun) {
            $report->recordMany($records->values());

            return $output->finish($report);
        }

        if (! $force && ! $output->wantsJson() && $this->isInteractiveTerminal()) {
            $reviewable = $safe->concat($mismatches)->values();

            if ($reviewable->isNotEmpty()) {
                [$selected, $cancelled] = $this->review($reviewable, $selected);

                if ($cancelled) {
                    foreach ($reviewable as $record) {
                        $records[spl_object_id($record)] = $records[spl_object_id($record)]
                            ->withAction(ReconciliationAction::Hold, 'review cancelled');
                    }

                    $report->recordMany($records->values());

                    return $output->finish($report);
                }

                foreach ($reviewable as $record) {
                    $key = spl_object_id($record);

                    $records[$key] = $selected->containsStrict($record)
                        ? $records[$key]->withAction(ReconciliationAction::Relink)
                        : $records[$key]->withAction(ReconciliationAction::Hold, 'held during review');
                }
            }
        }

        foreach ($runner->relink($selected) as $outcome) {
            $key = spl_object_id($outcome['record']);

            if ($this->isSuccess($outcome)) {
                $records[$key] = $records[$key]->succeeded();

                continue;
            }

            $records[$key] = $records[$key]->failed($outcome['error']);
            $report->failure(ReportFailure::make($outcome['error'], ReconciliationAction::Relink, $outcome['record']->path()));
        }

        $report->recordMany($records->values());

        return $output->finish($report);
    }

    protected function heldRemotes(ReconciliationPlan $plan): Collection
    {
        return $plan->remote(
            ReconciliationState::AttributionConflict,
            ReconciliationState::Unattributable,
            ReconciliationState::UnmanagedSource,
            ReconciliationState::SharedReference,
        );
    }

    /**
     * @return array{0: Collection, 1: bool} the selected records and whether the run was cancelled
     */
    protected function review(Collection $reviewable, Collection $selected): array
    {
        $mode = $this->components->choice(
            "How should these {$reviewable->count()} assets be handled?",
            ['a' => 'Relink all safe matches', 'e' => 'Review individually', 'n' => 'Cancel'],
            'n',
        );

        if ($mode === 'Cancel') {
            return [collect(), true];
        }

        if ($mode === 'Review individually') {
            return [$reviewable->filter(fn (LocalAssetRecord $record) => $this->confirmRecord($record))->values(), false];
        }

        return [$selected, false];
    }

    protected function confirmRecord(LocalAssetRecord $record): bool
    {
        $this->components->twoColumnDetail(
            OutputFormatter::escape($record->path()).' <fg=gray>'.OutputFormatter::escape($record->state->reason()).'</>',
            OutputFormatter::escape($this->shortMuxId($record->selected?->id())),
        );

        if ($record->state === ReconciliationState::MediaMismatch) {
            return $this->components->confirm("Relink despite the media mismatch? ({$record->reason})", false);
        }

        return $this->components->confirm('Relink this asset?', false);
    }

    protected function addAdvisories(CommandReport $report, ReconciliationPlan $plan, bool $force): void
    {
        $mismatches = $plan->countLocal(ReconciliationState::MediaMismatch);

        if ($force && $mismatches) {
            $report->advisory(Advisory::warn(
                'force-relink',
                "Force mode re-links {$mismatches} candidates that failed media validation.",
            ));
        }

        $proxySources = $plan->local(ReconciliationState::ProxySource);

        if ($proxySources->isNotEmpty()) {
            $report->advisory(Advisory::info(
                'proxy-source-relink',
                $this->pluralize($proxySources->count(), 'placeholder clip is', 'placeholder clips are').' re-linked to their full Mux encoding.',
                $proxySources->map(fn (LocalAssetRecord $record) => "{$record->path()} → {$this->shortMuxId($record->selected?->id())}")->values()->all(),
            ));
        }

        $this->addDiagnosticAdvisories($report, $plan);

        if (($uploads = $plan->uploadable()->count()) > 0) {
            $report->advisory(Advisory::info(
                'uploadable-assets',
                "{$uploads} local ".($uploads === 1 ? 'video has' : 'videos have').' no Mux encoding to re-link to.',
                hint: 'Upload them: php artisan mux:upload',
            ));
        }
    }

    protected function isInteractiveTerminal(): bool
    {
        if (! $this->input->isInteractive() || ! function_exists('stream_isatty')) {
            return false;
        }

        return @stream_isatty(STDIN) && @stream_isatty(STDOUT);
    }
}
