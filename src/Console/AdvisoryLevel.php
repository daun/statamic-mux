<?php

namespace Daun\StatamicMux\Console;

enum AdvisoryLevel: string
{
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
}
