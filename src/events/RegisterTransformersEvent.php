<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\events;

use yii\base\Event;

class RegisterTransformersEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array List of transformers
     */
    public $transformers = [];
}
