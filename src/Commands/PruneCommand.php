<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\HasOutputStyles;
use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;

class PruneCommand extends Command
{
    use HasOutputStyles;
    use RunsInPlease;

    protected $signature = 'mux:prune
                        {--dry-run : Perform a trial run with no removals and print a list of affected files}';

    protected $description = 'Remove orphaned videos from Mux';

    protected $dryrun;

    protected $sync;

    public function handle(MuxService $service): void
    {
        $this->dryrun = $this->option('dry-run');
        $this->sync = Queue::connection() === 'sync';

        if (! MirrorField::configured()) {
            $this->error('Mux is not configured. Please add valid Mux credentials in your .env file.');

            return;
        }

        if (! MirrorField::enabled()) {
            $this->error('The mirror feature is currently disabled.');

            return;
        }

        if ($this->dryrun) {
            $this->warn('Performing dry run: no videos will be deleted');
            $this->newLine();
        }

        $muxAssets = $service->listMuxAssets();
        $actualMuxIds = $muxAssets->pluck('id');

        if ($actualMuxIds->isEmpty()) {
            $this->line('No videos found on Mux');

            return;
        }

        $assets = MirrorField::assets();

        $localMuxIds = $assets->map(fn ($asset) => $service->muxId($asset))->filter();

        $orphans = $actualMuxIds->diff($localMuxIds);
        $orphans->each(function ($muxId) use ($service) {
            if ($this->dryrun) {
                $this->line("Would remove <name>{$muxId}</name>");
            } elseif ($this->sync) {
                $service->deleteMuxAsset($muxId);
                $this->line("Removed <name>{$muxId}</name>");
            } else {
                DeleteMuxAssetJob::dispatch($muxId);
                $this->line("Queued removal of <name>{$muxId}</name>");
            }
        })->whenNotEmpty(function () {
            $this->newLine();
        });

        $found = $actualMuxIds->intersect($localMuxIds);
        $found->each(function ($muxId) {
            if ($this->dryrun) {
                $this->line("Would keep <name>{$muxId}</name>");
            } else {
                $this->line("Keeping <name>{$muxId}</name>");
            }
        });

        $this->newLine();

        if ($this->dryrun) {
            $this->info("<success>✓ Would have removed {$orphans->count()} videos, kept {$found->count()} videos</success>");
        } elseif ($this->sync) {
            $this->info("<success>✓ Removed {$orphans->count()} videos, kept {$found->count()} videos</success>");
        } else {
            $this->info("<success>✓ Queued {$orphans->count()} videos for removal, kept {$found->count()} videos</success>");
        }
    }
}
