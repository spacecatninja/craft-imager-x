<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Events;

use yii\base\Event;

class RegisterOptimizersEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array List of optimizers
     */
    public array $optimizers = [];
}
