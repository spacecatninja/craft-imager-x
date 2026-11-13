<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Concerns;

use mikehaertl\shellcommand\Command;

trait RunShellCommandTrait
{
    /**
     * Runs a shell command through mikehaertl\shellcommand
     *
     * @param $commandString
     */
    private static function runShellCommand($commandString): string
    {
        $shellCommand = new Command();
        $shellCommand->setCommand($commandString);

        return $shellCommand->execute() ? $shellCommand->getOutput() : $shellCommand->getError();
    }
}
