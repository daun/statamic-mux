<?php

namespace Daun\StatamicMux\Concerns;

use Daun\StatamicMux\Mux\MuxApi;
use Throwable;

trait FailsOnPermanentMuxErrors
{
    /**
     * Fail the job outright when Mux rejected the request in a way no retry can
     * fix, so a bad token or a deleted asset cannot burn the whole retry window.
     * Anything else is rethrown for the queue to retry as usual.
     */
    protected function failOnPermanentMuxError(Throwable $exception): void
    {
        if (MuxApi::isPermanentError($exception)) {
            $this->fail($exception);

            return;
        }

        throw $exception;
    }
}
