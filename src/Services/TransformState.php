<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Services;

use Illuminate\Container\Attributes\Scoped;
use SpaceCatNinja\ImagerX\Data\ConfigModel;

/**
 * State for the transform that is being processed. Scoped, so that it never leaks between requests or queue jobs.
 */
#[Scoped]
class TransformState
{
    /**
     * @var ConfigModel|null The config for the current transform, with its config overrides applied.
     */
    public ?ConfigModel $config = null;

    /**
     * @var string The handle of the named transform being processed, if any.
     */
    public string $processingNamedTransform = '';

    /**
     * @var string[] Remote files that were downloaded to the runtime cache during the current transform.
     */
    public array $cachedRemoteFiles = [];
}
