<?php

use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\WorkerOptions;
use Statamic\Assets\Asset;

it('fails after one automatic attempt even when the worker allows retries', function () {
    $asset = $this->uploadTestFileToTestContainer('test.mp4', 'ambiguous-failure.mp4');
    $action = Mockery::mock(CreateMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Asset::class), false)
        ->andThrow(new RuntimeException('Ambiguous creation failure'));
    $this->app->instance(CreateMuxAsset::class, $action);

    $job = new CreateMuxAssetJob($asset);
    $connection = $this->app['queue']->connection('sync');
    $payload = (function (CreateMuxAssetJob $job): string {
        return $this->createPayload($job, 'default');
    })->call($connection, $job);
    $queuedJob = new SyncJob($this->app, $payload, 'sync', 'default');

    expect(fn () => $this->app['queue.worker']->process(
        'sync',
        $queuedJob,
        new WorkerOptions(maxTries: 5),
    ))->toThrow(RuntimeException::class, 'Ambiguous creation failure');

    expect($queuedJob->maxTries())->toBe(1)
        ->and($queuedJob->hasFailed())->toBeTrue()
        ->and($queuedJob->isReleased())->toBeFalse();
});
