<?php

use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\MuxService;
use Psr\Log\LoggerInterface;

test('creates Mux service facade instance', function () {
    expect(Mux::getFacadeRoot())->toBeInstanceOf(MuxService::class);
});

test('creates Logger facade instance', function () {
    expect(Log::getFacadeRoot())->toBeInstanceOf(LoggerInterface::class);
});
