<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\models;

use craft\base\Model;

use craft\helpers\FileHelper;
use spacecatninja\imagerx\services\ImagerService;

class ConfigModel extends Settings
{
    /**
     * @var string
     */
    private $configOverrideString = '';

    /**
     * TransformSettings constructor.
     *
     * @param Settings|Model $settings
     * @param array          $overrides
     * @param array          $config
     */
    public function __construct($settings, $overrides = null, $config = [])
    {
        parent::__construct($config);

        // Reset model to get overrides from config file 
        foreach ($settings as $key => $value) {
            $this->$key = $value;
        }

        // Apply transform overrides
        $excludedConfigOverrideProperties = ['fillTransforms', 'fillInterval', 'fillAttribute', 'filenamePattern', 'transformer', 'optimizeType'];

        if ($overrides !== null) {
            foreach ($overrides as $key => $value) {
                $this->$key = $value;
                if (!\in_array($key, $excludedConfigOverrideProperties, true)) {
                    $this->addToOverrideFilestring($key, $value);
                }
            }
        }

        // Prep position value
        if (isset(ImagerService::$craftPositionTranslate[(string)$this->position])) {
            $this->position = ImagerService::$craftPositionTranslate[(string)$this->position];
        }

        $this->position = str_replace('%', '', $this->position);

        // Replace aliases
        $parseables = ['imagerSystemPath', 'imagerUrl'];

        foreach ($parseables as $parseable) {
            $this->{$parseable} = \Craft::parseEnv($this->{$parseable});
        }
        
        // Normalize imager system path 
        $this->imagerSystemPath = FileHelper::normalizePath($this->imagerSystemPath);
    }

    /**
     * Get setting by key. If there is an override in transform, that is returned instead of the value in the model.
     *
     * @param string     $key
     * @param array|null $transform
     *
     * @return mixed
     */
    public function getSetting($key, $transform = null)
    {
        if (isset($transform[$key])) {
            return $transform[$key];
        }

        return $this[$key];
    }

    /**
     * Returns config override string for this config model
     *
     * @return string
     */
    public function getConfigOverrideString(): string
    {
        return $this->configOverrideString;
    }

    /**
     * Creates additional file string based on config overrides that is appended to filename
     *
     * @param string $key
     * @param mixed  $value
     */
    private function addToOverrideFilestring($key, $value)
    {
        $r = (ImagerService::$transformKeyTranslate[$key] ?? $key).(\is_array($value) ? md5(implode('-', $value)) : $value);
        $this->configOverrideString .= '_'.str_replace('%', '', str_replace([' ', '.'], '-', $r));
    }

}
