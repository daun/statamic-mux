<?php

use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;

// Cache eviction lives in the deletion action itself, see DeleteMuxAssetTest.

it('delegates deletion to the common action', function () {
    $action = Mockery::mock(DeleteMuxAsset::class);
    $action->shouldReceive('handle')->with('mux-id')->once()->andReturnTrue();

    (new DeleteMuxAssetJob('mux-id'))->handle($action);
});

it('leaves a failed deletion to the action to report', function () {
    $action = Mockery::mock(DeleteMuxAsset::class);
    $action->shouldReceive('handle')->with('mux-id')->once()->andReturnFalse();

    (new DeleteMuxAssetJob('mux-id'))->handle($action);
});
