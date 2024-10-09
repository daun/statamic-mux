<?php

use Carbon\Carbon;
use Daun\StatamicMux\Mux\Enums\MuxAudience;
use Daun\StatamicMux\Mux\MuxUrls;

beforeEach(function () {
    $this->urls = $this->app->make(MuxUrls::class);
});

test('converts expiration to timestamp', function () {
    Carbon::setTestNow('2021-01-01 00:00:00');

    expect($this->urls->timestamp())->toBeInt();

    expect($this->urls->timestamp('1 day'))->toBe(Carbon::now()->add('1 day')->timestamp);
    expect($this->urls->timestamp('1 week'))->toBe(Carbon::now()->add('1 week')->timestamp);
    expect($this->urls->timestamp('1 month'))->toBe(Carbon::now()->add('1 month')->timestamp);
    expect($this->urls->timestamp(1))->toBe(Carbon::now()->add('1 second')->timestamp);
    expect($this->urls->timestamp(5))->toBe(Carbon::now()->add('5 seconds')->timestamp);
});
