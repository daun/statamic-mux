<?php

namespace Daun\StatamicMux\GraphQL;

use Daun\StatamicMux\Data\MuxPlaybackId;
use Statamic\Facades\GraphQL;

class MuxPlaybackIdType extends \Rebing\GraphQL\Support\Type
{
    const NAME = 'MuxPlaybackId';

    protected $attributes = [
        'name' => self::NAME,
        'description' => 'Playback id allowing streaming access to a Mux video',
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::string(),
                'resolve' => fn (MuxPlaybackId $item) => $item->id(),
            ],
            'policy' => [
                'type' => GraphQL::string(),
                'resolve' => fn (MuxPlaybackId $item) => $item->policy(),
            ],
        ];
    }
}
