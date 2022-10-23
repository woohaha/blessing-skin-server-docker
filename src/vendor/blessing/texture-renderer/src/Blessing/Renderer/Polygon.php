<?php

namespace Blessing\Renderer;

class Polygon
{
    private $dots;
    private $color;
    private $isProjected = false;

    public function __construct($dots, $color)
    {
        $this->dots   = $dots;
        $this->color = $color;
        $coord_0       = $dots[0]->getOriginCoord();
        $coord_1       = $dots[1]->getOriginCoord();
        $coord_2       = $dots[2]->getOriginCoord();
        if ($coord_0['x'] == $coord_1['x'] && $coord_1['x'] == $coord_2['x']) {
            $this->_face      = 'x';
            $this->_faceDepth = $coord_0['x'];
        } elseif ($coord_0['y'] == $coord_1['y'] && $coord_1['y'] == $coord_2['y']) {
            $this->_face      = 'y';
            $this->_faceDepth = $coord_0['y'];
        } elseif ($coord_0['z'] == $coord_1['z'] && $coord_1['z'] == $coord_2['z']) {
            $this->_face      = 'z';
            $this->_faceDepth = $coord_0['z'];
        }
    }

    public function addPngPolygon(&$image, $minX, $minY, $ratio)
    {
        $points_2d = array();
        $nb_points = 0;
        $r         = ($this->color >> 16) & 0xFF;
        $g         = ($this->color >> 8) & 0xFF;
        $b         = $this->color & 0xFF;
        $vR        = (127 - (($this->color & 0x7F000000) >> 24)) / 127;
        if ($vR == 0)
            return;
        $same_plan_x = true;
        $same_plan_y = true;
        foreach ($this->dots as $dot) {
            $coord = $dot->getDestCoord();
            if (!isset($coord_x))
                $coord_x = $coord['x'];
            if (!isset($coord_y))
                $coord_y = $coord['y'];
            if ($coord_x != $coord['x'])
                $same_plan_x = false;
            if ($coord_y != $coord['y'])
                $same_plan_y = false;
            $points_2d[] = ($coord['x'] - $minX) * $ratio;
            $points_2d[] = ($coord['y'] - $minY) * $ratio;
            $nb_points++;
        }
        if (!($same_plan_x || $same_plan_y)) {
            $colour = imagecolorallocate($image, $r, $g, $b);
            imagefilledpolygon($image, $points_2d, $nb_points, $colour);
        }
    }

    public function isProjected()
    {
        return $this->isProjected;
    }

    public function project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, &$minX, &$maxX, &$minY, &$maxY)
    {
        foreach ($this->dots as &$dot) {
            if (!$dot->isProjected()) {
                $dot->project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $minX, $maxX, $minY, $maxY);
            }
        }
        $this->isProjected = true;
    }

    public function preProject($dx, $dy, $dz, $cos_alpha, $sin_alpha, $cos_omega, $sin_omega)
    {
        foreach ($this->dots as &$dot) {
            $dot->preProject($dx, $dy, $dz, $cos_alpha, $sin_alpha, $cos_omega, $sin_omega);
        }
    }
}
