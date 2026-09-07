<?php

namespace Daun\StatamicMux\Mux\Reconciliation;

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;

/**
 * A local Statamic asset with its reconciliation state and re-link candidates.
 *
 * Candidates are the finalized remote records attributed to this asset, so a
 * candidate and its remote listing row always report the same state.
 */
readonly class LocalAssetRecord
{
    public function __construct(
        public Asset $asset,
        public ReconciliationState $state,
        public ?string $muxId = null,
        public ?RemoteAssetRecord $selected = null,
        public Collection $candidates = new Collection,
        public ?string $reason = null,
        public bool $proxySource = false,
    ) {}

    /**
     * Candidates that were evaluated but not selected.
     */
    public function alternatives(): Collection
    {
        return $this->candidates
            ->reject(fn (RemoteAssetRecord $record) => $record === $this->selected)
            ->values();
    }

    public function path(): string
    {
        return $this->asset->id();
    }

    public function container(): string
    {
        return $this->asset->containerHandle();
    }

    public function canRelink(bool $force = false): bool
    {
        if ($this->selected === null) {
            return false;
        }

        return in_array($this->state, [ReconciliationState::Unlinked, ReconciliationState::ProxySource], true)
            || ($force && $this->state === ReconciliationState::MediaMismatch);
    }

    public function needsUpload(): bool
    {
        return in_array($this->state, [ReconciliationState::Upload, ReconciliationState::Reupload], true);
    }

    public function isStale(): bool
    {
        return $this->state === ReconciliationState::Reupload;
    }
}
