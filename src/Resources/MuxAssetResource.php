<?php

namespace Daun\StatamicMux\Resources;

use Daun\StatamicMux\Facades\Mux;
use Statamic\Http\Resources\API\AssetResource as APIAssetResource;

class MuxAssetResource extends APIAssetResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);
        $data['mux_id'] = Mux::getMuxId($this->resource);

        return $data;
    }
}
