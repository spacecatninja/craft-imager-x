<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\externalstorage;

use Craft;
use craft\helpers\FileHelper;

use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Storage\StorageClient;

use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\services\ImagerService;

class GcsStorage implements ImagerStorageInterface
{

    /**
     * @param string $file
     * @param string $uri
     * @param bool $isFinal
     * @param array $settings
     * @return bool
     */
    public static function upload(string $file, string $uri, bool $isFinal, array $settings): bool
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();
        
        if (isset($settings['folder']) && $settings['folder']!=='') {
            $uri = ltrim(FileHelper::normalizePath($settings['folder'] . '/' . $uri), '/');
        }
        
        // Always use forward slashes
        $uri = str_replace('\\', '/', $uri);
        
        $keyFilePath = FileHelper::normalizePath($settings['keyFile']);
        
        $storage = new StorageClient([
            'keyFilePath' => $keyFilePath 
        ]);
        
        $bucket = $storage->bucket($settings['bucket']);
        $cacheDuration = $isFinal ? $config->cacheDurationExternalStorage : $config->cacheDurationNonOptimized;
        
        try {
            $bucket->upload(
                fopen($file, 'rb'),
                [
                    'name' => $uri,
                    'predefinedAcl' => 'publicRead',
                    'metadata' => [
                        'cacheControl' => 'max-age=' . $cacheDuration . ', must-revalidate'
                    ]
                ]
            );
        } catch(ServiceException $e) {
            Craft::error('An error occured while uploading to Google Cloud Storage: ' . $e->getServiceException()->getMessage(), __METHOD__);
            return false;
        }

        return true;
    }

}
