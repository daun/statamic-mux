<?php

namespace Daun\StatamicMux\Mux\Enums;

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

    /**
     * Remote states whose encoding is no longer needed by any local file.
     */
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

    /**
     * Remote states where deleting would destroy the only encoding of a live file.
     */
    public function isDestructiveToPrune(): bool
    {
        return in_array($this, [self::Unlinked, self::ProxySource], true);
    }

    /**
     * The plain-language bucket shown in the control panel filter. The precise
     * state stays on the row; this is what an editor can act on.
     */
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
     * Group filter options for the control panel listing.
     *
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
