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

use craft\base\Component;
use Imagine\Exception\RuntimeException;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Imagick\Imagine;

use kornrunner\Blurhash\Blurhash;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\lib\Potracio;
use spacecatninja\imagerx\models\LocalSourceImageModel;

/**
 * PlaceholderService Service
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class PlaceholderService extends Component
{
    private array $defaults = [
        'type' => 'svg',
        'width' => 1,
        'height' => 1,
        'color' => null,
        'source' => null,
        'fgColor' => null,
        'size' => 1,
        'silhouetteType' => '',
    ];

    /**
     * Main public placeholder method.
     *
     * @param array|null $config
     *
     * @throws ImagerException
     */
    public function placeholder(array $config = null): string
    {
        $config = array_merge($this->defaults, $config ?? []);

        return match ($config['type']) {
            'svg' => $this->placeholderSVG($config),
            'gif' => $this->placeholderGIF($config),
            'silhouette' => $this->placeholderSilhuette($config),
            'blurhash' => $this->placeholderBlurhash($config),
            default => '',
        };
    }

    /**
     * Returns a SVG placeholder
     *
     * @param $config
     */
    private function placeholderSVG($config): string
    {
        $width = $config['width'];
        $height = $config['height'];
        $color = $config['color'] ?? 'transparent';

        return 'data:image/svg+xml;charset=utf-8,'.rawurlencode(sprintf('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'%s\' height=\'%s\' style=\'background:%s\'/>', $width, $height, $color));
    }

    /**
     * Returns a GIF placeholder.
     *
     * @param $config
     */
    private function placeholderGIF($config): string
    {
        $width = $config['width'];
        $height = $config['height'];
        $color = $config['color'] ?? 'transparent';

        if ($width === 1 && $height === 1 && $color === 'transparent') {
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }

        $imagineInstance = $this->createImagineInstance();

        if ($imagineInstance === null) {
            return '';
        }

        $palette = new RGB();

        $col = $color === 'transparent' ? $palette->color('#000000', 0) : $palette->color($color);

        $image = $imagineInstance->create(new Box($width, $height), $col);
        $data = $image->get('gif');

        return 'data:image/gif;base64,'.base64_encode($data);
    }

    /**
     * Returns a silhouette placeholder.
     *
     * @param $config
     *
     * @throws ImagerException
     */
    private function placeholderSilhuette($config): string
    {
        $source = $config['source'] ?? null;
        $size = $config['size'];
        $color = $config['color'] ?? '#fefefe';
        $fgColor = $config['fgColor'] ?? '#e0e0e0';
        $silhouetteType = $config['silhouetteType'];

        if ($source === null) {
            throw new ImagerException('Placeholder of type "silhouette" needs a source image.');
        }

        try {
            $sourceModel = new LocalSourceImageModel($source);
            $sourceModel->getLocalCopy();
        } catch (ImagerException $imagerException) {
            return '';
        }

        try {
            $tracer = new Potracio();
            $tracer->loadImageFromFile($sourceModel->getFilePath());
            $tracer->process();
            $data = $tracer->getSVG($size, $silhouetteType, $color, $fgColor);
        } catch (\Throwable $throwable) {
            \Craft::error($throwable->getMessage(), __METHOD__);

            return '';
        }

        return 'data:image/svg+xml;charset=utf-8,'.rawurlencode($data);
    }

    /**
     * Returns a blurhash placeholder.
     *
     * @param $config
     *
     * @throws ImagerException
     */
    private function placeholderBlurhash($config): string
    {
        $hash = $config['hash'] ?? null;
        $format = $config['format'] ?? 'png';
        $width = $config['width'] ?? 4;
        $height = $config['height'] ?? 3;
        $base64 = $config['base64'] ?? false;

        if ($hash === null) {
            throw new ImagerException('Placeholder of type "blurhash" needs a hash string.');
        }

        $hash64 = base64_encode($hash);
        $key = "imager-x-blurhash-placeholder-$hash64-$format-$width-$height";
        $cache = \Craft::$app->getCache();
        
        if (!$cache) {
            \Craft::error('Cache component not found when trying to create blurhash placeholder');
            return '';
        }
        
        $rawImageBytes = $cache->getOrSet($key, static function() use ($hash, $format, $width, $height) {
            $data = Blurhash::decode($hash, $width, $height);

            $image = imagecreatetruecolor($width, $height);

            for ($i = 0; $i < $width; $i++) {
                for ($j = 0; $j < $height; $j++) {
                    imagesetpixel($image, $i, $j, imagecolorallocate($image, $data[$j][$i][0], $data[$j][$i][1], $data[$j][$i][2]));
                }
            }

            ob_start();
            
            match ($format) {
                'gif' => imagegif($image),
                'jpg' => imagejpeg($image, null, 100),
                default => imagepng($image),
            };
            
            return ob_get_clean();
        });
        
        $base64String = base64_encode($rawImageBytes);
        
        if ($base64) {
            return $base64String;
        }
        
        return "data:image/$format;base64,$base64String";
    }

    /**
     * Creates the Imagine instance depending on the chosen image driver.
     */
    private function createImagineInstance(): Imagine|\Imagine\Gd\Imagine|null
    {
        $imageDriver = ImagerService::$imageDriver;

        try {
            if ($imageDriver === 'gd') {
                return new \Imagine\Gd\Imagine();
            }

            if ($imageDriver === 'imagick') {
                return new Imagine();
            }
        } catch (RuntimeException) {
            // just ignore for now
        }

        return null;
    }
}
