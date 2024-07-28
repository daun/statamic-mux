<?php

namespace Daun\StatamicMux\Support;

class Imagick
{
    public static function installed(): bool
    {
        return class_exists('\\Imagick');
    }
}
