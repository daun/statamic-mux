<?php

namespace Daun\StatamicMux\Query\Scopes\Filters\Fields;

use Illuminate\Support\Arr;
use Statamic\Query\Scopes\Filters\Fields\FieldtypeFilter;

class MuxMirrorFieldtypeFilter extends FieldtypeFilter
{
    public function fieldItems()
    {
        return [
            'field' => [
                'type' => 'select',
                'options' => [
                    'status' => __('Status'),
                    'policy' => __('Policy'),
                    'id' => __('ID'),
                ],
                'default' => 'status',
            ],
            'id' => [
                'type' => 'text',
                'placeholder' => __('ID'),
                'required' => false,
                'if' => [
                    'field' => 'id',
                ],
            ],
            'policy' => [
                'type' => 'select',
                'options' => [
                    'public' => __('Public'),
                    'signed' => __('Signed'),
                ],
                'required' => false,
                'if' => [
                    'field' => 'policy',
                ],
            ],
            'status' => [
                'type' => 'select',
                'options' => [
                    'uploaded' => __('Uploaded'),
                    'not_uploaded' => __('Not Uploaded'),
                    'ignored' => __('Ignored'),
                ],
                'required' => false,
                'if' => [
                    'field' => 'status',
                ],
            ],
        ];
    }

    public function apply($query, $handle, $values)
    {
        $field = $values['field'];

        if ($field === 'status') {
            match ($values['status'] ?? null) {
                'uploaded' => $query->where('is_video', true)->whereNotNull("{$handle}->id"),
                'not_uploaded' => $query->where('is_video', true)->whereNull("{$handle}->id"),
                'ignored' => $query->where('is_video', false),
            };
        }

        if ($field === 'policy') {
            match ($values['policy'] ?? null) {
                'public' => $query->whereNotNull("{$handle}->playback_ids->public"),
                'signed' => $query->whereNotNull("{$handle}->playback_ids->signed"),
            };
        }

        if ($field === 'id' && ($values['id'] ?? null)) {
            $query->where(fn ($q) => $q
                ->where("{$handle}->id", 'like', "%{$values['id']}%")
                ->orWhere("{$handle}->playback_ids", 'like', "%{$values['id']}%")
            );
        }
    }

    public function badge($values)
    {
        $base = 'Mux';
        $field = match ($values['field'] ?? null) {
            'status' => __('Status'),
            'policy' => __('Policy'),
            'id' => __('ID'),
        };
        $value = match ($values['field'] ?? null) {
            'status' => Arr::get($this->fieldItems(), "status.options.{$values['status']}"),
            'policy' => Arr::get($this->fieldItems(), "policy.options.{$values['policy']}"),
            'id' => $values['id'] ?? null,
        };

        return $base.' '.$field.' '.__('Is').' '.$value;
    }

    public function isComplete($values): bool
    {
        $values = array_filter($values);

        if (! $field = Arr::get($values, 'field')) {
            return false;
        }

        if ($field === 'status' && Arr::has($values, 'status')) {
            return true;
        }

        if ($field === 'policy' && Arr::has($values, 'policy')) {
            return true;
        }

        if ($field === 'id' && Arr::has($values, 'id')) {
            return true;
        }

        return false;
    }
}
