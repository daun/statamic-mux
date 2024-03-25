<?php

namespace Daun\StatamicMux\Features;

class Imagick
{
    public static function installed(): bool
    {
        return class_exists('\\Imagick');
    }
}
