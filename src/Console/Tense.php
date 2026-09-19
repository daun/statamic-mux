<?php

namespace Daun\StatamicMux\Console;

enum Tense: string
{
    case Planned = 'planned';
    case Queued = 'queued';
    case Applied = 'applied';

    public static function for(bool $dryRun, bool $sync): self
    {
        return match (true) {
            $dryRun => self::Planned,
            $sync => self::Applied,
            default => self::Queued,
        };
    }

    public function isPlanned(): bool
    {
        return $this === self::Planned;
    }

    public function isQueued(): bool
    {
        return $this === self::Queued;
    }

    public function isApplied(): bool
    {
        return $this === self::Applied;
    }
}
