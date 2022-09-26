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
use craft\elements\Asset;
use Imgix\UrlBuilder;

use spacecatninja\imagerx\exceptions\ImagerException;

use spacecatninja\imagerx\helpers\ImgixHelpers;
use spacecatninja\imagerx\models\ImgixSettings;

/**
 * ImgixService Service
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Mats Mikkel Rummelhoff
 * @package   Imager
 * @since     2.1.2
 */
class ImgixService extends Component
{
    /**
     * @var string
     */
    public const PURGE_ENDPOINT = 'https://api.imgix.com/api/v1/purge';

    /**
     * @var bool If purging is enabled or not
     */
    protected static ?bool $canPurge = null;

    /**
     * Purging is possible if there's an `imgixConfig` map, and all sources/profiles have an API key set
     * Used for determining if the ImgixPurgeElementAction element action and various related event handlers should be bootstrapped or not
     */
    public static function getCanPurge(): bool
    {
        if (self::$canPurge === null) {
            $config = ImagerService::getConfig();

            // No Imgix config, no purging
            $imgixConfigArr = $config->getSetting('imgixConfig');
            if (!\is_array($imgixConfigArr) || empty($imgixConfigArr)) {
                self::$canPurge = false;
                return false;
            }

            // Make sure there's at least one profile that is not a web proxy and that is not excluded from purging
            $hasApiKey = (bool)$config->getSetting('imgixApiKey');
            $hasPurgableProfile = false;
            foreach ($imgixConfigArr as $imgixConfig) {
                $imgixConfig = new ImgixSettings($imgixConfig);
                $hasApiKey = $hasApiKey || (bool)$imgixConfig->apiKey;
                $hasPurgableProfile = $hasPurgableProfile || (!$imgixConfig->sourceIsWebProxy && !$imgixConfig->excludeFromPurge);
                if ($hasApiKey && $hasPurgableProfile) {
                    break;
                }
            }

            self::$canPurge = $hasApiKey && $hasPurgableProfile;
        }

        return self::$canPurge;
    }

    /**
     * @param string $url The base URL to the image you wish to purge (e.g. https://your-imgix-source.imgix.net/image.jpg)
     * @param string $apiKey Imgix API key
     */
    public function purgeUrlFromImgix(string $url, string $apiKey): void
    {
        try {
            $headers = [
                'Content-Type:application/json',
                'Authorization: Bearer ' . $apiKey,
            ];
            $payload = json_encode([
                'data' => [
                    'attributes' => [
                        'url' => $url,
                    ],
                    'type' => 'purges',
                ],
            ], JSON_THROW_ON_ERROR);

            $curl = curl_init(self::PURGE_ENDPOINT);

            $opts = [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => true,
            ];

            curl_setopt_array($curl, $opts);

            $response = curl_exec($curl);
            $curlErrorNo = curl_errno($curl);
            $curlError = curl_error($curl);
            $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($curlErrorNo !== 0) {
                $msg = Craft::t('imager-x', 'A cURL error “{curlErrorNo}” encountered while attempting to purge image “{url}”. The error was: “{curlError}”', ['url' => $url, 'curlErrorNo' => $curlErrorNo, 'curlError' => $curlError]);
                Craft::error($msg, __METHOD__);
            }

            if ($httpStatus !== 200) {
                $msg = Craft::t('imager-x', 'An error occured when trying to purge “{url}”, status was “{httpStatus}” and respose was “{response}”', ['url' => $url, 'httpStatus' => $httpStatus, 'response' => $response]);
                Craft::error($msg);
            }
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            // We don't continue to throw this error, since it could be caused by a duplicated request.
        }
    }

    /**
     * @param Asset $asset The Asset you wish to purge
     * @throws ImagerException
     */
    public function purgeAssetFromImgix(Asset $asset): void
    {
        $config = ImagerService::getConfig();

        $imgixApiKey = $config->getSetting('imgixApiKey');
        $imgixConfigArr = $config->getSetting('imgixConfig');

        if (empty($imgixConfigArr) || !\is_array($imgixConfigArr)) {
            $msg = Craft::t('imager-x', 'The `imgixConfig` config setting is missing, or is not correctly set up.');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        foreach ($imgixConfigArr as $imgixConfig) {
            $imgixConfig = new ImgixSettings($imgixConfig);
            if ($imgixConfig->sourceIsWebProxy || $imgixConfig->excludeFromPurge) {
                continue;
            }

            $apiKey = $imgixConfig->apiKey ?: $imgixApiKey;
            if (!$apiKey) {
                continue;
            }

            $domain = $imgixConfig->domain;

            try {
                // Build base URL for the image on Imgix
                $builder = new UrlBuilder($domain,
                    $imgixConfig->useHttps,
                    null,
                    false);

                $path = ImgixHelpers::getImgixFilePath($asset, $imgixConfig);
                $url = $builder->createURL($path);

                $this->purgeUrlFromImgix($url, $apiKey);
            } catch (\Throwable $throwable) {
                Craft::error($throwable->getMessage(), __METHOD__);
                throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }
    }
}
