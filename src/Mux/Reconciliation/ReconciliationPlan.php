<?php

namespace Daun\StatamicMux\Mux\Reconciliation;

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Illuminate\Support\Collection;

readonly class ReconciliationPlan
{
    public function __construct(
        public Collection $locals,
        public Collection $remotes,
        public ?string $container = null,
    ) {}

    /**
     * Local records in any of the given states, within the plan's container scope.
     */
    public function local(ReconciliationState ...$states): Collection
    {
        return $this->scopedLocals()
            ->filter(fn (LocalAssetRecord $record) => in_array($record->state, $states, true))
            ->values();
    }

    /**
     * Remote records in any of the given states, within the plan's container scope.
     */
    public function remote(ReconciliationState ...$states): Collection
    {
        return $this->scopedRemotes()
            ->filter(fn (RemoteAssetRecord $record) => in_array($record->state, $states, true))
            ->values();
    }

    public function countLocal(ReconciliationState ...$states): int
    {
        return $this->local(...$states)->count();
    }

    public function countRemote(ReconciliationState ...$states): int
    {
        return $this->remote(...$states)->count();
    }

    public function relinkable(bool $force = false): Collection
    {
        return $this->scopedLocals()
            ->filter(fn (LocalAssetRecord $record) => $record->canRelink($force))
            ->values();
    }

    public function uploadable(): Collection
    {
        return $this->scopedLocals()
            ->filter(fn (LocalAssetRecord $record) => $record->needsUpload())
            ->values();
    }

    public function prunable(): Collection
    {
        return $this->scopedRemotes()->filter->isPrunable()->values();
    }

    /**
     * Prunable remotes whose deletion would destroy the only encoding of a live file.
     */
    public function destructive(): Collection
    {
        return $this->prunable()
            ->filter(fn (RemoteAssetRecord $record) => $record->state->isDestructiveToPrune())
            ->values();
    }

    /**
     * Remotes that could not be attributed to a container, so --container can't judge them.
     */
    public function unscopable(): Collection
    {
        if (! $this->container) {
            return collect();
        }

        return $this->remotes->filter(fn (RemoteAssetRecord $record) => $record->container === null)->values();
    }

    public function scopedLocals(): Collection
    {
        if (! $this->container) {
            return $this->locals;
        }

        return $this->locals->filter(fn (LocalAssetRecord $record) => $record->container() === $this->container)->values();
    }

    public function scopedRemotes(): Collection
    {
        if (! $this->container) {
            return $this->remotes;
        }

        return $this->remotes->filter(fn (RemoteAssetRecord $record) => $record->container === $this->container)->values();
    }
}
