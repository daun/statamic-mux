<?php

namespace Daun\StatamicMux\GraphQL;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Statamic\Facades\GraphQL;
use Statamic\GraphQL\Types\JsonArgument;

class MuxMirrorType extends \Rebing\GraphQL\Support\Type
{
    const NAME = 'MuxMirror';

    protected $attributes = [
        'name' => self::NAME,
        'description' => 'Video uploaded to and encoded by Mux',
    ];

    protected function playbackPolicyArgs(): array
    {
        return [
            'policy' => [
                'type' => GraphQL::string(),
                'description' => 'Playback policy to filter by: "public" or "signed"',
            ],
        ];
    }

    protected function paramArgs(?string $name = 'url'): array
    {
        return [
            'params' => [
                'type' => GraphQL::type(JsonArgument::NAME),
                'description' => "Additional parameters to include when generating the {$name}",
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'mux_id' => [
                'type' => GraphQL::string(),
                'description' => 'Mux asset ID',
                'resolve' => fn (MuxAsset $item) => $item->augmentedValue('id'),
            ],
            'playback_ids' => [
                'type' => GraphQL::listOf(GraphQL::type(MuxPlaybackIdType::NAME)),
                'description' => 'All available Mux playback ids for this asset',
                'resolve' => fn (MuxAsset $item) => $item->playbackIds(),
            ],
            'playback_id' => [
                'type' => GraphQL::type(MuxPlaybackIdType::NAME),
                'description' => 'Mux playback id used for streaming the video. Defaults to the first found `public` playback id, unless filtered by `policy` argument',
                'args' => $this->playbackPolicyArgs(),
                'resolve' => function (MuxAsset $item, array $args) {
                    return $item->playbackId($this->getPolicy($args));
                },
            ],
            'playback_url' => [
                'type' => GraphQL::string(),
                'description' => 'Playback url used for streaming the video',
                'args' => [
                    ...$this->playbackPolicyArgs(),
                    ...$this->paramArgs('playback url'),
                ],
                'resolve' => function (MuxAsset $item, array $args) {
                    return Mux::getPlaybackUrl($item->playbackId($this->getPolicy($args)), params: $args['params'] ?? []);
                },
            ],
            'playback_token' => [
                'type' => GraphQL::string(),
                'description' => 'Signed token used for private video playback',
                'args' => [
                    ...$this->playbackPolicyArgs(),
                    ...$this->paramArgs('signed playback url'),
                ],
                'resolve' => function (MuxAsset $item, array $args) {
                    return Mux::getPlaybackToken($item->playbackId($this->getPolicy($args)), params: $args['params'] ?? []);
                },
            ],
            'thumbnail' => [
                'type' => GraphQL::string(),
                'description' => 'Url of a thumbnail image representing the video',
                'args' => [
                    ...$this->playbackPolicyArgs(),
                    ...$this->paramArgs('thumbnail url'),
                ],
                'resolve' => function (MuxAsset $item, array $args) {
                    return Mux::getThumbnailUrl($item->playbackId($this->getPolicy($args)), params: $args['params'] ?? []);
                },
            ],
            'gif' => [
                'type' => GraphQL::string(),
                'description' => 'Url of an animated gif image representing the video',
                'args' => [
                    ...$this->playbackPolicyArgs(),
                    ...$this->paramArgs('gif url'),
                ],
                'resolve' => function (MuxAsset $item, array $args) {
                    return Mux::getGifUrl($item->playbackId($this->getPolicy($args)), params: $args['params'] ?? []);
                },
            ],
            'placeholder' => [
                'type' => GraphQL::string(),
                'description' => 'Data uri of a blurry image placeholder',
                'args' => [
                    ...$this->playbackPolicyArgs(),
                    ...$this->paramArgs('placeholder url'),
                ],
                'resolve' => function (MuxAsset $item, array $args) {
                    return Mux::getPlaceholderDataUri($item->playbackId($this->getPolicy($args)), params: $args['params'] ?? []);
                },
            ],
            'playback_modifiers' => [
                'type' => GraphQL::type(JsonArgument::NAME),
                'description' => 'Playback modifiers included in playback urls by default',
                'resolve' => fn () => Arr::wrap(config('mux.playback_modifiers', [])),
            ],
        ];
    }

    protected function getPolicy(array $args): ?MuxPlaybackPolicy
    {
        Validator::make($args, ['policy' => ['string', 'nullable', 'in:public,signed']])->validate();
        return MuxPlaybackPolicy::make($args['policy'] ?? null);
    }
}
