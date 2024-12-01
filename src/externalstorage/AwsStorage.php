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

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Craft;
use craft\helpers\App;
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

        $clientConfig = self::buildConfigArray($settings);
        
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

        // Don't start with forward slashes
        $uri = ltrim($uri, '/');

        $opts = $settings['requestHeaders'] ?? [];
        $cacheDuration = $isFinal ? $config->cacheDurationExternalStorage : $config->cacheDurationNonOptimized;
        $visibility = !isset($settings['public']) || $settings['public'] === true ? 'public-read' : 'private';

        if (!isset($opts['CacheControl'])) {
            $opts['CacheControl'] = 'max-age=' . $cacheDuration . ', must-revalidate';
        }

        $opts = array_merge($opts, [
            'Bucket' => App::parseEnv($settings['bucket']),
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
     * Builds config array for S3 client.
     * Mostly lifted from https://github.com/craftcms/aws-s3/blob/a04ee659490d53da879e302e660ba3807532a926/src/Fs.php#L432
     * 
     * @param array $settings
     * @param bool  $refreshToken
     *
     * @return array
     * @throws \yii\base\Exception
     */
    public static function buildConfigArray(array $settings, bool $refreshToken = false): array
    {
        $config = [
            'region' => App::parseEnv($settings['region']),
            'version' => 'latest',
        ];

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        if (isset($settings['useCredentialLessAuth']) && $settings['useCredentialLessAuth'] === true) {
            // Check for predefined access
            if (App::env('AWS_WEB_IDENTITY_TOKEN_FILE') && App::env('AWS_ROLE_ARN')) {
                // Check if anything is defined for a web identity provider (see: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_provider.html#assume-role-with-web-identity-provider)
                $provider = CredentialProvider::assumeRoleWithWebIdentityCredentialProvider();
                $provider = CredentialProvider::memoize($provider);
                $config['credentials'] = $provider;
            }
            // Check if running on ECS
            if (App::env('AWS_CONTAINER_CREDENTIALS_RELATIVE_URI')) {
                // Check if anything is defined for an ecsCredentials provider
                $provider = CredentialProvider::ecsCredentials();
                $provider = CredentialProvider::memoize($provider);
                $config['credentials'] = $provider;
            }
            
            // If that didn't happen, assume we're running on EC2, and we have an IAM role assigned so no action required.
        } else {
            $credentials = new Credentials(App::parseEnv($settings['accessKey']), App::parseEnv($settings['secretAccessKey']));
            $config['credentials'] = $credentials;
        }

        return $config;
    }


    /**
     * @param $storageTypeString
     *
     * @return string
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
