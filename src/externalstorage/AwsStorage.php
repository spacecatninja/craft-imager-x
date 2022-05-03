<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\externalstorage;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Craft;
use craft\helpers\FileHelper;

use spacecatninja\imagerx\services\ImagerService;

class AwsStorage implements ImagerStorageInterface
{
    /**
     * @throws \Exception
     */
    public static function upload(string $file, string $uri, bool $isFinal, array $settings): bool
    {
        $config = ImagerService::getConfig();

        $clientConfig = [
            'version' => 'latest',
            'region' => $settings['region'],
        ];

        if (isset($settings['accessKey'], $settings['secretAccessKey'])) {
            $clientConfig['credentials'] = [
                'key' => $settings['accessKey'],
                'secret' => $settings['secretAccessKey'],
            ];
        }

        try {
            $s3 = new S3Client($clientConfig);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            Craft::error('Invalid configuration of S3 Client: ' . $invalidArgumentException->getMessage(), __METHOD__);
            return false;
        }
        
        if (isset($settings['folder']) && $settings['folder'] !== '') {
            $uri = FileHelper::normalizePath($settings['folder'] . '/' . $uri);
        }

        // Always use forward slashes for S3
        $uri = str_replace('\\', '/', $uri);

        // Dont start with forward slashes
        $uri = ltrim($uri, '/');

        $opts = $settings['requestHeaders'] ?? [];
        $cacheDuration = $isFinal ? $config->cacheDurationExternalStorage : $config->cacheDurationNonOptimized;
        $visibility = !isset($settings['public']) || $settings['public'] === true ? 'public-read' : 'private';

        if (!isset($opts['CacheControl'])) {
            $opts['CacheControl'] = 'max-age=' . $cacheDuration . ', must-revalidate';
        }

        $opts = array_merge($opts, [
            'Bucket' => $settings['bucket'],
            'Key' => $uri,
            'Body' => fopen($file, 'rb'),
            'ACL' => $visibility,
            'StorageClass' => self::getAWSStorageClass($settings['storageType'] ?? 'standard'),
        ]);

        try {
            $s3->putObject($opts);
        } catch (S3Exception $s3Exception) {
            Craft::error('An error occured while uploading to Amazon S3: ' . $s3Exception->getMessage(), __METHOD__);
            return false;
        }

        // Cloudfront invalidation
        if (isset($settings['cloudfrontInvalidateEnabled'], $settings['cloudfrontDistributionId']) && $settings['cloudfrontInvalidateEnabled'] === true) {
            try {
                $cloudfront = new CloudFrontClient($clientConfig);
            } catch (\InvalidArgumentException $s3Exception) {
                Craft::error('Invalid configuration of CloudFront Client: ' . $s3Exception->getMessage(), __METHOD__);
                return false;
            }
            
            try {
                $cloudfront->createInvalidation([
                    'DistributionId' => $settings['cloudfrontDistributionId'],
                    'InvalidationBatch' => [
                        'Paths' => [
                            'Quantity' => 1,
                            'Items' => ['/' . $uri],
                        ],
                        'CallerReference' => md5($uri . random_int(111111, 999999)),
                    ],
                ]);
            } catch (CloudFrontException $cloudFrontException) {
                Craft::error('An error occured while sending an Cloudfront invalidation request: ' . $cloudFrontException->getMessage(), __METHOD__);
            }
        }

        return true;
    }
    
    /**
     * @param $storageTypeString
     */
    private static function getAWSStorageClass($storageTypeString): string
    {
        return match (mb_strtolower($storageTypeString)) {
            'rrs' => 'REDUCED_REDUNDANCY',
            'glacier' => 'GLACIER',
            default => 'STANDARD',
        };
    }
}
