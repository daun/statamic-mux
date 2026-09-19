<?php

namespace Daun\StatamicMux\Mux\Reconciliation;

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\RemoteVideo;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;

/** A remote Mux asset with its resolved attribution and reconciliation state. */
readonly class RemoteAssetRecord
{
    public function __construct(
        public RemoteVideo $remote,
        public ReconciliationState $state,
        public ?Asset $asset = null,
        public ?string $attributedAssetId = null,
        public ?string $reason = null,
        public ?string $container = null,
        public Collection $references = new Collection,
    ) {}

    public function id(): ?string
    {
        return $this->remote->id();
    }

    public function passthrough(): ?string
    {
        return $this->remote->passthrough();
    }

    public function proxyParentId(): ?string
    {
        return $this->remote->proxyParentId();
    }

    public function isPrunable(): bool
    {
        return $this->state->isPrunable();
    }

    public function isSafeCandidate(): bool
    {
        return in_array($this->state, [ReconciliationState::Unlinked, ReconciliationState::ProxySource], true);
    }

    /** True when the attributed local file is the short placeholder clip of this encoding. */
    public function isProxySource(): bool
    {
        return $this->state === ReconciliationState::ProxySource;
    }
}
