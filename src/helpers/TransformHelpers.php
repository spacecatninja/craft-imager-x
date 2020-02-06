<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use spacecatninja\imagerx\services\ImagerService;
use Craft;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;

class TransformHelpers
{
    /**
     * Resolves any callables in params
     * 
     * TODO : Make recursive, it now only resolves callables at the top level
     *
     * @param Asset|string $image
     * @param array|null $transforms
     * @return array
     */
    public static function resolveTransforms($image, $transforms): array
    {
        if (!$transforms) {
            return [];
        }

        $r = [];

        foreach ($transforms as $transform) {
            foreach ($transform as $key => $val) {
                if (\is_callable($val)) {
                    $resolvedVal = $val($image);
                    $transform[$key] = $resolvedVal;
                }
            }
            
            $r[] = $transform;
        }

        return $r;
    }
    
    /**
     * Fills in the missing transform objects
     *
     * @param array $transforms
     *
     * @return array
     */
    public static function fillTransforms($transforms): array
    {
        $r = [];

        $attributeName = ImagerService::$transformConfig->fillAttribute;
        $interval = ImagerService::$transformConfig->fillInterval;

        $r[] = $transforms[0];

        for ($i = 1, $l = \count($transforms); $i < $l; $i++) {
            $prevTransform = $transforms[$i - 1];
            $currentTransform = $transforms[$i];

            if (isset($prevTransform[$attributeName], $currentTransform[$attributeName])) {
                if ($prevTransform[$attributeName] < $currentTransform[$attributeName]) {
                    for ($num = $prevTransform[$attributeName] + $interval, $maxNum = $currentTransform[$attributeName]; $num < $maxNum; $num += $interval) {
                        $transformCopy = $prevTransform;
                        $transformCopy[$attributeName] = $num;
                        $r[] = $transformCopy;
                    }
                } else {
                    for ($num = $prevTransform[$attributeName] - $interval, $minNum = $currentTransform[$attributeName]; $num > $minNum; $num -= $interval) {
                        $transformCopy = $prevTransform;
                        $transformCopy[$attributeName] = $num;
                        $r[] = $transformCopy;
                    }
                }
            }

            $r[] = $currentTransform;
        }

        return $r;
    }    
    
    /**
     * Merges default transform object into an array of transforms
     *
     * @param array $transforms
     * @param array $defaults
     *
     * @return array
     */
    public static function mergeTransforms($transforms, $defaults): array
    {
        $r = [];

        foreach ($transforms as $t) {
            $r[] = ($defaults !== null ? ArrayHelper::merge($defaults, $t) : $t);
        }

        return $r;
    }
    
    /**
     * Normalizes format of transforms
     *
     * @param array $transforms
     * @param Asset|string $image
     *
     * @return array
     */
    public static function normalizeTransforms($transforms, $image): array
    {
        $r = [];

        foreach ($transforms as $t) {
            $r[] = self::normalizeTransform((array)$t, $image);
        }

        return $r;
    }

    /**
     * Normalize transform object and values
     *
     * @param array $transform
     * @param Asset|string $image
     *
     * @return array
     */
    public static function normalizeTransform($transform, $image): array
    {
        if (isset($transform['mode'])) {
            $transform['mode'] = mb_strtolower($transform['mode']);
        }
        
        // if resize mode is not crop or croponly, remove position
        if (isset($transform['mode'], $transform['position']) && (($transform['mode'] !== 'crop') && ($transform['mode'] !== 'croponly'))) {
            unset($transform['position']);
        }

        // if quality is used, assume it's jpegQuality
        if (isset($transform['quality'])) {
            $value = $transform['quality'];
            unset($transform['quality']);

            if (!isset($transform['jpegQuality'])) {
                $transform['jpegQuality'] = $value;
            }
        }

        // if ratio is set, and width or height is missing, calculate missing size
        if (isset($transform['ratio']) && (\is_float($transform['ratio']) || \is_int($transform['ratio']))) {
            if (isset($transform['width']) && !isset($transform['height'])) {
                $transform['height'] = round($transform['width'] / $transform['ratio']);
                unset($transform['ratio']);
            } else {
                if (isset($transform['height']) && !isset($transform['width'])) {
                    $transform['width'] = round($transform['height'] * $transform['ratio']);
                    unset($transform['ratio']);
                }
            }
        }

        // if no position is passed and a focal point exists, use it
        if ($image instanceof Asset && !isset($transform['position']) && $image->getHasFocalPoint()) {
            $transform['position'] = $image->getFocalPoint();
        }

        // if transform is in Craft's named version, convert to percentage
        if (isset($transform['position'])) {
            if (\is_array($transform['position']) && isset($transform['position']['x'], $transform['position']['y'])) {
                $transform['position'] = ($transform['position']['x'] * 100) . ' ' . ($transform['position']['y'] * 100);
            }

            if (isset(ImagerService::$craftPositionTranslate[(string)$transform['position']])) {
                $transform['position'] = ImagerService::$craftPositionTranslate[(string)$transform['position']];
            }

            $transform['position'] = str_replace('%', '', (string)$transform['position']);
        }
        
        // normalize padding
        if (isset($transform['pad'])) {
            $transform['pad'] = self::normalizePadding($transform['pad']);
        }

        // sort keys to get them in the same order 
        ksort($transform);

        // Move certain keys around abit to make the filename a bit more sane when viewed unencoded
        $transform = ImagerHelpers::moveArrayKeyToPos('mode', 0, $transform);
        $transform = ImagerHelpers::moveArrayKeyToPos('height', 0, $transform);
        $transform = ImagerHelpers::moveArrayKeyToPos('width', 0, $transform);
        $transform = ImagerHelpers::moveArrayKeyToPos('preEffects', 99, $transform);
        $transform = ImagerHelpers::moveArrayKeyToPos('effects', 99, $transform);
        $transform = ImagerHelpers::moveArrayKeyToPos('watermark', 99, $transform);

        return $transform;
    }

    /**
     * @param string|int|array $val
     * @return int[]|null
     */
    public static function normalizePadding($val)
    {
        if (is_int($val)) {
            return [$val, $val, $val, $val];
        }
        
        if (is_string($val)) {
            $val = str_replace('px', '', $val);
            $val = explode(' ', $val);
        }
        
        if (is_array($val)) {
            if (count($val) === 1) {
                return [(int)$val[0], (int)$val[0], (int)$val[0], (int)$val[0]];
            }
            if (count($val) === 2) {
                return [(int)$val[0], (int)$val[1], (int)$val[0], (int)$val[1]];
            }
            if (count($val) === 3) {
                return [(int)$val[0], (int)$val[1], (int)$val[2], (int)$val[1]];
            }
            if (count($val) > 4) {
                $val = array_slice($val, 0, 4);
            }
            if (count($val) === 4) {
                return [(int)$val[0], (int)$val[1], (int)$val[2], (int)$val[3]];
            }
        }
        
        return null;
    }

    public static function calculatePadding($imageWidth, $imageHeight, $padding)
    {
        
    }
}
