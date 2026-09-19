<?php

namespace Daun\StatamicMux\Mux\Enums;

use Daun\StatamicMux\Console\Tense;

use function Statamic\trans as __;
use function Statamic\trans_choice as __choice;

enum ReconciliationAction: string
{
    case Relink = 'relink';
    case Upload = 'upload';
    case Reupload = 're-upload';
    case Prune = 'prune';
    case Keep = 'keep';
    case Hold = 'hold';
    case Ignore = 'ignore';
    case Skip = 'skip';

    public const FAILED_TOKEN = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Relink => __('relink'),
            self::Upload => __('upload'),
            self::Reupload => __('re-upload'),
            self::Prune => __('prune'),
            self::Keep => __('keep'),
            self::Hold => __('hold'),
            self::Ignore => __('ignore'),
            self::Skip => __('skip'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Relink, self::Upload => 'green',
            self::Reupload, self::Hold => 'yellow',
            self::Prune => 'red',
            self::Keep, self::Ignore, self::Skip => 'gray',
        };
    }

    /** Internal actions are counted and serialized, but never rendered. */
    public function isInternal(): bool
    {
        return $this === self::Skip;
    }

    public function isVisible(): bool
    {
        return ! $this->isInternal();
    }

    public function token(Tense $tense): string
    {
        return match ($this) {
            self::Relink => match ($tense) {
                Tense::Planned => 'RELINK',
                Tense::Queued => 'QUEUED',
                Tense::Applied => 'RELINKED',
            },
            self::Upload => match ($tense) {
                Tense::Planned => 'UPLOAD',
                Tense::Queued => 'QUEUED',
                Tense::Applied => 'UPLOADED',
            },
            self::Reupload => match ($tense) {
                Tense::Planned => 'RE-UPLOAD',
                Tense::Queued => 'QUEUED',
                Tense::Applied => 'RE-UPLOADED',
            },
            self::Prune => match ($tense) {
                Tense::Planned => 'PRUNE',
                Tense::Queued => 'QUEUED',
                Tense::Applied => 'PRUNED',
            },
            self::Keep => $tense->isPlanned() ? 'KEEP' : 'KEPT',
            self::Hold => 'HELD',
            self::Ignore => 'IGNORED',
            self::Skip => 'SKIPPED',
        };
    }

    public function sentence(Tense $tense, int $count): string
    {
        $replace = ['count' => $count];

        return match ($this) {
            self::Relink => match ($tense) {
                Tense::Planned => __choice(':count re-link|:count re-links', $count, $replace),
                Tense::Queued => __(':count queued for re-linking', $replace),
                Tense::Applied => __(':count re-linked', $replace),
            },
            self::Upload => match ($tense) {
                Tense::Planned => __choice(':count upload|:count uploads', $count, $replace),
                Tense::Queued => __(':count queued for upload', $replace),
                Tense::Applied => __(':count uploaded', $replace),
            },
            self::Reupload => match ($tense) {
                Tense::Planned => __choice(':count re-upload|:count re-uploads', $count, $replace),
                Tense::Queued => __(':count queued for re-upload', $replace),
                Tense::Applied => __(':count re-uploaded', $replace),
            },
            self::Prune => match ($tense) {
                Tense::Planned => __choice(':count prune|:count prunes', $count, $replace),
                Tense::Queued => __(':count queued for removal', $replace),
                Tense::Applied => __(':count pruned', $replace),
            },
            self::Keep => __(':count kept', $replace),
            self::Hold => __(':count held', $replace),
            self::Ignore => __(':count ignored', $replace),
            self::Skip => __(':count skipped', $replace),
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Relink => 0,
            self::Upload => 1,
            self::Reupload => 2,
            self::Prune => 3,
            self::Keep => 4,
            self::Hold => 5,
            self::Ignore => 6,
            self::Skip => 7,
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        $actions = self::cases();
        usort($actions, fn (self $a, self $b) => $a->order() <=> $b->order());

        return $actions;
    }

    /**
     * @return list<self>
     */
    public static function visible(): array
    {
        return array_values(array_filter(self::ordered(), fn (self $action) => $action->isVisible()));
    }
}
