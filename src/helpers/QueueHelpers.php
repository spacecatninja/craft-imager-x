<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use craft\helpers\UrlHelper;

class QueueHelpers
{
    /**
     * Trigger queue/run immediately
     */
    public static function triggerQueueNow(): void
    {
        $url = UrlHelper::actionUrl('queue/run');

        if (\function_exists('curl_init')) {
            $ch = curl_init($url);

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => false,
                CURLOPT_NOSIGNAL => true,
            ];

            if (\defined('CURLOPT_TIMEOUT_MS')) {
                $options[CURLOPT_TIMEOUT_MS] = 500;
            } else {
                $options[CURLOPT_TIMEOUT] = 1;
            }

            curl_setopt_array($ch, $options);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
