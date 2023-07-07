<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use craft\base\Element;

/**
 * Class CacheHelper
 *
 * @author    SPACECATNINJA
 * @since     4.2.0
 */
class CacheHelpers
{
    public static function getElementCacheTags(Element $element):array
    {
        $class = get_class($element);
        return array_merge($element->getCacheTags(), ['element', "element::$class", "element::$class::$element->id"]);
    }
    
}
