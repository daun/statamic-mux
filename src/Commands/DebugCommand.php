<?php

namespace Daun\StatamicMux\Commands;

use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\AdvisoryLevel;
use Daun\StatamicMux\Console\CommandOutput;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Support\MirrorField;
use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class DebugCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'mux:debug
                        {--json : Output a single machine-readable JSON object}';

    protected $description = 'Debug Mux configuration and setup';

    public function handle(MuxService $service): int
    {
        $output = CommandOutput::for($this);
        $report = $output->report(dryRun: false);

        $configured = $service->configured();
        $sync = Queue::isSync();
        $enabled = MirrorField::enabled();
        $containers = MirrorField::containers();
        $signed = config('mux.playback_policy') === 'signed';
        $signingKey = filled(config('mux.signing_key.key_id')) && filled(config('mux.signing_key.private_key'));

        $report
            ->context('Credentials', $configured ? 'OK' : 'MISSING')
            ->context('Queue', Queue::connection().($sync ? ' (not recommended)' : ' (background)'))
            ->context('Mirror feature', $enabled ? 'ON' : 'OFF')
            ->context('Containers', $containers->isNotEmpty() ? $containers->map->handle()->implode(', ') : 'NONE')
            ->context('Signed playback', $signed ? 'ON' : 'OFF');

        // Only a broken setup fails: a synchronous queue is a warning, not an error.
        if (! $configured) {
            $report->failure(ReportFailure::make('Mux is not configured. Please add valid Mux credentials in your .env file.'));
        }

        if (! $enabled) {
            $report->failure(ReportFailure::make('The mirror feature is globally disabled from the config flag.'));
        }

        if ($containers->isEmpty()) {
            $report->failure(ReportFailure::make('No asset containers found to mirror. Please add a `mux_mirror` field to at least one of your asset blueprints.'));
        }

        foreach ($report->failures() as $failure) {
            $report->advisory(Advisory::error('setup', $failure->error));
        }

        if ($sync) {
            $report->advisory(Advisory::warn(
                'sync-queue',
                'The queue is synchronous. Uploads will block the request.',
                hint: 'Configure a background queue worker for best performance.',
            ));
        }

        if ($signed && ! $signingKey) {
            $report->advisory(Advisory::warn(
                'signing-key',
                'The playback policy is signed, but no signing key is configured.',
                hint: 'Set MUX_SIGNING_KEY_ID and MUX_SIGNING_PRIVATE_KEY in your .env file.',
            ));
        }

        $this->summarize($report);

        return $output->finish($report);
    }

    protected function summarize(CommandReport $report): void
    {
        $failures = count($report->failures());

        if ($failures === 0) {
            $report->summary(AdvisoryLevel::Info, 'Debug complete — the Mux setup looks good.');

            return;
        }

        $report->summary(
            AdvisoryLevel::Error,
            'Debug failed — '.($failures === 1 ? '1 check needs' : "{$failures} checks need").' attention.',
        );
    }
}
