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

use SpaceCatNinja\ImagerX\ExternalStorage\ExternalStorages;
use Illuminate\Container\Attributes\Singleton;
use craft\base\Component;

use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\ExternalStorage\ImagerStorageInterface;
use SpaceCatNinja\ImagerX\ImagerX;
use Illuminate\Support\Facades\Log;

/**
 * StorageService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
#[Singleton]
class StorageService extends Component
{
    /**
     * Store transformed image in configured storages
     *
     *
     * @throws ImagerException
     */
    public function store(string $path, bool $isFinalVersion): void
    {
        $config = ImagerService::getConfig();

        if (empty($config->storages)) {
            return;
        }

        $uri = str_replace(realpath($config->imagerSystemPath), '', realpath($path));

        foreach ($config->storages as $storage) {
            /** @var class-string<ImagerStorageInterface>|null $storageClass */
            $storageClass = app(ImagerX::class)->isPro() ? app(ExternalStorages::class)->get($storage) : null;

            if ($storageClass !== null) {
                $storageSettings = $config->storageConfig[$storage] ?? null;

                if ($storageSettings) {
                    $result = $storageClass::upload($path, $uri, $isFinalVersion, $storageSettings);

                    if (!$result) {
                        // If upload failed, delete transformed file, we assume that we want to try again.
                        unlink($path);
                        $msg = 'An error occured when trying to upload file "' . $path . '" to external storage "' . $storage . '". The transformed file has been deleted.';
                        Log::error($msg);
                        // Note, we don't throw exception here, to avoid stopping the processing of the rest of the images.
                    }
                } else {
                    $msg = 'Could not find settings for storage "' . $storage . '".';
                    Log::error($msg);
                    throw new ImagerException($msg);
                }
            } else {
                $msg = 'Could not find a registered storage with handle "' . $storage . '".';
                
                if (!app(ImagerX::class)->isPro()) {
                    $msg .= ' External storages are only available when using the Pro edition of Imager, you need to upgrade to use this feature.';
                }
                
                Log::error($msg);
                throw new ImagerException($msg);
            }
        }
    }
}
