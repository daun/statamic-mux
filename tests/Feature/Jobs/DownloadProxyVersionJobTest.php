<?php

use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Jobs\DeleteReplacedMuxAssetJob;
use Daun\StatamicMux\Jobs\DownloadProxyVersionJob;
use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Illuminate\Support\Facades\Queue;

it('uses generic string-id cleanup after downloading a proxy', function () {
    Queue::fake();

    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(DownloadProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('handle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();

    (new DownloadProxyVersionJob($asset, 'PROXY-ID'))->handle($action);

    Queue::assertPushed(DeleteMuxAssetJob::class, function (DeleteMuxAssetJob $job) {
        $asset = (new ReflectionClass($job))->getProperty('asset')->getValue($job);

        return $asset === 'PROXY-ID';
    });
    Queue::assertNotPushed(DeleteReplacedMuxAssetJob::class);
});
