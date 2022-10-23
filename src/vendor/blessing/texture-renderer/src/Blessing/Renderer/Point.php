<?php

namespace Blessing\Renderer;

class Point
{
    private $originCoord;
    private $destCoord = [];
    private $isProjected = false;
    private $isPreProjected = false;

    public function __construct($originCoord)
    {
        if (is_array($originCoord) && count($originCoord) == 3) {
            $this->originCoord = array(
                'x' => (isset($originCoord['x']) ? $originCoord['x'] : 0),
                'y' => (isset($originCoord['y']) ? $originCoord['y'] : 0),
                'z' => (isset($originCoord['z']) ? $originCoord['z'] : 0)
            );
        } else {
            $this->originCoord = array(
                'x' => 0,
                'y' => 0,
                'z' => 0
            );
        }
    }

    public function project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, &$minX, &$maxX, &$minY, &$maxY)
    {
        // 1, 0, 1, 0
        $x = $this->originCoord['x'];
        $y = $this->originCoord['y'];
        $z = $this->originCoord['z'];
        $this->destCoord['x'] = $x * $cos_omega + $z * $sin_omega;
        $this->destCoord['y'] = $x * $sin_alpha * $sin_omega + $y * $cos_alpha - $z * $sin_alpha * $cos_omega;
        $this->destCoord['z'] = -$x * $cos_alpha * $sin_omega + $y * $sin_alpha + $z * $cos_alpha * $cos_omega;
        $this->isProjected = true;
        $minX = min($minX, $this->destCoord['x']);
        $maxX = max($maxX, $this->destCoord['x']);
        $minY = min($minY, $this->destCoord['y']);
        $maxY = max($maxY, $this->destCoord['y']);
    }

    public function preProject($dx, $dy, $dz, $cos_alpha, $sin_alpha, $cos_omega, $sin_omega)
    {
        if (!$this->isPreProjected) {
            $x                         = $this->originCoord['x'] - $dx;
            $y                         = $this->originCoord['y'] - $dy;
            $z                         = $this->originCoord['z'] - $dz;
            $this->originCoord['x'] = $x * $cos_omega + $z * $sin_omega + $dx;
            $this->originCoord['y'] = $x * $sin_alpha * $sin_omega + $y * $cos_alpha - $z * $sin_alpha * $cos_omega + $dy;
            $this->originCoord['z'] = -$x * $cos_alpha * $sin_omega + $y * $sin_alpha + $z * $cos_alpha * $cos_omega + $dz;
            $this->isPreProjected     = true;
        }
    }

    public function getOriginCoord()
    {
        return $this->originCoord;
    }

    public function getDestCoord()
    {
        return $this->destCoord;
    }

    public function getDepth($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, &$minX, &$maxX, &$minY, &$maxY)
    {
        if (!$this->isProjected) {
            $this->project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $minX, $maxX, $minY, $maxY);
        }
        return $this->destCoord['z'];
    }

    public function isProjected()
    {
        return $this->isProjected;
    }
}
