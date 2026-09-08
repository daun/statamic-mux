<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\InteractsWithReconciliation;
use Daun\StatamicMux\Concerns\HasCommandOutputStyles;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationRunner;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class UploadCommand extends Command
{
    use HasCommandOutputStyles;
    use InteractsWithReconciliation;
    use RunsInPlease;

    protected $signature = 'mux:upload
                        {--container= : Limit the command to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}';

    protected $description = 'Upload local video assets to Mux';

    public function handle(Reconciler $reconciler, ReconciliationRunner $runner): int
    {
        $container = $this->option('container');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $sync = Queue::isSync();

        if (! $plan = $this->buildPlan($reconciler, $container)) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Performing dry run: no videos will be uploaded');
            $this->newLine();
        }

        $locals = $plan->scopedLocals();

        if ($locals->isEmpty()) {
            $this->line('No videos found'.($container ? " in container: <name>{$container}</name>" : ''));

            return self::SUCCESS;
        }

        [$uploads, $skipped] = $locals->partition(function (LocalAssetRecord $record) use ($force) {
            if (MuxAsset::fromAsset($record->asset)->isProxy()) {
                return false;
            }

            return $force || ! $record->muxId || $record->isStale();
        });

        $this->warnAboutReplacements($uploads);

        if ($dryRun) {
            foreach ($uploads as $record) {
                $verb = $force && filled($record->muxId) && ! $record->isStale() ? 'reupload' : 'upload';
                $this->line("Would {$verb} <name>{$record->path()}</name>");
            }
        } else {
            foreach ($runner->upload($uploads, $force) as $outcome) {
                if (! $this->isSuccess($outcome)) {
                    $this->error("Failed to upload {$outcome['record']->path()}: {$outcome['error']}");

                    continue;
                }

                $verb = $sync ? ($outcome['reupload'] ? 'Reuploaded' : 'Uploaded') : 'Queued '.($outcome['reupload'] ? 'reupload' : 'upload').' of';
                $this->line("{$verb} <name>{$outcome['record']->path()}</name>");
            }
        }

        if ($this->getOutput()->isVerbose()) {
            foreach ($skipped as $record) {
                $this->line(($dryRun ? 'Would skip' : 'Skipped')." <name>{$record->path()}</name>");
            }
        }

        $summary = match (true) {
            $dryRun => "Would have uploaded {$uploads->count()} videos",
            $sync => "Uploaded {$uploads->count()} videos",
            default => "Queued {$uploads->count()} videos for background upload",
        };

        $this->newLine();
        $this->info("<success>✓ {$summary}, skipped {$skipped->count()} videos</success>");

        return self::SUCCESS;
    }

    protected function warnAboutReplacements($uploads): void
    {
        $candidates = $uploads->filter(
            fn (LocalAssetRecord $record) => ! $record->muxId && $record->candidates->isNotEmpty()
        );

        if ($candidates->isEmpty()) {
            return;
        }

        $count = $candidates->count();
        $this->warn("{$count} ".($count === 1 ? 'asset already has' : 'assets already have').' attributable Mux encodings; upload will create replacements.');
        $this->line('Use mux:relink or mux:mirror to reuse them instead.');

        if ($candidates->contains(fn (LocalAssetRecord $record) => $record->state === ReconciliationState::ProxySource)) {
            $this->warn('A placeholder source is included. Uploading it would replace the full master with the short placeholder clip.');
        }

        $this->newLine();
    }
}
