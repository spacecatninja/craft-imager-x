<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\AssetBundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Værsågod
 * @package   Imager
 * @since     4.0.0
 */
class ImagerUtilityAssets extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init():void
    {
        $this->sourcePath = '@SpaceCatNinja/ImagerX/AssetBundles/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'imagerutility.js',
        ];

        $this->css = [
            'imagerutility.css',
        ];

        parent::init();
    }
}
