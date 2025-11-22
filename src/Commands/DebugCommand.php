<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Concerns\HasCommandOutputStyles;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class DebugCommand extends Command
{
    use HasCommandOutputStyles;
    use RunsInPlease;

    protected $signature = 'mux:debug';

    protected $description = 'Debug Mux configuration and setup';

    public function handle(MuxService $service): void
    {
        if (! $service->configured()) {
            $this->warn('× Mux is not configured. Please add valid Mux credentials in your .env file.');
        } else {
            $this->info('<success>✓ Mux is configured with credentials.</success>');
        }

        if (Queue::isSync()) {
            $this->warn('× The queue is set to synchronous mode. It is recommended to use a background queue worker for best performance.');
        } else {
            $this->info('<success>✓ The queue is configured to use a background worker.</success>');
        }

        if (! MirrorField::enabled()) {
            $this->warn('× The mirror feature is globally disabled from the config flag.');
        } else {
            $this->info('<success>✓ The mirror feature is globally enabled.</success>');
        }

        $containers = MirrorField::containers();
        if ($containers->isEmpty()) {
            $this->warn('× No asset containers found to mirror. Please add a `mux_mirror` field to at least one of your asset blueprints.');
        } else {
            $this->info("<success>✓ Found {$containers->count()} asset container(s) configured for mirroring: {$containers->map->handle()->implode(', ')}.</success>");
        }
    }
}
