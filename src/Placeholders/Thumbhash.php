<?php

namespace Daun\StatamicMux\Placeholders;

use Daun\StatamicMux\Support\Imagick;
use Thumbhash\Thumbhash as ThumbhashLib;

class Thumbhash
{
    protected static int $maxThumbSize = 100;

    public static function encode(string $contents): ?string
    {
        try {
            [$width, $height, $pixels] = static::generatePixelMatrix($contents);
            $hash = ThumbhashLib::RGBAToHash($width, $height, $pixels);

            return ThumbhashLib::convertHashToString($hash);
        } catch (\Exception $e) {
            throw new \Exception("Error encoding thumbhash: {$e->getMessage()}");
        }
    }

    public static function decode(string $hash): ?string
    {
        if (! $hash) {
            return null;
        }

        try {
            $hash = ThumbhashLib::convertStringToHash($hash);

            return ThumbhashLib::toDataURL($hash);
        } catch (\Exception $e) {
            throw new \Exception("Error decoding thumbhash: {$e->getMessage()}");
        }
    }

    protected static function generatePixelMatrix(?string $contents): array
    {
        if (! $contents) {
            return [];
        }

        if (Imagick::installed()) {
            return static::generatePixelMatrixUsingImagick($contents);
        } else {
            return static::generatePixelMatrixUsingGD($contents);
        }
    }

    protected static function generatePixelMatrixUsingGD(string $contents): array
    {
        $image = @imagecreatefromstring($contents);
        [$width, $height] = static::contain(imagesx($image), imagesy($image), static::$maxThumbSize);
        $image = imagescale($image, $width, $height);

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color_index = imagecolorat($image, $x, $y);
                $color = imagecolorsforindex($image, $color_index);
                $alpha = 255 - ceil($color['alpha'] * (255 / 127)); // GD only supports 7-bit alpha channel
                $pixels[] = $color['red'];
                $pixels[] = $color['green'];
                $pixels[] = $color['blue'];
                $pixels[] = $alpha;
            }
        }

        return [$width, $height, $pixels];
    }

    protected static function generatePixelMatrixUsingImagick(string $contents): array
    {
        $image = new \Imagick();
        $image->readImageBlob($contents);
        [$width, $height] = static::contain($image->getImageWidth(), $image->getImageHeight(), static::$maxThumbSize);
        $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);

        $pixels = [];
        foreach ($image->getPixelIterator() as $row) {
            foreach ($row as $pixel) {
                $colors = $pixel->getColor(2);
                $pixels[] = $colors['r'];
                $pixels[] = $colors['g'];
                $pixels[] = $colors['b'];
                $pixels[] = $colors['a'];
            }
        }

        $image->destroy();

        return [$width, $height, $pixels];
    }

    public static function contain(int $width, int $height, int $max): array
    {
        $ratio = $width / $height;
        if ($width >= $height) {
            $width = $max;
            $height = floor($max / $ratio);
        } else {
            $width = floor($max * $ratio);
            $height = $max;
        }

        return [$width, $height];
    }
}
