<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\services;

use spacecatninja\imagerx\lib\Potracio;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use craft\base\Component;

use spacecatninja\imagerx\exceptions\ImagerException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\Box;

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

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background:$color'/>");
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
        
        $palette = new \Imagine\Image\Palette\RGB();
        
        $col = $color==='transparent' ? $palette->color('#000000', 0) : $palette->color($color);
        
        $image = $imagineInstance->create(new Box($width, $height), $col);
        $data = $image->get('gif');
        
        
        return 'data:image/gif;base64,' . base64_encode($data);
    }

    /**
     * Returns a silhouette placeholder.
     *
     * @param $config
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
        } catch (ImagerException $e) {
            return '';
        }
        
        try {
            $tracer = new Potracio();
            $tracer->loadImageFromFile($sourceModel->getFilePath());
            $tracer->process();
            $data = $tracer->getSVG($size, $silhouetteType, $color, $fgColor);
        } catch (\Throwable $e) {
            \Craft::error($e->getMessage(), __METHOD__);
            return '';
        }
        
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($data);
    }
    
    /**
     * Creates the Imagine instance depending on the chosen image driver.
     */
    private function createImagineInstance(): \Imagine\Imagick\Imagine|\Imagine\Gd\Imagine|null
    {
        $imageDriver = ImagerService::$imageDriver;
        
        try {
            if ($imageDriver === 'gd') {
                return new \Imagine\Gd\Imagine();
            }

            if ($imageDriver === 'imagick') {
                return new \Imagine\Imagick\Imagine();
            }
        } catch (RuntimeException) {
            // just ignore for now
        }

        return null;
    }
}
