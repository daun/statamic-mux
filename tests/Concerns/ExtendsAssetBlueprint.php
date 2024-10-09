<?php

namespace Tests\Concerns;

use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;

trait ExtendsAssetBlueprint
{
    protected function addMirrorFieldToAssetBlueprint(array $params = [])
    {
        $this->setAssetContainerBlueprint([
            'alt' => [
                'type' => 'text',
            ],
            'mux' => [
                ...$params,
                'type' => MuxMirrorFieldtype::handle(),
            ],
        ]);
    }
}
