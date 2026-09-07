<?php

namespace Daun\StatamicMux\Commands\Concerns;

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Support\MirrorField;
use Illuminate\Support\Collection;
use Statamic\Facades\AssetContainer;

/**
 * Shared plan loading and reporting for the reconciliation commands.
 */
trait InteractsWithReconciliation
{
    protected function buildPlan(Reconciler $reconciler, ?string $container = null): ?ReconciliationPlan
    {
        if (! MirrorField::configured()) {
            $this->error('Mux is not configured. Please add valid Mux credentials in your .env file.');

            return null;
        }

        if (! MirrorField::enabled()) {
            $this->error('The mirror feature is currently disabled.');

            return null;
        }

        if (MirrorField::containers()->isEmpty()) {
            $this->error('No containers found to mirror.');
            $this->line('Please add a `mux_mirror` field to at least one of your asset blueprints.');

            return null;
        }

        if ($container && ! AssetContainer::find($container)) {
            $this->error("Asset container '{$container}' not found");

            return null;
        }

        try {
            return $reconciler->plan($container);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return null;
        }
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

    protected function renderDiagnostics(ReconciliationPlan $plan): void
    {
        $nonReady = $plan->local(ReconciliationState::NonReadyLinked);

        if ($nonReady->isNotEmpty()) {
            $this->newLine();
            $this->warn($this->pluralize($nonReady->count(), 'video points', 'videos point').' at a Mux asset that did not become ready');
            foreach ($nonReady as $record) {
                $this->line("  {$record->path()} → {$record->muxId}  an older ready encoding still exists");
            }
        }

        $shared = $plan->remote(ReconciliationState::SharedReference);

        if ($shared->isNotEmpty()) {
            $this->newLine();
            $this->warn($this->pluralize($shared->count(), 'Mux asset is', 'Mux assets are').' referenced by more than one local asset');
            foreach ($shared as $record) {
                $references = $record->references
                    ->map(fn ($asset) => $asset->id().($asset->id() === $record->attributedAssetId ? ' (attributed)' : ''))
                    ->implode(' · ');
                $this->line("  {$record->id()}  {$references}");
            }
        }
    }

    protected function renderDestructiveWarning(Collection $destructive, string $verb): void
    {
        if ($destructive->isEmpty()) {
            return;
        }

        $files = $destructive->pluck('attributedAssetId')->filter()->unique()->count();
        $this->warn("⚠ {$destructive->count()} orphans are the only Mux encoding of {$this->pluralize($files, 'local asset', 'local assets')} still in your containers");
        $this->line("Prune {$verb} delete these. Re-link them first: php artisan mux:relink");
        $this->newLine();

        foreach ($destructive->groupBy('attributedAssetId') as $records) {
            $path = $records->first()->asset?->id() ?? 'unknown source';
            $label = $this->pluralize($records->count(), 'encoding', 'encodings');

            foreach ($records->values() as $index => $record) {
                $prefix = $index === 0
                    ? "   {$path}   {$label}"
                    : str_repeat(' ', strlen($path) + strlen($label) + 6);
                $this->line("{$prefix}   <name>{$this->shortMuxId($record->id())}</name>  {$record->remote->resolutionTier()}  {$this->shortDate($record)}");
            }
        }

        $this->newLine();
    }

    /**
     * @param  array<string, string>  $labels  state value => description
     */
    protected function renderStateCounts(Collection $records, array $labels): void
    {
        foreach ($labels as $state => $label) {
            $count = $records->where('state', ReconciliationState::from($state))->count();

            if ($count) {
                $this->line(sprintf('%3d %s', $count, $label));
            }
        }
    }

    protected function renderRecordList(string $label, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line("<comment>{$label}</comment>");

        foreach ($records as $record) {
            if ($record instanceof LocalAssetRecord) {
                $target = $record->selected ? ' → '.$record->selected->id() : '';
                $this->line("  {$record->path()}{$target}");
            } elseif ($record instanceof RemoteAssetRecord) {
                $this->line('  '.($record->asset?->id() ?? 'remote only').' → '.$record->id());
            }
        }
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
