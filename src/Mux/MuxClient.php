<?php

namespace Daun\StatamicMux\Mux;

use Daun\StatamicMux\Concerns\ProcessesHooks;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class MuxClient extends Client implements ClientInterface {
    use ProcessesHooks;

    public function __construct() {
        return parent::__construct([
            'handler' => $this->buildHandlerStack()
        ]);
    }

    protected function buildHandlerStack(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            $payload = $this->hooks('api-request', ['request' => $request]);

            return $payload->request ?? $request;
        }));

        return $stack;
    }
}
