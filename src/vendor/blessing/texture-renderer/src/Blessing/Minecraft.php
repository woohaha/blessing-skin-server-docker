<?php

namespace Blessing;

use Blessing\Renderer\CapeRenderer;
use Blessing\Renderer\ImageUtil;
use Blessing\Renderer\SkinRenderer;

class Minecraft
{
    /**
     * @return resource|false
     */
    public function renderSkin($skin, $ratio = 7.0, $isAlex = false)
    {
        $vp = 15;  // vertical padding
        $hp = 30;  // horizontal padding
        $ip = 15;  // internal padding

        $renderer = new SkinRenderer($ratio, false, -45);
        $front = $renderer->render($skin, $isAlex);

        $renderer = new SkinRenderer($ratio, false, 135);
        $back = $renderer->render($skin, $isAlex);

        $width = imagesx($front);
        $height = imagesy($front);

        $canvas = ImageUtil::createEmptyCanvas(($hp + $width + $ip) * 2, $vp * 2 + $height);

        imagecopy($canvas, $back, $hp, $vp, 0, 0, $width, $height);
        imagecopy($canvas, $front, $hp + $width + $ip * 2, $vp, 0, 0, $width, $height);

        imagedestroy($front);
        imagedestroy($back);

        return $canvas;
    }

    /**
     * @return resource|false
     */
    public function renderCape($cape, int $height)
    {
        $vp = 20;  // vertical padding
        $hp = 40;  // horizontal padding

        $renderer = new CapeRenderer();
        $cape = $renderer->render($cape, $height);
        $width = imagesx($cape);
        $height = imagesy($cape);

        $canvas = ImageUtil::createEmptyCanvas($hp * 2 + $width, $vp * 2 + $height);
        imagecopy($canvas, $cape, $hp, $vp, 0, 0, $width, $height);

        imagedestroy($cape);

        return $canvas;
    }

    /**
     * @return resource|false
     */
    public function render2dAvatar($skin, $ratio = 15.0)
    {
        $renderer = new SkinRenderer($ratio, true, 0, 0);
        $avatar = $renderer->render($skin);

        return $avatar;
    }

    /**
     * @return resource|false
     */
    public function render3dAvatar($skin, $ratio = 15.0)
    {
        $renderer = new SkinRenderer($ratio, true, 45);
        $avatar = $renderer->render($skin);

        return $avatar;
    }
}
