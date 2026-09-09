<?php

namespace Daun\StatamicMux\Console;

enum RecordStatus: string
{
    case Planned = 'planned';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
