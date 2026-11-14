<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Optimizers;


use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Concerns\RunShellCommandTrait;
use CraftCms\Cms\Support\Env;
use Illuminate\Support\Facades\Log;

class JpegtranOptimizer implements ImagerOptimizeInterface
{
    use RunShellCommandTrait;

    public static function optimize(string $file, ?array $settings): void
    {
        $config = ImagerService::getConfig();

        if ($config->skipExecutableExistCheck || file_exists(Env::parse($settings['path']))) {
            $cmd = Env::parse($settings['path']);
            $cmd .= ' ';
            $cmd .= escapeshellcmd($settings['optionString']);
            $cmd .= ' -outfile ';
            $cmd .= escapeshellarg($file);
            $cmd .= ' ';
            $cmd .= escapeshellarg($file);

            $result = self::runShellCommand($cmd);
            
            Log::info('Command "' . $cmd . '" returned "' . $result . '"');
        } else {
            Log::error('Optimizer ' . self::class . ' could not be found in path ' . $settings['path']);
        }
    }
}
