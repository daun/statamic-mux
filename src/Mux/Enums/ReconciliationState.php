<?php

namespace Daun\StatamicMux\Mux\Enums;

use LogicException;

use function Statamic\trans as __;

enum ReconciliationState: string
{
    case Linked = 'linked';
    case SharedReference = 'shared-reference';
    case Upload = 'upload';
    case Reupload = 're-upload';
    case Unlinked = 'unlinked';
    case ProxySource = 'proxy-source';
    case Preparing = 'preparing';
    case Errored = 'errored';
    case UnknownStatus = 'unknown-status';
    case MediaMismatch = 'media-mismatch';
    case NonReadyLinked = 'non-ready-linked';
    case Superseded = 'superseded';
    case MissingSource = 'missing-source';
    case UnmanagedSource = 'unmanaged-source';
    case AttributionConflict = 'attribution-conflict';
    case Unattributable = 'unattributable';
    case Foreign = 'foreign';
    case ProxyInFlight = 'proxy-in-flight';
    case ExpiredProxy = 'expired-proxy';
    case OrphanedProxy = 'orphaned-proxy';

    /** Remote states whose encoding is no longer needed by any local file. */
    public function isPrunable(): bool
    {
        return in_array($this, [
            self::Superseded,
            self::MissingSource,
            self::Unlinked,
            self::ProxySource,
            self::Errored,
            self::UnmanagedSource,
            self::ExpiredProxy,
            self::OrphanedProxy,
        ], true);
    }

    /** Remote states where deleting would destroy the only encoding of a live file. */
    public function isDestructiveToPrune(): bool
    {
        return in_array($this, [self::Unlinked, self::ProxySource], true);
    }

    /**
     * A few states read differently per side: a proxy source is re-linkable
     * locally, but its old encoding is a prune candidate remotely.
     *
     * @throws LogicException if the state cannot occur on the given side
     */
    public function action(Side $side): ReconciliationAction
    {
        return $side->isLocal() ? $this->localAction() : $this->remoteAction();
    }

    public function reason(): string
    {
        return match ($this) {
            self::Linked => __('linked to a ready Mux encoding'),
            self::SharedReference => __('shared between multiple local assets'),
            self::Upload => __('local videos not yet on Mux'),
            self::Reupload => __('linked encoding is missing or stale'),
            self::Unlinked => __('local asset exists but is unlinked'),
            self::ProxySource => __('placeholder clip source is unlinked'),
            self::Preparing => __('still preparing'),
            self::Errored => __('encoding errored'),
            self::UnknownStatus => __('unknown processing status'),
            self::MediaMismatch => __('media validation failed'),
            self::NonReadyLinked => __('linked encoding did not become ready'),
            self::Superseded => __('superseded by a newer upload'),
            self::MissingSource => __('local asset no longer exists'),
            self::UnmanagedSource => __('local asset has no Mux field'),
            self::AttributionConflict => __('attribution conflict'),
            self::Unattributable => __('unattributable addon asset'),
            self::Foreign => __('not created by this addon'),
            self::ProxyInFlight => __('placeholder clip still in flight'),
            self::ExpiredProxy => __('expired placeholder clip'),
            self::OrphanedProxy => __('placeholder parent is gone'),
        };
    }

    protected function localAction(): ReconciliationAction
    {
        return match ($this) {
            self::Linked => ReconciliationAction::Keep,
            self::Upload => ReconciliationAction::Upload,
            self::Reupload => ReconciliationAction::Reupload,
            self::Unlinked, self::ProxySource => ReconciliationAction::Relink,
            self::MediaMismatch, self::NonReadyLinked, self::UnknownStatus => ReconciliationAction::Hold,
            self::Preparing => ReconciliationAction::Skip,
            default => throw new LogicException("Reconciliation state [{$this->value}] cannot occur on the local side."),
        };
    }

    protected function remoteAction(): ReconciliationAction
    {
        return match ($this) {
            self::Linked => ReconciliationAction::Keep,
            self::Superseded, self::MissingSource, self::Unlinked, self::ProxySource,
            self::Errored, self::UnmanagedSource, self::ExpiredProxy,
            self::OrphanedProxy => ReconciliationAction::Prune,
            self::SharedReference, self::AttributionConflict, self::MediaMismatch,
            self::UnknownStatus => ReconciliationAction::Hold,
            self::Foreign, self::Unattributable => ReconciliationAction::Ignore,
            self::Preparing, self::ProxyInFlight => ReconciliationAction::Skip,
            default => throw new LogicException("Reconciliation state [{$this->value}] cannot occur on the remote side."),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Linked, self::SharedReference, self::NonReadyLinked => 'linked',
            self::Unlinked, self::ProxySource => 'relinkable',
            self::Superseded, self::MissingSource, self::Errored,
            self::ExpiredProxy, self::OrphanedProxy => 'unused',
            self::MediaMismatch, self::AttributionConflict,
            self::Unattributable, self::UnmanagedSource => 'needs-attention',
            self::Preparing, self::ProxyInFlight, self::UnknownStatus => 'processing',
            self::Foreign => 'foreign',
            self::Upload, self::Reupload => 'relinkable',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            'linked' => __('Linked'),
            'relinkable' => __('Can be re-linked'),
            'unused' => __('Unused'),
            'needs-attention' => __('Needs attention'),
            'processing' => __('Still processing'),
            'foreign' => __('Not from Statamic'),
        ];
    }
}
