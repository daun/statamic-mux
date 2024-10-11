<?php

namespace Daun\StatamicMux\Fieldtypes;

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Statamic\Assets\Asset;
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

        return [
            'is_asset' => (bool) $asset,
            'is_video' => $asset && $asset->isVideo(),
        ];
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
        if ($asset && $reupload) {
            CreateMuxAssetJob::dispatch($asset, true);
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
}
