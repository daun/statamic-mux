<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Jobs\DeleteReplacedMuxAssetJob;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use Daun\StatamicMux\Mux\MuxApi;
use Illuminate\Queue\Jobs\FakeJob;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset as MuxApiAsset;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->api = Mockery::mock(MuxApi::class);
    $this->action = Mockery::mock(DeleteMuxAsset::class);
});

it('checks current references before fetching the predecessor', function () {
    $this->addMirrorFieldToAssetBlueprint();
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    MuxAsset::fromAsset($asset)->withId('MUX-ID')->save();
    Stache::clear();

    $this->api->shouldNotReceive('getAsset');
    $this->action->shouldNotReceive('handle');

    (new DeleteReplacedMuxAssetJob('MUX-ID'))->handle($this->action, $this->api);
});

it('completes when the predecessor is missing', function () {
    $this->api->shouldReceive('getAsset')->once()->with('MISSING-ID')->andReturnNull();
    $this->action->shouldNotReceive('handle');

    $job = (new DeleteReplacedMuxAssetJob('MISSING-ID'))->withFakeQueueInteractions();
    $job->handle($this->action, $this->api);

    $job->assertNotReleased();
});

it('releases a processing predecessor with an increasing delay for each attempt', function (int $attempt, int $delay) {
    $remoteAsset = new MuxApiAsset([
        'id' => 'PROCESSING-ID',
        'status' => MuxApiAsset::STATUS_PREPARING,
    ]);

    $this->api->shouldReceive('getAsset')->once()->with('PROCESSING-ID')->andReturn($remoteAsset);
    $this->action->shouldNotReceive('handle');

    $fakeJob = new FakeJob;
    $fakeJob->attempts = $attempt;
    $job = (new DeleteReplacedMuxAssetJob('PROCESSING-ID'))->setJob($fakeJob);
    $job->handle($this->action, $this->api);

    $job->assertReleased($delay);
})->with([
    'first attempt' => [1, 60],
    'second attempt' => [2, 180],
    'third attempt' => [3, 300],
    'fourth attempt' => [4, 600],
    'fifth attempt' => [5, 1200],
    'sixth attempt' => [6, 1800],
    'seventh attempt' => [7, 3600],
    'eighth attempt' => [8, 10800],
    'later attempt' => [9, 10800],
]);

it('deletes a ready predecessor using the fetched remote asset', function () {
    $remoteAsset = new MuxApiAsset([
        'id' => 'READY-ID',
        'status' => MuxApiAsset::STATUS_READY,
        'passthrough' => 'statamic::video.mp4',
    ]);

    $this->api->shouldReceive('getAsset')->once()->with('READY-ID')->andReturn($remoteAsset);
    $this->action->shouldReceive('handle')->once()->with($remoteAsset)->andReturnTrue();

    (new DeleteReplacedMuxAssetJob('READY-ID'))->handle($this->action, $this->api);
});

it('deletes an errored predecessor without releasing the job', function () {
    $remoteAsset = new MuxApiAsset([
        'id' => 'ERRORED-ID',
        'status' => MuxApiAsset::STATUS_ERRORED,
        'passthrough' => 'statamic::video.mp4',
    ]);

    $this->api->shouldReceive('getAsset')->once()->with('ERRORED-ID')->andReturn($remoteAsset);
    $this->action->shouldReceive('handle')->once()->with($remoteAsset)->andReturnTrue();

    $job = (new DeleteReplacedMuxAssetJob('ERRORED-ID'))->withFakeQueueInteractions();
    $job->handle($this->action, $this->api);

    $job->assertNotReleased();
});

it('retries for 24 hours with minute-scale exception backoff', function () {
    $this->travelTo(now()->startOfHour());
    $job = new DeleteReplacedMuxAssetJob('MUX-ID');

    expect($job->backoff())
        ->toBe([60, 180, 300, 600, 1200, 1800, 3600, 10800])
        ->and($job->retryUntil()->equalTo(now()->addDay()))->toBeTrue();
});

it('leaves non-404 lookup failures visible to the queue', function () {
    $exception = new ApiException('Mux unavailable', 503);

    $this->api->shouldReceive('getAsset')->once()->with('MUX-ID')->andThrow($exception);
    $this->action->shouldNotReceive('handle');

    expect(fn () => (new DeleteReplacedMuxAssetJob('MUX-ID'))->handle($this->action, $this->api))
        ->toThrow(ApiException::class, 'Mux unavailable');
});

it('leaves non-404 deletion failures visible to the queue', function () {
    $remoteAsset = new MuxApiAsset([
        'id' => 'MUX-ID',
        'status' => MuxApiAsset::STATUS_READY,
        'passthrough' => 'statamic::video.mp4',
    ]);
    $exception = new ApiException('Mux delete unavailable', 503);

    $this->api->shouldReceive('getAsset')->once()->with('MUX-ID')->andReturn($remoteAsset);
    $this->action->shouldReceive('handle')->once()->with($remoteAsset)->andThrow($exception);

    expect(fn () => (new DeleteReplacedMuxAssetJob('MUX-ID'))->handle($this->action, $this->api))
        ->toThrow(ApiException::class, 'Mux delete unavailable');
});
