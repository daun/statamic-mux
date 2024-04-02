<?php

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\MuxService;

test('creates correct facade instance', function () {
    expect(Mux::getFacadeRoot())->toBeInstanceOf(MuxService::class);
});
