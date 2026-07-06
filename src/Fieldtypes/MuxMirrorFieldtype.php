<?php

namespace Daun\StatamicMux\Fieldtypes;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Data\MuxPlaybackId;
use Daun\StatamicMux\GraphQL\MuxMirrorType;
use Daun\StatamicMux\GraphQL\MuxPlaybackIdType;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Query\Scopes\Filters\Fields\MuxMirrorFieldtypeFilter;
use Statamic\Assets\Asset;
use Statamic\Facades\GraphQL;
use Statamic\Fields\Fieldtype;

class MuxMirrorFieldtype extends Fieldtype
{
    protected static $handle = 'mux_mirror';

    protected static $title = 'Mux Mirror';

    protected $categories = ['media', 'special'];

    protected $icon = 'video';

    protected $validatable = false;

    protected function configFieldItems(): array
    {
        return [
            'show_details' => [
                'display' => __('statamic-mux::fieldtypes.mux_mirror.config.show_details.display'),
                'instructions' => __('statamic-mux::fieldtypes.mux_mirror.config.show_details.instructions'),
                'type' => 'toggle',
            ],
            'allow_reupload' => [
                'display' => __('statamic-mux::fieldtypes.mux_mirror.config.allow_reupload.display'),
                'instructions' => __('statamic-mux::fieldtypes.mux_mirror.config.allow_reupload.instructions'),
                'type' => 'toggle',
            ],
        ];
    }

    protected function asset(): ?Asset
    {
        if ($this->field?->parent() instanceof Asset) {
            return $this->field->parent();
        } else {
            return null;
        }
    }

    public function preload()
    {
        $asset = $this->asset();
        $muxAsset = $asset ? MuxAsset::fromAsset($asset) : null;

        return [
            'is_asset' => (bool) $asset,
            'is_video' => $asset?->isVideo() ?? false,
            'is_proxy' => $muxAsset?->isProxy() ?? false,
            'mux' => $muxAsset && $this->config('show_details')
                ? $this->muxInfo($muxAsset)
                : [],
        ];
    }

    protected function muxInfo(MuxAsset $muxAsset): array
    {
        $playbackId = $muxAsset?->playbackId();
        if (! $playbackId instanceof MuxPlaybackId) {
            return [
                'asset_id' => $muxAsset->id(),
            ];
        }

        $service = app(MuxService::class);
        $url = fn (callable $cb) => rescue($cb, null, report: false);

        return array_filter([
            'asset_id' => $muxAsset->id(),
            'playback_id' => $playbackId->id(),
            'signed' => $playbackId->isSigned(),
            'player_url' => $url(fn () => $service->getPlayerUrl($playbackId)),
            'stream_url' => $url(fn () => $service->getPlaybackUrl($playbackId)),
            'thumbnail_url' => $url(fn () => $service->getThumbnailUrl($playbackId)),
            'gif_url' => $url(fn () => $service->getGifUrl($playbackId)),
            'embed_code' => $url(fn () => $service->getEmbedCode($playbackId)),
        ], fn ($v) => $v !== null);
    }

    public function preProcess($data)
    {
        return ['reupload' => false] + ($data ?? []);
    }

    public function process($data)
    {
        $asset = $this->asset();
        $reupload = $data['reupload'] ?? false;
        unset($data['reupload']);

        // (Re)upload asset if checkbox was checked by editor
        if ($reupload && $asset && $asset->isVideo()) {
            CreateMuxAssetJob::dispatchAsync($asset, true);
        }

        return $data;
    }

    public function augment($value)
    {
        if ($asset = $this->asset()) {
            return new MuxAsset($value ?? [], $asset, $this->field()?->handle());
        } else {
            return $value;
        }
    }

    public function toGqlType()
    {
        return GraphQL::type(MuxMirrorType::NAME);
    }

    public function addGqlTypes()
    {
        GraphQL::addType(MuxMirrorType::class);
        GraphQL::addType(MuxPlaybackIdType::class);
    }

    public function filter()
    {
        return new MuxMirrorFieldtypeFilter($this);
    }
}
