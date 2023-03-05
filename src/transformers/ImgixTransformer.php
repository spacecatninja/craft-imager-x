<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\transformers;

use Craft;

use craft\base\Component;
use craft\elements\Asset;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImgixHelpers;
use spacecatninja\imagerx\models\ImgixSettings;
use spacecatninja\imagerx\models\ImgixTransformedImageModel;
use spacecatninja\imagerx\services\ImagerService;

/**
 * ImgixTransformer
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class ImgixTransformer extends Component implements TransformerInterface
{
    public static array $transformKeyTranslate = [
        'width' => 'w',
        'height' => 'h',
        'format' => 'fm',
        'bgColor' => 'bg',
    ];
    
    /**
     * Main transform method
     *
     *
     * @throws ImagerException
     */
    public function transform(Asset|string $image, array $transforms): ?array
    {
        $transformedImages = [];

        foreach ($transforms as $transform) {
            $transformedImages[] = $this->getTransformedImage($image, $transform);
        }

        return $transformedImages;
    }

    /**
     * Transform one image
     *
     *
     *
     * @throws ImagerException
     */
    private function getTransformedImage(Asset|string $image, array $transform): ImgixTransformedImageModel
    {
        $config = ImagerService::getConfig();

        $profile = $config->getSetting('imgixProfile', $transform);
        $imgixConfigArr = $config->getSetting('imgixConfig', $transform);

        if (!isset($imgixConfigArr[$profile])) {
            $msg = 'Imgix profile “' . $profile . '” does not exist.';
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        $imgixConfig = new ImgixSettings($imgixConfigArr[$profile]);
        
        if (($imgixConfig->sourceIsWebProxy) && ($imgixConfig->signKey === '')) {
            $msg = Craft::t('imager-x', 'Your Imgix source is a web proxy according to config setting “sourceIsWebProxy”, but no sign key/security token has been given in imgix config setting “signKey”. You`ll find this in your Imgix source details page.');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        try {
            $builder = ImgixHelpers::getBuilder($imgixConfig);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            Craft::error($invalidArgumentException->getMessage(), __METHOD__);
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        $params = $this->createParams($transform, $image, $imgixConfig);
        $path = ImgixHelpers::getImgixFilePath($image, $imgixConfig);
        $url = $builder->createURL($path, $params);

        return new ImgixTransformedImageModel($url, $image, $params, $imgixConfig);
    }

    /**
     * Create Imgix transform params
     *
     *
     */
    private function createParams(array $transform, Asset|string $image, ImgixSettings $imgixConfig): array
    {
        $config = ImagerService::getConfig();

        $r = [];
        
        if (isset($transform['imgixParams'])) {
            $transform['transformerParams'] = array_merge($transform['transformerParams'] ?? [], $transform['imgixParams']);
            unset($transform['imgixParams']);
            
            \Craft::$app->deprecator->log(__METHOD__, 'The `imgixParams` transform parameter has been deprecated, use `transformerParams` instead.');
        }
        
        // Merge in default values
        if (\is_array($imgixConfig->defaultParams)) {
            $transform['transformerParams'] = array_merge($imgixConfig->defaultParams, $transform['transformerParams'] ?? []);
        }
        
        // Directly translate some keys
        foreach (self::$transformKeyTranslate as $key => $val) {
            if (isset($transform[$key])) {
                $r[$val] = $transform[$key];
                unset($transform[$key]);
            }
        }

        // Set quality
        if (
            !isset($transform['q'])
            && !$this->transformHasAutoCompressionEnabled($transform)
        ) {
            if (isset($r['fm'])) {
                $r['q'] = $this->getQualityFromExtension($r['fm'], $transform);
            } else {
                $ext = null;

                if ($image instanceof Asset) {
                    $ext = $image->getExtension();
                }

                if (\is_string($image)) {
                    $pathParts = pathinfo($image);
                    $ext = $pathParts['extension'] ?? '';
                }

                $r['q'] = $this->getQualityFromExtension($ext, $transform);
            }
        }

        // unset quality
        unset(
            $transform['jpegQuality'],
            $transform['pngCompressionLevel'],
            $transform['webpQuality']
        );

        // Deal with resize mode, called fit in Imgix
        if (!isset($transform['fit'])) {
            if (isset($transform['mode'])) {
                $mode = $transform['mode'];
                switch ($mode) {
                    case 'fit':
                        $r['fit'] = 'clip';
                        break;
                    case 'stretch':
                        $r['fit'] = 'scale';
                        break;
                    case 'croponly':
                        // todo : Not really supported, need to figure out if there's a workaround
                        $r['fit'] = 'clip';
                        break;
                    case 'letterbox':
                        $r['fit'] = 'fill';
                        $letterboxDef = $config->getSetting('letterbox', $transform);
                        $r['bg'] = $this->getLetterboxColor($letterboxDef);
                        unset($transform['letterbox']);
                        break;
                    default:
                        $r['fit'] = 'crop';
                        break;
                }
            } elseif (isset($r['w'], $r['h'])) {
                $r['fit'] = 'crop';
            } else {
                $r['fit'] = 'clip';
            }
        } else {
            $r['fit'] = $transform['fit'];
        }
        
        if (!isset($r['w'], $r['h']) && $r['fit'] === 'crop') {
            $r['fit'] = 'clip';
        }

        // If fit is crop, and crop isn't specified, use position as focal point.
        if ($r['fit'] === 'crop' && !isset($transform['crop'])) {
            $position = $config->getSetting('position', $transform);
            [$left, $top] = explode(' ', $position);
            $r['crop'] = 'focalpoint';
            $r['fp-x'] = ((float)$left) / 100;
            $r['fp-y'] = ((float)$top) / 100;

            if (isset($transform['cropZoom'])) {
                $r['fp-z'] = $transform['cropZoom'];
            }
        }
        
        // unset everything that has to do with mode and crop
        unset(
            $transform['mode'],
            $transform['fit'],
            $transform['cropZoom'],
            $transform['position'],
            $transform['letterbox']
        );

        
        // Add any explicitly set Imgix params
        if (isset($transform['transformerParams'])) {
            foreach ($transform['transformerParams'] as $key => $val) {
                $r[$key] = $val;
            }

            unset($transform['transformerParams']);
        }

        // Assume that the reset of the values left in the transform object is Imgix specific
        foreach ($transform as $key => $val) {
            $r[$key] = $val;
        }

        // If allowUpscale is disabled, use max-w/-h instead of w/h
        if (isset($r['fit']) && !$config->getSetting('allowUpscale', $transform)) {
            if ($r['fit'] === 'crop' && $image instanceof Asset && ($r['w'] > $image->getWidth() || $r['h'] > $image->getHeight())) {
                $sourceRatio = $image->getHeight() / $image->getWidth();
                $transformRatio = (int)$r['h'] / (int)$r['w'];
                
                if ($sourceRatio > $transformRatio) {
                    $r['w'] = $image->getWidth();
                    $r['h'] = ceil($image->getWidth() * $transformRatio);
                } else {
                    $r['h'] = $image->getHeight();
                    $r['w'] = ceil($image->getHeight() / $transformRatio);
                }
            }
            
            if ($r['fit'] === 'clip') {
                $r['fit'] = 'max';
            }
        }

        // Unset stuff that's not supported by Imgix and has not yet been dealt with
        unset(
            $r['effects'],
            $r['preeffects'],
            $r['allowUpscale'],
            $r['cacheEnabled'],
            $r['cacheDuration'],
            $r['interlace'],
            $r['resizeFilter'],
            $r['smartResizeEnabled'],
            $r['removeMetadata'],
            $r['hashFilename'],
            $r['hashRemoteUrl'],
            $r['watermark'],
            $r['customEncoderOptions']
        );

        // Remove any empty values in return array, since these will result in
        // an empty query string value that will give us trouble with Facebook (!).
        foreach ($r as $key => $val) {
            if ($val === '') {
                unset($r[$key]);
            }
        }

        return $r;
    }

    /**
     * Check if transform has auto compression enabled
     *
     *
     */
    private function transformHasAutoCompressionEnabled(array $transform): bool
    {
        return isset($transform['auto']) && str_contains($transform['auto'], 'compress');
    }

    /**
     * Gets letterbox params string
     *
     * @param $letterboxDef
     */
    private function getLetterboxColor($letterboxDef): string
    {
        $color = $letterboxDef['color'];
        $opacity = $letterboxDef['opacity'];

        $color = str_replace('#', '', $color);

        if (\strlen($color) === 3) {
            $opacity = dechex($opacity * 15);

            return $opacity . $color;
        }

        if (\strlen($color) === 6) {
            $opacity = dechex($opacity * 255);
            $val = $opacity . $color;
            if (\strlen($val) === 7) {
                $val = '0' . $val;
            }

            return $val;
        }

        if (\strlen($color) === 4 || \strlen($color) === 8) { // assume color already is 4 or 8 digit rgba.
            return $color;
        }

        return '0fff';
    }

    /**
     * Gets the quality setting based on the extension.
     *
     * @param array|null $transform
     *
     */
    private function getQualityFromExtension(string $ext, array $transform = null): string
    {
        $config = ImagerService::getConfig();

        if ($ext == 'png') {
            $pngCompression = $config->getSetting('pngCompressionLevel', $transform);
            return max(100 - ($pngCompression * 10), 1);
        } elseif ($ext == 'webp') {
            return $config->getSetting('webpQuality', $transform);
        }

        return $config->getSetting('jpegQuality', $transform);
    }
}
