<?php

namespace Daun\StatamicMux\Mux\Enums;

enum Side: string
{
    case Local = 'local';
    case Remote = 'remote';

    public function isLocal(): bool
    {
        return $this === self::Local;
    }

    public function isRemote(): bool
    {
        return $this === self::Remote;
    }
}
