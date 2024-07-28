<?php

namespace Daun\StatamicMux\Listeners\Concerns;

use Daun\StatamicMux\Support\Queue;

trait UsesAddonQueue
{
    public function viaConnection(): ?string
    {
        return Queue::connection();
    }

    public function viaQueue(): ?string
    {
        return Queue::queue();
    }
}
