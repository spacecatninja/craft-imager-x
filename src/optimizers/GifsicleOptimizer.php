<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\optimizers;

use Craft;

use craft\helpers\App;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\traits\RunShellCommandTrait;

class GifsicleOptimizer implements ImagerOptimizeInterface
{
    use RunShellCommandTrait;

    public static function optimize(string $file, ?array $settings): void
    {
        $config = ImagerService::getConfig();
        
        if ($config->skipExecutableExistCheck || file_exists(App::parseEnv($settings['path']))) {
            $cmd = App::parseEnv($settings['path']);
            $cmd .= ' ';
            $cmd .= $settings['optionString'];
            $cmd .= ' ';
            $cmd .= '-b ';
            $cmd .= '"' . $file . '"';
            
            $result = self::runShellCommand($cmd);

            Craft::info('Command "' . $cmd . '" returned "' . $result . '"');
        } else {
            Craft::error('Optimizer ' . self::class . ' could not be found in path ' . $settings['path'], __METHOD__);
        }
    }
}
