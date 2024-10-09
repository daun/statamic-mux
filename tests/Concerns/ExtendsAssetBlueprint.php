<?php

namespace Tests\Concerns;

use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;

trait ExtendsAssetBlueprint
{
    protected function addMirrorFieldToAssetBlueprint(array $params = [], mixed $container = null)
    {
        $this->setAssetContainerBlueprint([
            'alt' => [
                'type' => 'text',
            ],
            'mux' => [
                'type' => MuxMirrorFieldtype::handle(),
                ...$params,
            ],
        ], $container);
    }
}
