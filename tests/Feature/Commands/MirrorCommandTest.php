<?php

use Daun\StatamicMux\Commands\MirrorCommand;
use Statamic\Facades\Stache;

beforeEach(function () {
    Stache::clear();
});

it('calls both upload and prune commands', function () {
    // Create a spy for the MirrorCommand to track which commands it calls
    $spy = Mockery::spy(MirrorCommand::class)->makePartial();
    $spy->shouldReceive('info')->andReturn(null);
    $spy->shouldReceive('newLine')->andReturn(null);
    $spy->shouldReceive('option')->andReturn(null);
    $spy->shouldReceive('call')->with('mux:upload', Mockery::any())->once()->andReturn(0);
    $spy->shouldReceive('call')->with('mux:prune', Mockery::any())->once()->andReturn(0);

    // Run the handle method directly
    $spy->handle();
});

it('passes along arguments to commands', function () {
    // Create a spy for the MirrorCommand to track which commands it calls
    $spy = Mockery::spy(MirrorCommand::class)->makePartial();
    $spy->shouldReceive('info')->andReturn(null);
    $spy->shouldReceive('newLine')->andReturn(null);

    // Mock options to return specific values
    $spy->shouldReceive('option')->with('container')->andReturn('videos');
    $spy->shouldReceive('option')->with('force')->andReturn(true);
    $spy->shouldReceive('option')->with('dry-run')->andReturn(true);

    // Verify upload command receives all three options
    $spy->shouldReceive('call')->with('mux:upload', [
        '--container' => 'videos',
        '--force' => true,
        '--dry-run' => true,
    ])->once()->andReturn(0);

    // Verify prune command receives the dry-run option
    $spy->shouldReceive('call')->with('mux:prune', [
        '--dry-run' => true,
    ])->once()->andReturn(0);

    // Run the handle method directly
    $spy->handle();
});
