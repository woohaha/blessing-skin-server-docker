<?php

namespace Blessing\Renderer;

class CapeRenderer
{
    public function render($source, int $height)
    {
        $resource = ImageUtil::initGdResource($source);

        $srcWidth = imagesx($resource);
        $srcHeight = imagesy($resource);
        $hdRatio = $srcWidth / 64;
        $outHeight = $height;
        $outWidth = $outHeight / 16 * 10;

        $canvas = ImageUtil::createEmptyCanvas($outWidth, $outHeight);
        imagecopyresampled($canvas, $resource, 0, 0, 1 * $hdRatio, 1 * $hdRatio, $outWidth, $outHeight, $srcWidth * 10 / 64, $srcHeight * 16 / 32);
        imagedestroy($resource);

        return $canvas;
    }
}
