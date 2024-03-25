<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\HasOutputStyles;
use Daun\StatamicMux\Features\Mirror as MirrorFeature;
use Daun\StatamicMux\Features\Queue;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

class Upload extends Command
{
    use HasOutputStyles;
    use RunsInPlease;

    protected $signature = 'mux:upload
                        {--container= : Limit the upload to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}';

    protected $description = 'Upload local video assets to Mux';

    protected $container;
    protected $force;
    protected $dryrun;
    protected $sync;

    protected $containers;

    public function handle(MuxService $service): void
    {
        $this->container = $this->option('container');
        $this->force = $this->option('force');
        $this->dryrun = $this->option('dry-run');
        $this->sync = Queue::connection() === 'sync';

        if (!MirrorFeature::configured()) {
            $this->error('Mux is not configured. Please add valid Mux credentials in your .env file.');
            return;
        }

        if (!MirrorFeature::enabled()) {
            $this->error('The mirror feature is currently disabled.');
            return;
        }

        $this->containers = MirrorFeature::containers();
        if ($this->containers->isEmpty()) {
            $this->error('No containers found to mirror.');
            $this->newLine();
            $this->line('Please add a `mux_mirror` field to at least one of your asset blueprints.');
            return;
        }

        if ($this->container) {
            $container = AssetContainer::find($this->container);
            if ($container) {
                $this->containers = collect($container);
            } else {
                $this->error("Asset container '{$this->container}' not found");
                return;
            }
        }

        if ($this->dryrun) {
            $this->warn('Performing dry run: no videos will be uploaded');
            $this->newLine();
        }

        $assets = $this->containers->flatMap(
            fn($container) => Asset::whereContainer($container->handle())->filter(
                fn($asset) => MirrorFeature::enabledForAsset($asset)
            )
        );

        if ($assets->isEmpty()) {
            $this->line("No videos found in containers: <name>{$this->containers->implode(', ')}</name>");
            return;
        }

        $assetGroups = $assets->mapToGroups(function ($asset) use ($service) {
            $exists = $service->hasExistingMuxAsset($asset);
            $action = !$exists ? 'upload' : ($this->force ? 'reupload' : 'skip');
            return [$action => $asset];
        });

        $assetsToUpload = $assetGroups->get('upload', collect());
        $assetsToReupload = $assetGroups->get('reupload', collect());
        $assetsToSkip = $assetGroups->get('skip', collect());

        $assetsToUpload->each(function ($asset) use ($service) {
            if ($this->dryrun) {
                $this->line("Would upload <name>{$asset->id()}</name>");
            } else if ($this->sync) {
                $service->createMuxAsset($asset);
                $this->line("Uploaded <name>{$asset->id()}</name>");
            } else {
                CreateMuxAssetJob::dispatch($asset->id());
                $this->line("Queued upload of <name>{$asset->id()}</name>");
            }
        })->whenNotEmpty(function() {
            $this->newLine();
        });

        $assetsToReupload->each(function ($asset) use ($service) {
            if ($this->dryrun) {
                $this->line("Would reupload <name>{$asset->id()}</name>");
            } else if ($this->sync) {
                $service->createMuxAsset($asset, true);
                $this->line("Reuploaded <name>{$asset->id()}</name>");
            } else {
                CreateMuxAssetJob::dispatch($asset->id(), true);
                $this->line("Queued reupload of <name>{$asset->id()}</name>");
            }
        })->whenNotEmpty(function() {
            $this->newLine();
        });

        $assetsToSkip->each(function ($asset) {
            if ($this->dryrun) {
                $this->line("Would skip <name>{$asset->id()}</name>");
            } else {
                $this->line("Skipped <name>{$asset->id()}</name>");
            }
        });

        $this->newLine();

        $uploaded = $assetsToUpload->count() + $assetsToReupload->count();
        $skipped = $assetsToSkip->count();

        if ($this->dryrun) {
            $this->info("<success>✓ Would have uploaded {$uploaded} videos, skipped {$skipped} videos</success>");
        } else if ($this->sync) {
            $this->info("<success>✓ Uploaded {$uploaded} videos, skipped {$skipped} videos</success>");
        } else {
            $this->info("<success>✓ Queued {$uploaded} videos for background upload, skipped {$skipped} videos</success>");
        }
    }
}
