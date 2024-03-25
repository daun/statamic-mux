<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Commands\Concerns\HasOutputStyles;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class Mirror extends Command
{
    use HasOutputStyles;
    use RunsInPlease;

    protected $signature = 'mux:mirror
                        {--container= : Limit the command to a specific asset container}
                        {--force : Reupload videos to Mux even if they already exist}
                        {--dry-run : Perform a trial run with no uploads and print a list of affected files}';

    protected $description = 'Mirror local video assets with Mux';

    public function handle(): void
    {
        $this->info('Upload local videos to Mux ...');
        $this->newLine();

        $this->call('mux:upload', [
            '--container' => $this->option('container'),
            '--force' => $this->option('force'),
            '--dry-run' => $this->option('dry-run'),
        ]);

        $this->newLine();

        $this->info('Pruning orphaned videos on Mux ...');
        $this->newLine();

        $this->call('mux:prune', [
            '--dry-run' => $this->option('dry-run'),
        ]);
    }
}
