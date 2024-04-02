<?php

namespace Daun\StatamicMux\Support;

use Illuminate\Support\Arr;

class URL
{
    public static function withQuery(string $url, ?array $params): string
    {
        $query = Arr::query($params ?? []);

        return $query ? "{$url}?{$query}" : $url;
    }
}
