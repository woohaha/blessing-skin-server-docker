<?php

namespace Blessing\Renderer;

use Intervention\Image\ImageManager;

class TextureUtil
{
    public static function isAlex($imageData): bool
    {
        $manager = new ImageManager(['driver' => 'gd']);
        $image = $manager->make($imageData);
        $width = $image->width();

        if ($width === $image->height()) {
            $ratio = $width / 64;
            for ($x = 46 * $ratio; $x < 48 * $ratio; $x += 1) {
                for ($y = 52 * $ratio; $y < 64 * $ratio; $y += 1) {
                    if (!static::checkPixel($image->pickColor($x, $y))) {
                        return false;
                    }
                }
            }

            return true;
        } else {
            return false;
        }
    }

    protected static function checkPixel(array $color): bool
    {
        return $color[0] === 0 && $color[1] === 0 && $color[2] === 0;
    }
}
