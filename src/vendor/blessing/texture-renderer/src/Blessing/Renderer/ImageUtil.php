<?php

namespace Blessing\Renderer;

use Intervention\Image\ImageManager;

class ImageUtil
{
    public static function initGdResource($source)
    {
        $manager = new ImageManager(['driver' => 'gd']);

        return $manager->make($source)->getCore();
    }

    public static function createEmptyCanvas($width, $height)
    {
        $dst = imagecreatetruecolor($width, $height);
        imagesavealpha($dst, true);
        $trans_color = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $trans_color);

        return $dst;
    }

    public static function convertToTrueColor($img)
    {
        if (imageistruecolor($img)) {
            return $img;
        }

        $dst = ImageUtil::createEmptyCanvas(imagesx($img), imagesy($img));

        imagecopy($dst, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);

        return $dst;
    }
}
