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
            $this->line('<failure>✗</failure> Mux is not configured. Please add valid Mux credentials in your .env file.');
        } else {
            $this->line('<success>✓</success> Mux is configured with credentials.');
        }

        if (Queue::isSync()) {
            $this->line('<failure>✗</failure> The queue is set to synchronous mode. It is recommended to use a background queue worker for best performance.');
        } else {
            $this->line('<success>✓</success> The queue is configured to use a background worker.');
        }

        if (! MirrorField::enabled()) {
            $this->line('<failure>✗</failure> The mirror feature is globally disabled from the config flag.');
        } else {
            $this->line('<success>✓</success> The mirror feature is globally enabled.');
        }

        $containers = MirrorField::containers();
        if ($containers->isEmpty()) {
            $this->line('<failure>✗</failure> No asset containers found to mirror. Please add a `mux_mirror` field to at least one of your asset blueprints.');
        } else {
            $this->line("<success>✓</success> Found {$containers->count()} asset container(s) configured for mirroring: {$containers->map->handle()->implode(', ')}.");
        }
    }
}
