<?php

namespace Daun\StatamicMux\Commands\Concerns;

use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Enums\Side;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Support\Collection;
use Statamic\Facades\AssetContainer;

/**
 * Nothing in here prints: every outcome is recorded on the report and rendered
 * once, by the renderer or as the `--json` object.
 */
trait InteractsWithReconciliation
{
    protected function buildPlan(Reconciler $reconciler, CommandReport $report, ?string $container = null): ?ReconciliationPlan
    {
        if (! MirrorField::configured()) {
            return $this->failPrecondition($report, 'Mux is not configured. Please add valid Mux credentials in your .env file.');
        }

        if (! MirrorField::enabled()) {
            return $this->failPrecondition($report, 'The mirror feature is currently disabled.');
        }

        if (MirrorField::containers()->isEmpty()) {
            return $this->failPrecondition($report, 'No containers found to mirror. Please add a `mux_mirror` field to at least one of your asset blueprints.');
        }

        if ($container && ! AssetContainer::find($container)) {
            return $this->failPrecondition($report, "Asset container '{$container}' not found.");
        }

        try {
            return $reconciler->plan($container);
        } catch (\Throwable $exception) {
            return $this->failPrecondition($report, $exception->getMessage());
        }
    }

    protected function failPrecondition(CommandReport $report, string $message): null
    {
        $report->failure(ReportFailure::make($message))->abort($message);

        return null;
    }

    /** Scope rows are always the scoped inventory, never the full plan. */
    protected function describeScope(CommandReport $report, ReconciliationPlan $plan, ?string $container = null): void
    {
        $report
            ->scope(
                containers: array_filter([$container]),
                locals: $plan->scopedLocals()->count(),
                remotes: $plan->scopedRemotes()->count(),
            )
            ->context('Queue', $this->queueLabel());
    }

    protected function queueLabel(): string
    {
        $connection = Queue::connection() ?: 'default';

        return $connection.(Queue::isSync() ? ' (foreground)' : ' (background)');
    }

    protected function actionFor(ReconciliationState $state, Side $side): ReconciliationAction
    {
        try {
            return $state->action($side);
        } catch (\LogicException) {
            return ReconciliationAction::Skip;
        }
    }

    protected function localRecord(LocalAssetRecord $record, ReconciliationAction $action, ?string $reason = null): ReportRecord
    {
        return ReportRecord::make(
            action: $action,
            id: $record->path(),
            state: $record->state,
            reason: $reason,
            diagnostics: $this->localDiagnostics($record),
        );
    }

    protected function remoteRecord(RemoteAssetRecord $record, ReconciliationAction $action, ?string $reason = null): ReportRecord
    {
        $muxId = $record->id() ?? 'unknown';
        $source = $record->asset?->id() ?? $record->attributedAssetId;

        return ReportRecord::make(
            action: $action,
            id: $muxId,
            state: $record->state,
            reason: $reason,
            label: $this->shortMuxId($muxId).($source ? " → {$source}" : ''),
            diagnostics: $this->remoteDiagnostics($record),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function localDiagnostics(LocalAssetRecord $record): array
    {
        $asset = $record->asset;
        $diagnostics = array_filter([
            'container' => $record->container(),
            'mux id' => $record->muxId,
            'selected' => $record->selected?->id(),
            'detail' => $record->reason,
            'dimensions' => $asset->width() && $asset->height() ? "{$asset->width()}×{$asset->height()}" : null,
            'duration' => $asset->duration() !== null ? "{$asset->duration()}s" : null,
        ]);

        foreach ($record->candidates as $index => $candidate) {
            $video = $candidate->remote;
            $role = $candidate === $record->selected ? 'selected' : 'discarded';
            $diagnostics['candidate '.($index + 1)] = implode('  ', array_filter([
                $candidate->id(),
                $role,
                $video->duration() !== null ? "{$video->duration()}s" : null,
                $video->aspectRatioLabel(),
                $video->resolutionTier(),
                $video->status(),
                $video->createdAt()?->format('Y-m-d'),
                $candidate->reason,
            ]));
        }

        return $diagnostics;
    }

    /**
     * @return array<string, string>
     */
    protected function remoteDiagnostics(RemoteAssetRecord $record): array
    {
        return array_filter([
            'source' => $record->asset?->id() ?? $record->attributedAssetId,
            'container' => $record->container,
            'resolution' => $record->remote->resolutionTier(),
            'status' => $record->remote->status(),
            'created' => $this->shortDate($record),
            'detail' => $record->reason,
            'references' => $record->references->isNotEmpty()
                ? $record->references->map(fn ($asset) => $asset->id())->implode(' · ')
                : null,
        ]);
    }

    protected function addDiagnosticAdvisories(CommandReport $report, ReconciliationPlan $plan): void
    {
        $nonReady = $plan->local(ReconciliationState::NonReadyLinked);

        if ($nonReady->isNotEmpty()) {
            $report->advisory(Advisory::warn(
                'non-ready-linked',
                $this->pluralize($nonReady->count(), 'video points', 'videos point').' at a Mux asset that did not become ready.',
                $nonReady->map(fn (LocalAssetRecord $record) => "{$record->path()} → {$record->muxId}")->values()->all(),
            ));
        }

        $shared = $plan->remote(ReconciliationState::SharedReference);

        if ($shared->isNotEmpty()) {
            $report->advisory(Advisory::warn(
                'shared-reference',
                $this->pluralize($shared->count(), 'Mux asset is', 'Mux assets are').' referenced by more than one local asset.',
                $shared->map(function (RemoteAssetRecord $record) {
                    $references = $record->references
                        ->map(fn ($asset) => $asset->id().($asset->id() === $record->attributedAssetId ? ' (attributed)' : ''))
                        ->implode(' · ');

                    return "{$record->id()}  {$references}";
                })->values()->all(),
            ));
        }
    }

    /** Remotes with no resolvable container cannot be judged by --container. */
    protected function addUnscopableAdvisory(CommandReport $report, ReconciliationPlan $plan, ?string $container): Collection
    {
        $unscopable = $plan->unscopable();

        if ($unscopable->isEmpty()) {
            return $unscopable;
        }

        $report->advisory(Advisory::warn(
            'unscopable-container',
            "{$unscopable->count()} Mux assets could not be scoped to --container={$container} and were skipped.",
            $unscopable->map(fn (RemoteAssetRecord $record) => $this->shortMuxId($record->id()))->values()->all(),
            'Run without --container to review them.',
        ));

        return $unscopable;
    }

    protected function reportable(Collection $outcomes): Collection
    {
        return $outcomes->where('status', '!==', ReconciliationRunner::SKIPPED)->values();
    }

    protected function isSuccess(array $outcome): bool
    {
        return $outcome['status'] === ReconciliationRunner::SUCCESS;
    }

    protected function succeeded(Collection $outcomes): Collection
    {
        return $outcomes->where('status', ReconciliationRunner::SUCCESS)->values();
    }

    protected function failures(Collection $outcomes): Collection
    {
        return $outcomes->where('status', ReconciliationRunner::FAILURE)->values();
    }

    protected function succeededMuxIds(Collection $outcomes): Collection
    {
        return $this->succeeded($outcomes)->pluck('mux_id')->filter()->values();
    }

    protected function shortMuxId(?string $muxId): string
    {
        if (! $muxId) {
            return 'unknown';
        }

        return strlen($muxId) <= 16 ? $muxId : substr($muxId, 0, 12).'…';
    }

    protected function shortDate(RemoteAssetRecord $record): string
    {
        return $record->remote->createdAt()?->format('Y-m-d') ?? 'unknown date';
    }

    protected function pluralize(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
