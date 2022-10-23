<?php

namespace Blessing\Renderer;

class SkinRenderer
{
    private $playerSkin = false;
    private $isNewSkinType = false;
    private $isAlex = false;

    private $hd_ratio = 1;

    private $vR = null;
    private $hR = null;
    private $hrh = null;
    private $vrll = null;
    private $vrrl = null;
    private $vrla = null;
    private $vrra = null;
    private $head_only = null;
    private $ratio = null;
    private $layers = null;

    // Rotation variables in radians (3D Rendering)
    private $alpha = null; // Vertical rotation on the X axis.
    private $omega = null; // Horizontal rotation on the Y axis.
    private $cos_alpha;
    private $sin_alpha;
    private $cos_omega;
    private $sin_omega;

    private $minX;
    private $maxX;
    private $minY;
    private $maxY;

    private $members_angles = array(); // Head, Helmet, Torso, Arms, Legs

    private $visible_faces_format = null;
    private $visible_faces = null;
    private $all_faces = null;

    private $front_faces = null;
    private $back_faces = null;

    private $cube_points = null;

    private $polygons = null;

    public function __construct(
        $ratio = 7,
        $headOnly = false,
        $horizontalRotation = 145,
        $verticalRotation = -25,
        $horizontalRotationOfHead = 0,
        $verticalRotationOfLeftLeg = 0,
        $verticalRotationOfRightLeg = 0,
        $verticalRotationOfLeftArm = 0,
        $verticalRotationOfRightArm = 0,
        $layers = true
    ) {
        $this->ratio = $ratio;
        $this->head_only = $headOnly;
        $this->hR = $horizontalRotation;
        $this->vR = $verticalRotation;
        $this->hrh = $horizontalRotationOfHead;
        $this->vrll = $verticalRotationOfLeftLeg;
        $this->vrrl = $verticalRotationOfRightLeg;
        $this->vrla = $verticalRotationOfLeftArm;
        $this->vrra = $verticalRotationOfRightArm;
        $this->layers = $layers;
    }

    public function render($source, $isAlex = false)
    {
        $this->playerSkin = ImageUtil::initGdResource($source);

        $sourceWidth = imagesx($this->playerSkin);
        $sourceHeight = imagesy($this->playerSkin);

        // prevent memory overflow
        if ($sourceWidth > 256) {
            $newHeight = $sourceHeight / ($sourceWidth / 256);
            $dest = ImageUtil::createEmptyCanvas(256, $newHeight);
            imagecopyresampled($dest, $this->playerSkin, 0, 0, 0, 0, 256, $newHeight, $sourceWidth, $sourceHeight);
            $this->playerSkin = $dest;
        }

        $this->isAlex = $isAlex;
        $this->hd_ratio = imagesx($this->playerSkin) / 64; // Set HD ratio to 2 if the skin is 128x64. Check via width, not height because of new skin type.

        // check if new skin type. If both sides are equaly long: new skin type
        if (imagesx($this->playerSkin) === imagesy($this->playerSkin)) {
            $this->isNewSkinType = true;
        }

        $this->playerSkin = ImageUtil::convertToTrueColor($this->playerSkin); // Convert the image to true color if not a true color image
        $this->makeBackgroundTransparent(); // make background transparent (fix for weird rendering skins)

        // Quick fix for 1.8:
        // Copy the extra layers ontop of the base layers
        if ($this->layers) {
            $this->fixNewSkinTypeLayers();
        }

        $this->calculateAngles();
        $this->facesDetermination();
        $this->generatePolygons();
        $this->memberRotation();
        $this->createProjectionPlan();
        $image = $this->displayImage();

        imagedestroy($this->playerSkin);

        return $image;
    }

    private function makeBackgroundTransparent()
    {
        // check if the corner box is one solid color
        $tempValue = null;
        $needRemove = true;

        for ($iH = 0; $iH < 8; $iH++) {
            for ($iV = 0; $iV < 8; $iV++) {
                $pixelColor = imagecolorat($this->playerSkin, $iH, $iV);

                $indexColor = imagecolorsforindex($this->playerSkin, $pixelColor);
                if ($indexColor['alpha'] > 120) {
                    // the image contains transparancy, noting to do
                    $needRemove = false;
                }

                if ($tempValue === null) {
                    $tempValue = $pixelColor;
                } else if ($tempValue != $pixelColor) {
                    // Cannot determine a background color, file is probably fine
                    $needRemove = false;
                }
            }
        }

        $imgX = imagesx($this->playerSkin);
        $imgY = imagesy($this->playerSkin);

        $dst = ImageUtil::createEmptyCanvas($imgX, $imgY);

        imagesavealpha($this->playerSkin, false);

        if ($needRemove) {
            // the entire block is one solid color. Use this color to clear the background.
            $r = ($tempValue >> 16) & 0xFF;
            $g = ($tempValue >> 8) & 0xFF;
            $b = $tempValue & 0xFF;


            //imagealphablending($dst, true);
            $transparant = imagecolorallocate($this->playerSkin, $r, $g, $b);
            imagecolortransparent($this->playerSkin, $transparant);

            // create fill
            $color = imagecolorallocate($dst, $r, $g, $b);
        } else {
            // create fill
            $color = imagecolorallocate($dst, 0, 0, 0);
        }

        // fill the areas that should not be transparant
        $positionMultiply = $imgX / 64;

        // head
        imagefilledrectangle($dst, 8 * $positionMultiply, 0 * $positionMultiply, 23 * $positionMultiply, 7 * $positionMultiply, $color);
        imagefilledrectangle($dst, 0 * $positionMultiply, 8 * $positionMultiply, 31 * $positionMultiply, 15 * $positionMultiply, $color);

        // right leg, body, right arm
        imagefilledrectangle($dst, 4 * $positionMultiply, 16 * $positionMultiply, 11 * $positionMultiply, 19 * $positionMultiply, $color);
        imagefilledrectangle($dst, 20 * $positionMultiply, 16 * $positionMultiply, 35 * $positionMultiply, 19 * $positionMultiply, $color);
        imagefilledrectangle($dst, 44 * $positionMultiply, 16 * $positionMultiply, 51 * $positionMultiply, 19 * $positionMultiply, $color);
        imagefilledrectangle($dst, 0 * $positionMultiply, 20 * $positionMultiply, 54 * $positionMultiply, 31 * $positionMultiply, $color);

        // left leg, left arm
        imagefilledrectangle($dst, 20 * $positionMultiply, 48 * $positionMultiply, 27 * $positionMultiply, 51 * $positionMultiply, $color);
        imagefilledrectangle($dst, 36 * $positionMultiply, 48 * $positionMultiply, 43 * $positionMultiply, 51 * $positionMultiply, $color);
        imagefilledrectangle($dst, 16 * $positionMultiply, 52 * $positionMultiply, 47 * $positionMultiply, 63 * $positionMultiply, $color);

        imagecopy($dst, $this->playerSkin, 0, 0, 0, 0, $imgX, $imgY);

        $this->playerSkin = $dst;
        return;
    }

    /* Function copys the extra layers of a 1.8 skin
        * onto the base layers so that it will still show. QUICK FIX, NEEDS BETTER FIX
        *
        * Espects an image.
        * Returns a croped image.
        */
    private function fixNewSkinTypeLayers()
    {
        if (!$this->isNewSkinType) {
            return;
        }

        imagecopy($this->playerSkin, $this->playerSkin, 0, 16, 0, 32, 56, 16); // RL2, BODY2, RA2
        imagecopy($this->playerSkin, $this->playerSkin, 16, 48, 0, 48, 16, 16); // LL2
        imagecopy($this->playerSkin, $this->playerSkin, 32, 48, 48, 48, 16, 16); // LA2
    }

    private function calculateAngles()
    {
        // Rotation variables in radians (3D Rendering)
        $this->alpha = deg2rad($this->vR); // Vertical rotation on the X axis.
        $this->omega = deg2rad($this->hR); // Horizontal rotation on the Y axis.

        // Cosine and Sine values
        $this->cos_alpha = cos($this->alpha);
        $this->sin_alpha = sin($this->alpha);
        $this->cos_omega = cos($this->omega);
        $this->sin_omega = sin($this->omega);

        $this->members_angles['torso'] = array(
            'cos_alpha' => cos(0),
            'sin_alpha' => sin(0),
            'cos_omega' => cos(0),
            'sin_omega' => sin(0)
        );

        $alpha_head = 0;
        $omega_head = deg2rad($this->hrh);
        $this->members_angles['head'] = $this->members_angles['helmet'] = array( // Head and helmet get the same calculations
            'cos_alpha' => cos($alpha_head),
            'sin_alpha' => sin($alpha_head),
            'cos_omega' => cos($omega_head),
            'sin_omega' => sin($omega_head)
        );

        $alpha_right_arm = deg2rad($this->vrra);
        $omega_right_arm = 0;
        $this->members_angles['rightArm'] = array(
            'cos_alpha' => cos($alpha_right_arm),
            'sin_alpha' => sin($alpha_right_arm),
            'cos_omega' => cos($omega_right_arm),
            'sin_omega' => sin($omega_right_arm)
        );

        $alpha_left_arm = deg2rad($this->vrla);
        $omega_left_arm = 0;
        $this->members_angles['leftArm'] = array(
            'cos_alpha' => cos($alpha_left_arm),
            'sin_alpha' => sin($alpha_left_arm),
            'cos_omega' => cos($omega_left_arm),
            'sin_omega' => sin($omega_left_arm)
        );

        $alpha_right_leg = deg2rad($this->vrrl);
        $omega_right_leg = 0;
        $this->members_angles['rightLeg'] = array(
            'cos_alpha' => cos($alpha_right_leg),
            'sin_alpha' => sin($alpha_right_leg),
            'cos_omega' => cos($omega_right_leg),
            'sin_omega' => sin($omega_right_leg)
        );

        $alpha_left_leg = deg2rad($this->vrll);
        $omega_left_leg = 0;
        $this->members_angles['leftLeg'] = array(
            'cos_alpha' => cos($alpha_left_leg),
            'sin_alpha' => sin($alpha_left_leg),
            'cos_omega' => cos($omega_left_leg),
            'sin_omega' => sin($omega_left_leg)
        );
        $this->minX = 0;
        $this->maxX = 0;
        $this->minY = 0;
        $this->maxY = 0;
    }

    private function facesDetermination()
    {
        $cos_alpha = $this->cos_alpha;
        $sin_alpha = $this->sin_alpha;
        $cos_omega = $this->cos_omega;
        $sin_omega = $this->sin_omega;

        $this->visible_faces_format = array(
            'front' => array(),
            'back' => array()
        );

        $this->visible_faces = array(
            'head' => $this->visible_faces_format,
            'torso' => $this->visible_faces_format,
            'rightArm' => $this->visible_faces_format,
            'leftArm' => $this->visible_faces_format,
            'rightLeg' => $this->visible_faces_format,
            'leftLeg' => $this->visible_faces_format
        );

        $this->all_faces = array(
            'back',
            'right',
            'top',
            'front',
            'left',
            'bottom'
        );

        // Loop each preProject and Project then calculate the visible faces for each - also display
        foreach ($this->visible_faces as $k => &$v) {
            unset($cube_max_depth_faces, $this->cube_points);

            $this->setCubePoints();

            foreach ($this->cube_points as $cube_point) {
                $cube_point[0]->preProject(
                    0,
                    0,
                    0,
                    $this->members_angles[$k]['cos_alpha'],
                    $this->members_angles[$k]['sin_alpha'],
                    $this->members_angles[$k]['cos_omega'],
                    $this->members_angles[$k]['sin_omega']
                );
                $cube_point[0]->project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY);

                if (!isset($cube_max_depth_faces)) {
                    $cube_max_depth_faces = $cube_point;
                } elseif (
                    $cube_max_depth_faces[0]->getDepth($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY) >
                    $cube_point[0]->getDepth($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY)
                ) {
                    $cube_max_depth_faces = $cube_point;
                }
            }

            $v['back'] = $cube_max_depth_faces[1];
            $v['front'] = array_diff($this->all_faces, $v['back']);
        }

        $this->setCubePoints();

        unset($cube_max_depth_faces);
        foreach ($this->cube_points as $cube_point) {
            $cube_point[0]->project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY);

            if (!isset($cube_max_depth_faces)) {
                $cube_max_depth_faces = $cube_point;
            } elseif (
                $cube_max_depth_faces[0]->getDepth($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY) >
                $cube_point[0]->getDepth($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY)
            ) {
                $cube_max_depth_faces = $cube_point;
            }

            $this->back_faces = $cube_max_depth_faces[1];
            $this->front_faces = array_diff($this->all_faces, $this->back_faces);
        }
    }

    private function setCubePoints()
    {
        $this->cube_points = [];
        $this->cube_points[] = array(
            new Point(array(
                'x' => 0,
                'y' => 0,
                'z' => 0
            )), array(
                'back',
                'right',
                'top'
            )
        ); // 0

        $this->cube_points[] = array(
            new Point(array(
                'x' => 0,
                'y' => 0,
                'z' => 1
            )), array(
                'front',
                'right',
                'top'
            )
        ); // 1

        $this->cube_points[] = array(
            new Point(array(
                'x' => 0,
                'y' => 1,
                'z' => 0
            )), array(
                'back',
                'right',
                'bottom'
            )
        ); // 2

        $this->cube_points[] = array(
            new Point(array(
                'x' => 0,
                'y' => 1,
                'z' => 1
            )), array(
                'front',
                'right',
                'bottom'
            )
        ); // 3

        $this->cube_points[] = array(
            new Point(array(
                'x' => 1,
                'y' => 0,
                'z' => 0
            )), array(
                'back',
                'left',
                'top'
            )
        ); // 4

        $this->cube_points[] = array(
            new Point(array(
                'x' => 1,
                'y' => 0,
                'z' => 1
            )), array(
                'front',
                'left',
                'top'
            )
        ); // 5

        $this->cube_points[] = array(
            new Point(array(
                'x' => 1,
                'y' => 1,
                'z' => 0
            )), array(
                'back',
                'left',
                'bottom'
            )
        ); // 6

        $this->cube_points[] = array(
            new Point(array(
                'x' => 1,
                'y' => 1,
                'z' => 1
            )), array(
                'front',
                'left',
                'bottom'
            )
        ); // 7
    }

    private function generatePolygons()
    {
        $this->polygons = array();
        $cube_faces_array = array(
            'front' => array(),
            'back' => array(),
            'top' => array(),
            'bottom' => array(),
            'right' => array(),
            'left' => array()
        );

        $this->polygons = array(
            'helmet' => $cube_faces_array,
            'head' => $cube_faces_array,
            'torso' => $cube_faces_array,
            'rightArm' => $cube_faces_array,
            'leftArm' => $cube_faces_array,
            'rightLeg' => $cube_faces_array,
            'leftLeg' => $cube_faces_array
        );

        $is_alex = $this->isAlex;
        $hd_ratio = $this->hd_ratio;
        $img_png = $this->playerSkin;

        // HEAD
        $volume_points = array();
        for ($i = 0; $i < 9 * $hd_ratio; $i++) {
            for ($j = 0; $j < 9 * $hd_ratio; $j++) {
                if (!isset($volume_points[$i][$j][-2 * $hd_ratio])) {
                    $volume_points[$i][$j][-2 * $hd_ratio] = new Point(array(
                        'x' => $i,
                        'y' => $j,
                        'z' => -2 * $hd_ratio
                    ));
                }
                if (!isset($volume_points[$i][$j][6 * $hd_ratio])) {
                    $volume_points[$i][$j][6 * $hd_ratio] = new Point(array(
                        'x' => $i,
                        'y' => $j,
                        'z' => 6 * $hd_ratio
                    ));
                }
            }
        }
        for ($j = 0; $j < 9 * $hd_ratio; $j++) {
            for ($k = -2 * $hd_ratio; $k < 7 * $hd_ratio; $k++) {
                if (!isset($volume_points[0][$j][$k])) {
                    $volume_points[0][$j][$k] = new Point(array(
                        'x' => 0,
                        'y' => $j,
                        'z' => $k
                    ));
                }
                if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                    $volume_points[8 * $hd_ratio][$j][$k] = new Point(array(
                        'x' => 8 * $hd_ratio,
                        'y' => $j,
                        'z' => $k
                    ));
                }
            }
        }
        for ($i = 0; $i < 9 * $hd_ratio; $i++) {
            for ($k = -2 * $hd_ratio; $k < 7 * $hd_ratio; $k++) {
                if (!isset($volume_points[$i][0][$k])) {
                    $volume_points[$i][0][$k] = new Point(array(
                        'x' => $i,
                        'y' => 0,
                        'z' => $k
                    ));
                }
                if (!isset($volume_points[$i][8 * $hd_ratio][$k])) {
                    $volume_points[$i][8 * $hd_ratio][$k] = new Point(array(
                        'x' => $i,
                        'y' => 8 * $hd_ratio,
                        'z' => $k
                    ));
                }
            }
        }
        for ($i = 0; $i < 8 * $hd_ratio; $i++) {
            for ($j = 0; $j < 8 * $hd_ratio; $j++) {
                $this->polygons['head']['back'][]  = new Polygon(array(
                    $volume_points[$i][$j][-2 * $hd_ratio],
                    $volume_points[$i + 1][$j][-2 * $hd_ratio],
                    $volume_points[$i + 1][$j + 1][-2 * $hd_ratio],
                    $volume_points[$i][$j + 1][-2 * $hd_ratio]
                ), imagecolorat($img_png, (32 * $hd_ratio - 1) - $i, 8 * $hd_ratio + $j));
                $this->polygons['head']['front'][] = new Polygon(array(
                    $volume_points[$i][$j][6 * $hd_ratio],
                    $volume_points[$i + 1][$j][6 * $hd_ratio],
                    $volume_points[$i + 1][$j + 1][6 * $hd_ratio],
                    $volume_points[$i][$j + 1][6 * $hd_ratio]
                ), imagecolorat($img_png, 8 * $hd_ratio + $i, 8 * $hd_ratio + $j));
            }
        }
        for ($j = 0; $j < 8 * $hd_ratio; $j++) {
            for ($k = -2 * $hd_ratio; $k < 6 * $hd_ratio; $k++) {
                $this->polygons['head']['right'][] = new Polygon(array(
                    $volume_points[0][$j][$k],
                    $volume_points[0][$j][$k + 1],
                    $volume_points[0][$j + 1][$k + 1],
                    $volume_points[0][$j + 1][$k]
                ), imagecolorat($img_png, $k + 2 * $hd_ratio, 8 * $hd_ratio + $j));
                $this->polygons['head']['left'][]  = new Polygon(array(
                    $volume_points[8 * $hd_ratio][$j][$k],
                    $volume_points[8 * $hd_ratio][$j][$k + 1],
                    $volume_points[8 * $hd_ratio][$j + 1][$k + 1],
                    $volume_points[8 * $hd_ratio][$j + 1][$k]
                ), imagecolorat($img_png, (24 * $hd_ratio - 1) - $k - 2 * $hd_ratio, 8 * $hd_ratio + $j));
            }
        }
        for ($i = 0; $i < 8 * $hd_ratio; $i++) {
            for ($k = -2 * $hd_ratio; $k < 6 * $hd_ratio; $k++) {
                $this->polygons['head']['top'][]    = new Polygon(array(
                    $volume_points[$i][0][$k],
                    $volume_points[$i + 1][0][$k],
                    $volume_points[$i + 1][0][$k + 1],
                    $volume_points[$i][0][$k + 1]
                ), imagecolorat($img_png, 8 * $hd_ratio + $i, $k + 2 * $hd_ratio));
                $this->polygons['head']['bottom'][] = new Polygon(array(
                    $volume_points[$i][8 * $hd_ratio][$k],
                    $volume_points[$i + 1][8 * $hd_ratio][$k],
                    $volume_points[$i + 1][8 * $hd_ratio][$k + 1],
                    $volume_points[$i][8 * $hd_ratio][$k + 1]
                ), imagecolorat($img_png, 16 * $hd_ratio + $i, 2 * $hd_ratio + $k));
            }
        }
        // HELMET/HAIR
        $volume_points = array();
        for ($i = 0; $i < 9 * $hd_ratio; $i++) {
            for ($j = 0; $j < 9 * $hd_ratio; $j++) {
                if (!isset($volume_points[$i][$j][-2 * $hd_ratio])) {
                    $volume_points[$i][$j][-2 * $hd_ratio] = new Point(array(
                        'x' => $i * 9 / 8 - 0.5 * $hd_ratio,
                        'y' => $j * 9 / 8 - 0.5 * $hd_ratio,
                        'z' => -2.5 * $hd_ratio
                    ));
                }
                if (!isset($volume_points[$i][$j][6 * $hd_ratio])) {
                    $volume_points[$i][$j][6 * $hd_ratio] = new Point(array(
                        'x' => $i * 9 / 8 - 0.5 * $hd_ratio,
                        'y' => $j * 9 / 8 - 0.5 * $hd_ratio,
                        'z' => 6.5 * $hd_ratio
                    ));
                }
            }
        }
        for ($j = 0; $j < 9 * $hd_ratio; $j++) {
            for ($k = -2 * $hd_ratio; $k < 7 * $hd_ratio; $k++) {
                if (!isset($volume_points[0][$j][$k])) {
                    $volume_points[0][$j][$k] = new Point(array(
                        'x' => -0.5 * $hd_ratio,
                        'y' => $j * 9 / 8 - 0.5 * $hd_ratio,
                        'z' => $k * 9 / 8 - 0.5 * $hd_ratio
                    ));
                }
                if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                    $volume_points[8 * $hd_ratio][$j][$k] = new Point(array(
                        'x' => 8.5 * $hd_ratio,
                        'y' => $j * 9 / 8 - 0.5 * $hd_ratio,
                        'z' => $k * 9 / 8 - 0.5 * $hd_ratio
                    ));
                }
            }
        }
        for ($i = 0; $i < 9 * $hd_ratio; $i++) {
            for ($k = -2 * $hd_ratio; $k < 7 * $hd_ratio; $k++) {
                if (!isset($volume_points[$i][0][$k])) {
                    $volume_points[$i][0][$k] = new Point(array(
                        'x' => $i * 9 / 8 - 0.5 * $hd_ratio,
                        'y' => -0.5 * $hd_ratio,
                        'z' => $k * 9 / 8 - 0.5 * $hd_ratio
                    ));
                }
                if (!isset($volume_points[$i][8 * $hd_ratio][$k])) {
                    $volume_points[$i][8 * $hd_ratio][$k] = new Point(array(
                        'x' => $i * 9 / 8 - 0.5 * $hd_ratio,
                        'y' => 8.5 * $hd_ratio,
                        'z' => $k * 9 / 8 - 0.5 * $hd_ratio
                    ));
                }
            }
        }
        for ($i = 0; $i < 8 * $hd_ratio; $i++) {
            for ($j = 0; $j < 8 * $hd_ratio; $j++) {
                $this->polygons['helmet']['back'][]  = new Polygon(array(
                    $volume_points[$i][$j][-2 * $hd_ratio],
                    $volume_points[$i + 1][$j][-2 * $hd_ratio],
                    $volume_points[$i + 1][$j + 1][-2 * $hd_ratio],
                    $volume_points[$i][$j + 1][-2 * $hd_ratio]
                ), imagecolorat($img_png, 32 * $hd_ratio + (32 * $hd_ratio - 1) - $i, 8 * $hd_ratio + $j));
                $this->polygons['helmet']['front'][] = new Polygon(array(
                    $volume_points[$i][$j][6 * $hd_ratio],
                    $volume_points[$i + 1][$j][6 * $hd_ratio],
                    $volume_points[$i + 1][$j + 1][6 * $hd_ratio],
                    $volume_points[$i][$j + 1][6 * $hd_ratio]
                ), imagecolorat($img_png, 32 * $hd_ratio + 8 * $hd_ratio + $i, 8 * $hd_ratio + $j));
            }
        }
        for ($j = 0; $j < 8 * $hd_ratio; $j++) {
            for ($k = -2 * $hd_ratio; $k < 6 * $hd_ratio; $k++) {
                $this->polygons['helmet']['right'][] = new Polygon(array(
                    $volume_points[0][$j][$k],
                    $volume_points[0][$j][$k + 1],
                    $volume_points[0][$j + 1][$k + 1],
                    $volume_points[0][$j + 1][$k]
                ), imagecolorat($img_png, 32 * $hd_ratio + $k + 2 * $hd_ratio, 8 * $hd_ratio + $j));
                $this->polygons['helmet']['left'][]  = new Polygon(array(
                    $volume_points[8 * $hd_ratio][$j][$k],
                    $volume_points[8 * $hd_ratio][$j][$k + 1],
                    $volume_points[8 * $hd_ratio][$j + 1][$k + 1],
                    $volume_points[8 * $hd_ratio][$j + 1][$k]
                ), imagecolorat($img_png, 32 * $hd_ratio + (24 * $hd_ratio - 1) - $k - 2 * $hd_ratio, 8 * $hd_ratio + $j));
            }
        }
        for ($i = 0; $i < 8 * $hd_ratio; $i++) {
            for ($k = -2 * $hd_ratio; $k < 6 * $hd_ratio; $k++) {
                $this->polygons['helmet']['top'][]    = new Polygon(array(
                    $volume_points[$i][0][$k],
                    $volume_points[$i + 1][0][$k],
                    $volume_points[$i + 1][0][$k + 1],
                    $volume_points[$i][0][$k + 1]
                ), imagecolorat($img_png, 32 * $hd_ratio + 8 * $hd_ratio + $i, $k + 2 * $hd_ratio));
                $this->polygons['helmet']['bottom'][] = new Polygon(array(
                    $volume_points[$i][8 * $hd_ratio][$k],
                    $volume_points[$i + 1][8 * $hd_ratio][$k],
                    $volume_points[$i + 1][8 * $hd_ratio][$k + 1],
                    $volume_points[$i][8 * $hd_ratio][$k + 1]
                ), imagecolorat($img_png, 32 * $hd_ratio + 16 * $hd_ratio + $i, 2 * $hd_ratio + $k));
            }
        }
        if (!$this->head_only) {
            // TORSO
            $volume_points = array();
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                    if (!isset($volume_points[$i][$j][0])) {
                        $volume_points[$i][$j][0] = new Point(array(
                            'x' => $i,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 0
                        ));
                    }
                    if (!isset($volume_points[$i][$j][4 * $hd_ratio])) {
                        $volume_points[$i][$j][4 * $hd_ratio] = new Point(array(
                            'x' => $i,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 4 * $hd_ratio
                        ));
                    }
                }
            }
            for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[0][$j][$k])) {
                        $volume_points[0][$j][$k] = new Point(array(
                            'x' => 0,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                        $volume_points[8 * $hd_ratio][$j][$k] = new Point(array(
                            'x' => 8 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[$i][0][$k])) {
                        $volume_points[$i][0][$k] = new Point(array(
                            'x' => $i,
                            'y' => 0 + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[$i][12 * $hd_ratio][$k])) {
                        $volume_points[$i][12 * $hd_ratio][$k] = new Point(array(
                            'x' => $i,
                            'y' => 12 * $hd_ratio + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 8 * $hd_ratio; $i++) {
                for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                    $this->polygons['torso']['back'][]  = new Polygon(array(
                        $volume_points[$i][$j][0],
                        $volume_points[$i + 1][$j][0],
                        $volume_points[$i + 1][$j + 1][0],
                        $volume_points[$i][$j + 1][0]
                    ), imagecolorat($img_png, (40 * $hd_ratio - 1) - $i, 20 * $hd_ratio + $j));
                    $this->polygons['torso']['front'][] = new Polygon(array(
                        $volume_points[$i][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                        $volume_points[$i][$j + 1][4 * $hd_ratio]
                    ), imagecolorat($img_png, 20 * $hd_ratio + $i, 20 * $hd_ratio + $j));
                }
            }
            for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    $this->polygons['torso']['right'][] = new Polygon(array(
                        $volume_points[0][$j][$k],
                        $volume_points[0][$j][$k + 1],
                        $volume_points[0][$j + 1][$k + 1],
                        $volume_points[0][$j + 1][$k]
                    ), imagecolorat($img_png, 16 * $hd_ratio + $k, 20 * $hd_ratio + $j));
                    $this->polygons['torso']['left'][]  = new Polygon(array(
                        $volume_points[8 * $hd_ratio][$j][$k],
                        $volume_points[8 * $hd_ratio][$j][$k + 1],
                        $volume_points[8 * $hd_ratio][$j + 1][$k + 1],
                        $volume_points[8 * $hd_ratio][$j + 1][$k]
                    ), imagecolorat($img_png, (32 * $hd_ratio - 1) - $k, 20 * $hd_ratio + $j));
                }
            }
            for ($i = 0; $i < 8 * $hd_ratio; $i++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    $this->polygons['torso']['top'][]    = new Polygon(array(
                        $volume_points[$i][0][$k],
                        $volume_points[$i + 1][0][$k],
                        $volume_points[$i + 1][0][$k + 1],
                        $volume_points[$i][0][$k + 1]
                    ), imagecolorat($img_png, 20 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                    $this->polygons['torso']['bottom'][] = new Polygon(array(
                        $volume_points[$i][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                        $volume_points[$i][12 * $hd_ratio][$k + 1]
                    ), imagecolorat($img_png, 28 * $hd_ratio + $i, (20 * $hd_ratio - 1) - $k));
                }
            }
            // RIGHT ARM
            $volume_points = array();
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                    if (!isset($volume_points[$i][$j][0])) {
                        $volume_points[$i][$j][0] = new Point(array(
                            'x' => $i - 4 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 0
                        ));
                    }
                    if (!isset($volume_points[$i][$j][4 * $hd_ratio])) {
                        $volume_points[$i][$j][4 * $hd_ratio] = new Point(array(
                            'x' => $i - 4 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 4 * $hd_ratio
                        ));
                    }
                }
            }
            for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[0][$j][$k])) {
                        $volume_points[0][$j][$k] = new Point(array(
                            'x' => 0 - 4 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                        $volume_points[4 * $hd_ratio][$j][$k] = new Point(array(
                            'x' => 4 * $hd_ratio - 4 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[$i][0][$k])) {
                        $volume_points[$i][0][$k] = new Point(array(
                            'x' => $i - 4 * $hd_ratio,
                            'y' => 0 + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[$i][12 * $hd_ratio][$k])) {
                        $volume_points[$i][12 * $hd_ratio][$k] = new Point(array(
                            'x' => $i - 4 * $hd_ratio,
                            'y' => 12 * $hd_ratio + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            if ($is_alex) {
                for ($i = 0; $i < 3 * $hd_ratio; $i++) {
                    for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                        $this->polygons['rightArm']['back'][]  = new Polygon(array(
                            $volume_points[$i][$j][0],
                            $volume_points[$i + 1][$j][0],
                            $volume_points[$i + 1][$j + 1][0],
                            $volume_points[$i][$j + 1][0]
                        ), imagecolorat($img_png, (51 * $hd_ratio - 1) - $i, 20 * $hd_ratio + $j));
                        $this->polygons['rightArm']['front'][] = new Polygon(array(
                            $volume_points[$i][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                            $volume_points[$i][$j + 1][4 * $hd_ratio]
                        ), imagecolorat($img_png, 44 * $hd_ratio + $i, 20 * $hd_ratio + $j));
                    }
                }
            } else {
                for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                    for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                        $this->polygons['rightArm']['back'][]  = new Polygon(array(
                            $volume_points[$i][$j][0],
                            $volume_points[$i + 1][$j][0],
                            $volume_points[$i + 1][$j + 1][0],
                            $volume_points[$i][$j + 1][0]
                        ), imagecolorat($img_png, (56 * $hd_ratio - 1) - $i, 20 * $hd_ratio + $j));
                        $this->polygons['rightArm']['front'][] = new Polygon(array(
                            $volume_points[$i][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                            $volume_points[$i][$j + 1][4 * $hd_ratio]
                        ), imagecolorat($img_png, 44 * $hd_ratio + $i, 20 * $hd_ratio + $j));
                    }
                }
            }
            for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    $right_offset_x = ($is_alex ? 47 : 40) * $hd_ratio;
                    $this->polygons['rightArm']['right'][] = new Polygon(array(
                        $volume_points[0][$j][$k],
                        $volume_points[0][$j][$k + 1],
                        $volume_points[0][$j + 1][$k + 1],
                        $volume_points[0][$j + 1][$k]
                    ), imagecolorat($img_png, $right_offset_x + $k, 20 * $hd_ratio + $j));
                    $left_offset_x = ($is_alex ? 40 : 52) * $hd_ratio;
                    $this->polygons['rightArm']['left'][]  = new Polygon(array(
                        $volume_points[4 * $hd_ratio][$j][$k],
                        $volume_points[4 * $hd_ratio][$j][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k]
                    ), imagecolorat($img_png, ($left_offset_x - 1) - $k, 20 * $hd_ratio + $j));
                }
            }
            if ($is_alex) {
                for ($i = 0; $i < 3 * $hd_ratio; $i++) {
                    for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                        $this->polygons['rightArm']['top'][]    = new Polygon(array(
                            $volume_points[$i][0][$k],
                            $volume_points[$i + 1][0][$k],
                            $volume_points[$i + 1][0][$k + 1],
                            $volume_points[$i][0][$k + 1]
                        ), imagecolorat($img_png, 44 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                        $this->polygons['rightArm']['bottom'][] = new Polygon(array(
                            $volume_points[$i][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                            $volume_points[$i][12 * $hd_ratio][$k + 1]
                        ), imagecolorat($img_png, 47 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                    }
                }
            } else {
                for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                    for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                        $this->polygons['rightArm']['top'][]    = new Polygon(array(
                            $volume_points[$i][0][$k],
                            $volume_points[$i + 1][0][$k],
                            $volume_points[$i + 1][0][$k + 1],
                            $volume_points[$i][0][$k + 1]
                        ), imagecolorat($img_png, 44 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                        $this->polygons['rightArm']['bottom'][] = new Polygon(array(
                            $volume_points[$i][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                            $volume_points[$i][12 * $hd_ratio][$k + 1]
                        ), imagecolorat($img_png, 48 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                    }
                }
            }
            // LEFT ARM
            $volume_points = array();
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                    if (!isset($volume_points[$i][$j][0])) {
                        $volume_points[$i][$j][0] = new Point(array(
                            'x' => $i + 8 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 0
                        ));
                    }
                    if (!isset($volume_points[$i][$j][4 * $hd_ratio])) {
                        $volume_points[$i][$j][4 * $hd_ratio] = new Point(array(
                            'x' => $i + 8 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => 4 * $hd_ratio
                        ));
                    }
                }
            }
            for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[0][$j][$k])) {
                        $volume_points[0][$j][$k] = new Point(array(
                            'x' => 8 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                        $volume_points[4 * $hd_ratio][$j][$k] = new Point(array(
                            'x' => 4 * $hd_ratio + 8 * $hd_ratio,
                            'y' => $j + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[$i][0][$k])) {
                        $volume_points[$i][0][$k] = new Point(array(
                            'x' => $i + 8 * $hd_ratio,
                            'y' => 0 + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[$i][12 * $hd_ratio][$k])) {
                        $volume_points[$i][12 * $hd_ratio][$k] = new Point(array(
                            'x' => $i + 8 * $hd_ratio,
                            'y' => 12 * $hd_ratio + 8 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            if ($is_alex) {
                for ($i = 0; $i < 3 * $hd_ratio; $i++) {
                    for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                        $this->polygons['leftArm']['back'][]  = new Polygon(array(
                            $volume_points[$i][$j][0],
                            $volume_points[$i + 1][$j][0],
                            $volume_points[$i + 1][$j + 1][0],
                            $volume_points[$i][$j + 1][0]
                        ), imagecolorat($img_png, 43 * $hd_ratio + $i, 52 * $hd_ratio + $j));
                        $this->polygons['leftArm']['front'][] = new Polygon(array(
                            $volume_points[$i][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                            $volume_points[$i][$j + 1][4 * $hd_ratio]
                        ), imagecolorat($img_png, 36 * $hd_ratio + $i, 52 * $hd_ratio + $j));
                    }
                }
            } else {
                for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                    for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                        if ($this->isNewSkinType) {
                            $color1 = imagecolorat($img_png, 47 * $hd_ratio - $i, 52 * $hd_ratio + $j); // from right to left
                            $color2 = imagecolorat($img_png, 36 * $hd_ratio + $i, 52 * $hd_ratio + $j); // from left to right
                        } else {
                            $color1 = imagecolorat($img_png, (56 * $hd_ratio - 1) - ((4 * $hd_ratio - 1) - $i), 20 * $hd_ratio + $j);
                            $color2 = imagecolorat($img_png, 44 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), 20 * $hd_ratio + $j);
                        }

                        $this->polygons['leftArm']['back'][]  = new Polygon(array(
                            $volume_points[$i][$j][0],
                            $volume_points[$i + 1][$j][0],
                            $volume_points[$i + 1][$j + 1][0],
                            $volume_points[$i][$j + 1][0]
                        ), $color1);
                        $this->polygons['leftArm']['front'][] = new Polygon(array(
                            $volume_points[$i][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j][4 * $hd_ratio],
                            $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                            $volume_points[$i][$j + 1][4 * $hd_ratio]
                        ), $color2);
                    }
                }
            }
            for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    if ($this->isNewSkinType) {
                        $color1 = imagecolorat($img_png, 32 * $hd_ratio + $k, 52 * $hd_ratio + $j); // from left to right
                        $color2 = imagecolorat($img_png, 43 * $hd_ratio - $k, 52 * $hd_ratio + $j); // from right to left
                    } else {
                        $color1 = imagecolorat($img_png, 40 * $hd_ratio + ((4 * $hd_ratio - 1) - $k), 20 * $hd_ratio + $j);
                        $color2 = imagecolorat($img_png, (52 * $hd_ratio - 1) - ((4 * $hd_ratio - 1) - $k), 20 * $hd_ratio + $j);
                    }

                    $this->polygons['leftArm']['right'][] = new Polygon(array(
                        $volume_points[0][$j][$k],
                        $volume_points[0][$j][$k + 1],
                        $volume_points[0][$j + 1][$k + 1],
                        $volume_points[0][$j + 1][$k]
                    ), $color1);
                    $this->polygons['leftArm']['left'][]  = new Polygon(array(
                        $volume_points[4 * $hd_ratio][$j][$k],
                        $volume_points[4 * $hd_ratio][$j][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k]
                    ), $color2);
                }
            }
            if ($is_alex) {
                for ($i = 0; $i < 3 * $hd_ratio; $i++) {
                    for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                        $this->polygons['leftArm']['top'][]    = new Polygon(array(
                            $volume_points[$i][0][$k],
                            $volume_points[$i + 1][0][$k],
                            $volume_points[$i + 1][0][$k + 1],
                            $volume_points[$i][0][$k + 1]
                        ), imagecolorat($img_png, 36 * $hd_ratio + $i, 48 * $hd_ratio + $k));
                        $this->polygons['leftArm']['bottom'][] = new Polygon(array(
                            $volume_points[$i][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                            $volume_points[$i][12 * $hd_ratio][$k + 1]
                        ), imagecolorat($img_png, 39 * $hd_ratio + $i, 48 * $hd_ratio + $k));
                    }
                }
            } else {
                for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                    for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                        if ($this->isNewSkinType) {
                            $color1 = imagecolorat($img_png, 36 * $hd_ratio + $i, 48 * $hd_ratio + $k); // from left to right
                            $color2 = imagecolorat($img_png, 40 * $hd_ratio + $i, 48 * $hd_ratio + $k); // from left to right
                        } else {
                            $color1 = imagecolorat($img_png, 44 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), 16 * $hd_ratio + $k);
                            $color2 = imagecolorat($img_png, 48 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), (20 * $hd_ratio - 1) - $k);
                        }

                        $this->polygons['leftArm']['top'][]    = new Polygon(array(
                            $volume_points[$i][0][$k],
                            $volume_points[$i + 1][0][$k],
                            $volume_points[$i + 1][0][$k + 1],
                            $volume_points[$i][0][$k + 1]
                        ), $color1);
                        $this->polygons['leftArm']['bottom'][] = new Polygon(array(
                            $volume_points[$i][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k],
                            $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                            $volume_points[$i][12 * $hd_ratio][$k + 1]
                        ), $color2);
                    }
                }
            }
            // RIGHT LEG
            $volume_points = array();
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                    if (!isset($volume_points[$i][$j][0])) {
                        $volume_points[$i][$j][0] = new Point(array(
                            'x' => $i,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => 0
                        ));
                    }
                    if (!isset($volume_points[$i][$j][4 * $hd_ratio])) {
                        $volume_points[$i][$j][4 * $hd_ratio] = new Point(array(
                            'x' => $i,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => 4 * $hd_ratio
                        ));
                    }
                }
            }
            for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[0][$j][$k])) {
                        $volume_points[0][$j][$k] = new Point(array(
                            'x' => 0,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                        $volume_points[4 * $hd_ratio][$j][$k] = new Point(array(
                            'x' => 4 * $hd_ratio,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[$i][0][$k])) {
                        $volume_points[$i][0][$k] = new Point(array(
                            'x' => $i,
                            'y' => 0 + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[$i][12 * $hd_ratio][$k])) {
                        $volume_points[$i][12 * $hd_ratio][$k] = new Point(array(
                            'x' => $i,
                            'y' => 12 * $hd_ratio + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                    $this->polygons['rightLeg']['back'][]  = new Polygon(array(
                        $volume_points[$i][$j][0],
                        $volume_points[$i + 1][$j][0],
                        $volume_points[$i + 1][$j + 1][0],
                        $volume_points[$i][$j + 1][0]
                    ), imagecolorat($img_png, (16 * $hd_ratio - 1) - $i, 20 * $hd_ratio + $j));
                    $this->polygons['rightLeg']['front'][] = new Polygon(array(
                        $volume_points[$i][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                        $volume_points[$i][$j + 1][4 * $hd_ratio]
                    ), imagecolorat($img_png, 4 * $hd_ratio + $i, 20 * $hd_ratio + $j));
                }
            }
            for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    $this->polygons['rightLeg']['right'][] = new Polygon(array(
                        $volume_points[0][$j][$k],
                        $volume_points[0][$j][$k + 1],
                        $volume_points[0][$j + 1][$k + 1],
                        $volume_points[0][$j + 1][$k]
                    ), imagecolorat($img_png, 0 + $k, 20 * $hd_ratio + $j));
                    $this->polygons['rightLeg']['left'][]  = new Polygon(array(
                        $volume_points[4 * $hd_ratio][$j][$k],
                        $volume_points[4 * $hd_ratio][$j][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k]
                    ), imagecolorat($img_png, (12 * $hd_ratio - 1) - $k, 20 * $hd_ratio + $j));
                }
            }
            for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    $this->polygons['rightLeg']['top'][]    = new Polygon(array(
                        $volume_points[$i][0][$k],
                        $volume_points[$i + 1][0][$k],
                        $volume_points[$i + 1][0][$k + 1],
                        $volume_points[$i][0][$k + 1]
                    ), imagecolorat($img_png, 4 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                    $this->polygons['rightLeg']['bottom'][] = new Polygon(array(
                        $volume_points[$i][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                        $volume_points[$i][12 * $hd_ratio][$k + 1]
                    ), imagecolorat($img_png, 8 * $hd_ratio + $i, 16 * $hd_ratio + $k));
                }
            }
            // LEFT LEG
            $volume_points = array();
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                    if (!isset($volume_points[$i][$j][0])) {
                        $volume_points[$i][$j][0] = new Point(array(
                            'x' => $i + 4 * $hd_ratio,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => 0
                        ));
                    }
                    if (!isset($volume_points[$i][$j][4 * $hd_ratio])) {
                        $volume_points[$i][$j][4 * $hd_ratio] = new Point(array(
                            'x' => $i + 4 * $hd_ratio,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => 4 * $hd_ratio
                        ));
                    }
                }
            }
            for ($j = 0; $j < 13 * $hd_ratio; $j++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[0][$j][$k])) {
                        $volume_points[0][$j][$k] = new Point(array(
                            'x' => 0 + 4 * $hd_ratio,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[8 * $hd_ratio][$j][$k])) {
                        $volume_points[4 * $hd_ratio][$j][$k] = new Point(array(
                            'x' => 4 * $hd_ratio + 4 * $hd_ratio,
                            'y' => $j + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 9 * $hd_ratio; $i++) {
                for ($k = 0; $k < 5 * $hd_ratio; $k++) {
                    if (!isset($volume_points[$i][0][$k])) {
                        $volume_points[$i][0][$k] = new Point(array(
                            'x' => $i + 4 * $hd_ratio,
                            'y' => 0 + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                    if (!isset($volume_points[$i][12 * $hd_ratio][$k])) {
                        $volume_points[$i][12 * $hd_ratio][$k] = new Point(array(
                            'x' => $i + 4 * $hd_ratio,
                            'y' => 12 * $hd_ratio + 20 * $hd_ratio,
                            'z' => $k
                        ));
                    }
                }
            }
            for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                    if ($this->isNewSkinType) {
                        $color1 = imagecolorat($img_png, 31 * $hd_ratio - $i, 52 * $hd_ratio + $j); // from right to left
                        $color2 = imagecolorat($img_png, 20 * $hd_ratio + $i, 52 * $hd_ratio + $j); // from left to right
                    } else {
                        $color1 = imagecolorat($img_png, (16 * $hd_ratio - 1) - ((4 * $hd_ratio - 1) - $i), 20 * $hd_ratio + $j);
                        $color2 = imagecolorat($img_png, 4 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), 20 * $hd_ratio + $j);
                    }

                    $this->polygons['leftLeg']['back'][]  = new Polygon(array(
                        $volume_points[$i][$j][0],
                        $volume_points[$i + 1][$j][0],
                        $volume_points[$i + 1][$j + 1][0],
                        $volume_points[$i][$j + 1][0]
                    ), $color1);
                    $this->polygons['leftLeg']['front'][] = new Polygon(array(
                        $volume_points[$i][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j][4 * $hd_ratio],
                        $volume_points[$i + 1][$j + 1][4 * $hd_ratio],
                        $volume_points[$i][$j + 1][4 * $hd_ratio]
                    ), $color2);
                }
            }
            for ($j = 0; $j < 12 * $hd_ratio; $j++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    if ($this->isNewSkinType) {
                        $color1 = imagecolorat($img_png, 16 * $hd_ratio + $k, 52 * $hd_ratio + $j); // from left to right
                        $color2 = imagecolorat($img_png, 27 * $hd_ratio - $k, 52 * $hd_ratio + $j); // from right to left
                    } else {
                        $color1 = imagecolorat($img_png, 0 + ((4 * $hd_ratio - 1) - $k), 20 * $hd_ratio + $j);
                        $color2 = imagecolorat($img_png, (12 * $hd_ratio - 1) - ((4 * $hd_ratio - 1) - $k), 20 * $hd_ratio + $j);
                    }

                    $this->polygons['leftLeg']['right'][] = new Polygon(array(
                        $volume_points[0][$j][$k],
                        $volume_points[0][$j][$k + 1],
                        $volume_points[0][$j + 1][$k + 1],
                        $volume_points[0][$j + 1][$k]
                    ), $color1);
                    $this->polygons['leftLeg']['left'][]  = new Polygon(array(
                        $volume_points[4 * $hd_ratio][$j][$k],
                        $volume_points[4 * $hd_ratio][$j][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k + 1],
                        $volume_points[4 * $hd_ratio][$j + 1][$k]
                    ), $color2);
                }
            }
            for ($i = 0; $i < 4 * $hd_ratio; $i++) {
                for ($k = 0; $k < 4 * $hd_ratio; $k++) {
                    if ($this->isNewSkinType) {
                        $color1 = imagecolorat($img_png, 20 * $hd_ratio + $i, 48 * $hd_ratio + $k); // from left to right
                        $color2 = imagecolorat($img_png, 24 * $hd_ratio + $i, 48 * $hd_ratio + $k); // from left to right
                    } else {
                        $color1 = imagecolorat($img_png, 4 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), 16 * $hd_ratio + $k);
                        $color2 = imagecolorat($img_png, 8 * $hd_ratio + ((4 * $hd_ratio - 1) - $i), (20 * $hd_ratio - 1) - $k);
                    }

                    $this->polygons['leftLeg']['top'][]    = new Polygon(array(
                        $volume_points[$i][0][$k],
                        $volume_points[$i + 1][0][$k],
                        $volume_points[$i + 1][0][$k + 1],
                        $volume_points[$i][0][$k + 1]
                    ), $color1);
                    $this->polygons['leftLeg']['bottom'][] = new Polygon(array(
                        $volume_points[$i][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k],
                        $volume_points[$i + 1][12 * $hd_ratio][$k + 1],
                        $volume_points[$i][12 * $hd_ratio][$k + 1]
                    ), $color2);
                }
            }
        }
    }

    private function memberRotation()
    {
        foreach ($this->polygons['head'] as $face) {
            foreach ($face as $poly) {
                $poly->preProject(4, 8, 2, $this->members_angles['head']['cos_alpha'], $this->members_angles['head']['sin_alpha'], $this->members_angles['head']['cos_omega'], $this->members_angles['head']['sin_omega']);
            }
        }

        foreach ($this->polygons['helmet'] as $face) {
            foreach ($face as $poly) {
                $poly->preProject(4, 8, 2, $this->members_angles['head']['cos_alpha'], $this->members_angles['head']['sin_alpha'], $this->members_angles['head']['cos_omega'], $this->members_angles['head']['sin_omega']);
            }
        }

        if (!$this->head_only) {
            foreach ($this->polygons['rightArm'] as $face) {
                foreach ($face as $poly) {
                    $poly->preProject(-2, 8, 2, $this->members_angles['rightArm']['cos_alpha'], $this->members_angles['rightArm']['sin_alpha'], $this->members_angles['rightArm']['cos_omega'], $this->members_angles['rightArm']['sin_omega']);
                }
            }
            foreach ($this->polygons['leftArm'] as $face) {
                foreach ($face as $poly) {
                    $poly->preProject(10, 8, 2, $this->members_angles['leftArm']['cos_alpha'], $this->members_angles['leftArm']['sin_alpha'], $this->members_angles['leftArm']['cos_omega'], $this->members_angles['leftArm']['sin_omega']);
                }
            }
            foreach ($this->polygons['rightLeg'] as $face) {
                foreach ($face as $poly) {
                    $poly->preProject(2, 20, ($this->members_angles['rightLeg']['sin_alpha'] < 0 ? 0 : 4), $this->members_angles['rightLeg']['cos_alpha'], $this->members_angles['rightLeg']['sin_alpha'], $this->members_angles['rightLeg']['cos_omega'], $this->members_angles['rightLeg']['sin_omega']);
                }
            }
            foreach ($this->polygons['leftLeg'] as $face) {
                foreach ($face as $poly) {
                    $poly->preProject(6, 20, ($this->members_angles['leftLeg']['sin_alpha'] < 0 ? 0 : 4), $this->members_angles['leftLeg']['cos_alpha'], $this->members_angles['leftLeg']['sin_alpha'], $this->members_angles['leftLeg']['cos_omega'], $this->members_angles['leftLeg']['sin_omega']);
                }
            }
        }
    }

    private function createProjectionPlan()
    {
        $cos_alpha = $this->cos_alpha;
        $sin_alpha = $this->sin_alpha;
        $cos_omega = $this->cos_omega;
        $sin_omega = $this->sin_omega;

        foreach ($this->polygons as $piece) {
            foreach ($piece as $face) {
                foreach ($face as $poly) {
                    if (!$poly->isProjected()) {
                        $poly->project($cos_alpha, $sin_alpha, $cos_omega, $sin_omega, $this->minX, $this->maxX, $this->minY, $this->maxY);
                    }
                }
            }
        }
    }

    private function displayImage()
    {
        $width = $this->maxX - $this->minX;
        $height = $this->maxY - $this->minY;
        $ratio = $this->ratio * 2;

        $srcWidth = $ratio * $width + 1;
        $srcHeight = $ratio * $height + 1;

        $image = ImageUtil::createEmptyCanvas($srcWidth, $srcHeight);

        $display_order = $this->getDisplayOrder();

        foreach ($display_order as $pieces) {
            foreach ($pieces as $piece => $faces) {
                foreach ($faces as $face) {
                    foreach ($this->polygons[$piece][$face] as $poly) {
                        $poly->addPngPolygon($image, $this->minX, $this->minY, $ratio);
                    }
                }
            }
        }

        // Anti-aliasing
        $realWidth = $srcWidth / 2;
        $realHeight = $srcHeight / 2;
        $destImage = ImageUtil::createEmptyCanvas($realWidth, $realHeight);
        imagecopyresampled($destImage, $image, 0, 0, 0, 0, $realWidth, $realHeight, $srcWidth, $srcHeight);
        $image = $destImage;

        return $image;
    }

    private function getDisplayOrder()
    {
        $display_order = array();
        if (in_array('top', $this->front_faces)) {
            if (in_array('right', $this->front_faces)) {
                $display_order[] = array('leftLeg' => $this->back_faces);
                $display_order[] = array('leftLeg' => $this->visible_faces['leftLeg']['front']);
                $display_order[] = array('rightLeg' => $this->back_faces);
                $display_order[] = array('rightLeg' => $this->visible_faces['rightLeg']['front']);
                $display_order[] = array('leftArm' => $this->back_faces);
                $display_order[] = array('leftArm' => $this->visible_faces['leftArm']['front']);
                $display_order[] = array('torso' => $this->back_faces);
                $display_order[] = array('torso' => $this->visible_faces['torso']['front']);
                $display_order[] = array('rightArm' => $this->back_faces);
                $display_order[] = array('rightArm' => $this->visible_faces['rightArm']['front']);
            } else {
                $display_order[] = array('rightLeg' => $this->back_faces);
                $display_order[] = array('rightLeg' => $this->visible_faces['rightLeg']['front']);
                $display_order[] = array('leftLeg' => $this->back_faces);
                $display_order[] = array('leftLeg' => $this->visible_faces['leftLeg']['front']);
                $display_order[] = array('rightArm' => $this->back_faces);
                $display_order[] = array('rightArm' => $this->visible_faces['rightArm']['front']);
                $display_order[] = array('torso' => $this->back_faces);
                $display_order[] = array('torso' => $this->visible_faces['torso']['front']);
                $display_order[] = array('leftArm' => $this->back_faces);
                $display_order[] = array('leftArm' => $this->visible_faces['leftArm']['front']);
            }

            $display_order[] = array('helmet' => $this->back_faces);
            $display_order[] = array('head' => $this->back_faces);
            $display_order[] = array('head' => $this->visible_faces['head']['front']);
            $display_order[] = array('helmet' => $this->visible_faces['head']['front']);
        } else {
            $display_order[] = array('helmet' => $this->back_faces);
            $display_order[] = array('head' => $this->back_faces);
            $display_order[] = array('head' => $this->visible_faces['head']['front']);
            $display_order[] = array('helmet' => $this->visible_faces['head']['front']);

            if (in_array('right', $this->front_faces)) {
                $display_order[] = array('leftArm' => $this->back_faces);
                $display_order[] = array('leftArm' => $this->visible_faces['leftArm']['front']);
                $display_order[] = array('torso' => $this->back_faces);
                $display_order[] = array('torso' => $this->visible_faces['torso']['front']);
                $display_order[] = array('rightArm' => $this->back_faces);
                $display_order[] = array('rightArm' => $this->visible_faces['rightArm']['front']);
                $display_order[] = array('leftLeg' => $this->back_faces);
                $display_order[] = array('leftLeg' => $this->visible_faces['leftLeg']['front']);
                $display_order[] = array('rightLeg' => $this->back_faces);
                $display_order[] = array('rightLeg' => $this->visible_faces['rightLeg']['front']);
            } else {
                $display_order[] = array('rightArm' => $this->back_faces);
                $display_order[] = array('rightArm' => $this->visible_faces['rightArm']['front']);
                $display_order[] = array('torso' => $this->back_faces);
                $display_order[] = array('torso' => $this->visible_faces['torso']['front']);
                $display_order[] = array('leftArm' => $this->back_faces);
                $display_order[] = array('leftArm' => $this->visible_faces['leftArm']['front']);
                $display_order[] = array('rightLeg' => $this->back_faces);
                $display_order[] = array('rightLeg' => $this->visible_faces['rightLeg']['front']);
                $display_order[] = array('leftLeg' => $this->back_faces);
                $display_order[] = array('leftLeg' => $this->visible_faces['leftLeg']['front']);
            }
        }

        return $display_order;
    }
}
