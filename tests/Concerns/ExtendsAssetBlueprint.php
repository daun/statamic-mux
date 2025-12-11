<?php

namespace Tests\Concerns;

use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;

trait ExtendsAssetBlueprint
{
    protected function addMirrorFieldToAssetBlueprint(string $handle = 'mux', array $params = [], mixed $container = null)
    {
        $this->setAssetContainerBlueprint([
            'alt' => [
                'type' => 'text',
            ],
            $handle => [
                'type' => MuxMirrorFieldtype::handle(),
                ...$params,
            ],
        ], $container);
    }
}
