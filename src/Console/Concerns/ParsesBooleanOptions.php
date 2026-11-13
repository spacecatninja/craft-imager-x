<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Console\Concerns;

/**
 * Reads boolean options the way the Craft 5 console commands accepted them: `--force` on its own,
 * or with a value like `--force=1` or `--force=false`.
 *
 * Options using this must be defined with an optional value (`{--force=false}`), so that a bare
 * `--force` is passed as `null`.
 */
trait ParsesBooleanOptions
{
    protected function booleanOption(string $name): bool
    {
        $value = $this->option($name);

        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
