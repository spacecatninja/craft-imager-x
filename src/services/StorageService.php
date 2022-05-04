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

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\externalstorage\ImagerStorageInterface;
use spacecatninja\imagerx\ImagerX;

/**
 * StorageService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
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
            if (isset(ImagerService::$storage[$storage])) {
                $storageSettings = $config->storageConfig[$storage] ?? null;

                if ($storageSettings) {
                    /** @var ImagerStorageInterface $storageClass */
                    $storageClass = ImagerService::$storage[$storage];
                    $result = $storageClass::upload($path, $uri, $isFinalVersion, $storageSettings);

                    if (!$result) {
                        // If upload failed, delete transformed file, we assume that we want to try again.
                        unlink($path);
                        $msg = 'An error occured when trying to upload file "' . $path . '" to external storage "' . $storage . '". The transformed file has been deleted.';
                        Craft::error($msg, __METHOD__);
                        // Note, we don't throw exception here, to avoid stopping the processing of the rest of the images.
                    }
                } else {
                    $msg = 'Could not find settings for storage "' . $storage . '".';
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            } else {
                $msg = 'Could not find a registered storage with handle "' . $storage . '".';
                
                if (!ImagerX::getInstance()?->is(ImagerX::EDITION_PRO)) {
                    $msg .= ' External storages are only available when using the Pro edition of Imager, you need to upgrade to use this feature.';
                }
                
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }
    }
}
