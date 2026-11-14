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

use SpaceCatNinja\ImagerX\Optimizers\Optimizers;
use Illuminate\Container\Attributes\Singleton;
use CraftCms\Cms\Support\Facades\I18N;

use SpaceCatNinja\ImagerX\Jobs\OptimizeJob;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;
use SpaceCatNinja\ImagerX\Optimizers\ImagerOptimizeInterface;
use Illuminate\Support\Facades\Log;

use function CraftCms\Cms\t;

/**
 * StorageService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
#[Singleton]
class OptimizerService
{
    /**
     * Post optimizations
     *
     *
     * @return bool Return if the image is the final version or not. If a task was set up, it's not.
     */
    public function optimize(TransformedImageInterface $transformedImage): bool
    {
        $config = ImagerService::getConfig();

        // If there are no enabled optimizers, exit now
        if (empty($config->optimizers)) {
            return true;
        }

        $jobCreated = false;
        foreach ($config->optimizers as $optimizer) {
            /** @var class-string<ImagerOptimizeInterface>|null $optimizerClass */
            $optimizerClass = app(Optimizers::class)->get($optimizer);

            if ($optimizerClass !== null) {
                $optimizerSettings = $config->optimizerConfig[$optimizer] ?? null;

                if ($optimizerSettings) {
                    if ($this->shouldOptimizeByExtension($transformedImage->getExtension(), $optimizerSettings['extensions'])) {
                        if ($config->optimizeType === 'job' || $config->optimizeType === 'task') {
                            $this->createOptimizeJob($optimizer, $transformedImage->getPath(), $optimizerSettings);
                            $jobCreated = true;
                        } else {
                            $optimizerClass::optimize($transformedImage->getPath(), $optimizerSettings);

                            // Clear stat cache to make sure old file size is not cached
                            clearstatcache(true, $transformedImage->getPath());
                        }
                    }
                } else {
                    Log::error('Could not find settings for optimizer "' . $optimizer . '"');
                }
            } else {
                Log::error('Could not find a registered optimizer with handle "' . $optimizer . '"');
            }
        }

        return !$jobCreated;
    }

    /**
     * Checks if extension is in array of extensions
     *
     *
     */
    private function shouldOptimizeByExtension(string $extension, array $validExtensions): bool
    {
        return \in_array($extension === 'jpeg' ? 'jpg' : $extension, $validExtensions, true);
    }

    /**
     * Creates optimize queue job
     */
    private function createOptimizeJob(string $handle, string $filePath, array $settings): void
    {
        dispatch(new OptimizeJob(
            optimizer: $handle,
            optimizerSettings: $settings,
            filePath: $filePath,
            description: I18N::prep('Optimizing images ({handle})', ['handle' => $handle], 'imager-x'),
        ));

        Log::info('Created optimize job for ' . $handle);
    }
}
