<?php

use Daun\StatamicMux\Actions\RelinkToAsset;
use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\Reconciler;
use Statamic\Facades\Stache;

beforeEach(function () {
    config(['mux.mirror.enabled' => false]);
    $this->addMirrorFieldToAssetBlueprint();
    $this->video = $this->uploadTestFileToTestContainer('test.mp4');
    Stache::clear();

    $this->video_ = muxRemoteVideo('mux-id');
    $this->remote = $this->video_->asset();
    $this->candidate = muxRemoteRecord($this->video_, ReconciliationState::Unlinked, $this->video);
    $this->local = muxLocalRecord($this->video, ReconciliationState::Unlinked, ['selected' => $this->candidate]);
    $this->plan = muxPlan([$this->local], [$this->candidate]);
});

it('is visible only for an item the server marked relinkable', function () {
    $action = new RelinkToAsset;

    expect($action->visibleTo(new MuxLibraryItem('mux-id', relinkable: true)))->toBeTrue()
        ->and($action->visibleTo(new MuxLibraryItem('mux-id')))->toBeFalse()
        ->and($action->visibleToBulk(collect([new MuxLibraryItem('mux-id', relinkable: true)])))->toBeFalse()
        ->and($action->visibleTo('mux-id'))->toBeFalse();
});

it('resolves the trusted target from a fresh server-side plan', function () {
    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('plan')->once()->andReturn($this->plan);
    app()->instance(Reconciler::class, $reconciler);

    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('relinkMuxAsset')->with($this->video, $this->remote, false)->once()->andReturnTrue();
    app()->instance(MuxService::class, $service);

    $result = (new RelinkToAsset)->run(collect([new MuxLibraryItem('mux-id')]), []);

    expect($result)->toContain($this->video->id());
});

it('refuses a row that is no longer a safe candidate, even when marked relinkable', function () {
    $empty = muxPlan();
    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('plan')->once()->andReturn($empty);
    app()->instance(Reconciler::class, $reconciler);

    expect(fn () => (new RelinkToAsset)->run(collect([new MuxLibraryItem('mux-id', relinkable: true)]), []))
        ->toThrow(RuntimeException::class, 'no longer a safe');
});

it('authorizes mux managers', function () {
    $action = new RelinkToAsset;

    expect($action->authorize(userWithMuxPermission('manage mux'), null))->toBeTrue()
        ->and($action->authorize(userWithMuxPermission(null), null))->toBeFalse();
});
