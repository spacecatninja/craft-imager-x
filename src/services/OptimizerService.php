<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\services;

use Craft;
use craft\base\Component;

use spacecatninja\imagerx\jobs\OptimizeJob;
use spacecatninja\imagerx\models\TransformedImageInterface;
use spacecatninja\imagerx\optimizers\ImagerOptimizeInterface;

/**
 * StorageService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
class OptimizerService extends Component
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
            if (isset(ImagerService::$optimizers[$optimizer])) {
                $optimizerSettings = $config->optimizerConfig[$optimizer] ?? null;

                if ($optimizerSettings) {
                    if ($this->shouldOptimizeByExtension($transformedImage->getExtension(), $optimizerSettings['extensions'])) {
                        if ($config->optimizeType === 'job' || $config->optimizeType === 'task') {
                            $this->createOptimizeJob($optimizer, $transformedImage->getPath(), $optimizerSettings);
                            $jobCreated = true;
                        } else {
                            /** @var ImagerOptimizeInterface $optimizerClass */
                            $optimizerClass = ImagerService::$optimizers[$optimizer];
                            $optimizerClass::optimize($transformedImage->getPath(), $optimizerSettings);

                            // Clear stat cache to make sure old file size is not cached
                            clearstatcache(true, $transformedImage->getPath());
                        }
                    }
                } else {
                    Craft::error('Could not find settings for optimizer "' . $optimizer . '"', __METHOD__);
                }
            } else {
                Craft::error('Could not find a registered optimizer with handle "' . $optimizer . '"', __METHOD__);
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
        $queue = Craft::$app->getQueue();

        $jobId = $queue->push(new OptimizeJob([
            'description' => Craft::t('imager-x', 'Optimizing images (' . $handle . ')'),
            'optimizer' => $handle,
            'optimizerSettings' => $settings,
            'filePath' => $filePath,
        ]));

        Craft::info('Created optimize job for ' . $handle . ' (job id is ' . $jobId . ')', __METHOD__);
    }
}
