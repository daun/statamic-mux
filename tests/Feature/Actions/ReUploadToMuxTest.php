<?php

use Daun\StatamicMux\Actions\ReUploadToMux;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

beforeEach(function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 'mux-asset-001']);
    $this->mp4->save();

    Stache::clear();
});

test('authorize requires manage mux permission', function () {
    $action = new ReUploadToMux;

    expect($action->authorize(userWithMuxPermission('manage mux'), null))->toBeTrue();
    expect($action->authorize(User::make()->makeSuper(), null))->toBeTrue();
    expect($action->authorize(userWithMuxPermission('view mux library'), null))->toBeFalse();
    expect($action->authorize(userWithMuxPermission(null), null))->toBeFalse();
});

test('visible to mux assets that already exist on mux', function () {
    $action = new ReUploadToMux;

    expect($action->visibleTo(MuxAsset::fromAsset($this->mp4)))->toBeTrue();
});

test('not visible to unuploaded or proxy assets', function () {
    $action = new ReUploadToMux;

    expect($action->visibleTo(new MuxAsset([], $this->mp4)))->toBeFalse();
    expect($action->visibleTo((new MuxAsset([], $this->mp4))->withId('mux-asset-001')->withProxy()))->toBeFalse();
    expect($action->visibleTo('not-a-mux-thing'))->toBeFalse();
});

test('run queues a forced upload job for each asset', function () {
    $result = (new ReUploadToMux)->run(collect([MuxAsset::fromAsset($this->mp4)]), []);

    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) {
        $asset = (new ReflectionClass($job))->getProperty('asset')->getValue($job);
        $force = (new ReflectionClass($job))->getProperty('force')->getValue($job);

        return $asset->id() === $this->mp4->id() && $force === true;
    });

    expect($result['callback'])->toBe(['pollMuxMirroredAssetRows', [$this->mp4->id()], 'reupload']);
});
