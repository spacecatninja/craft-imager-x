<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Effects;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Support\HandleRegistry;

/**
 * Registry of the effects that can be used in the `effects` and `preEffects` transform parameters, keyed by handle.
 *
 * @extends HandleRegistry<ImagerEffectsInterface>
 */
#[Singleton]
class Effects extends HandleRegistry
{
    protected const ?string CONTRACT = ImagerEffectsInterface::class;

    protected const array DEFAULT_TYPES = [
        // Both for GD and Imagick
        'grayscale' => GreyscaleEffect::class,
        'greyscale' => GreyscaleEffect::class,
        'negative' => NegativeEffect::class,
        'blur' => BlurEffect::class,
        'sharpen' => SharpenEffect::class,
        'gamma' => GammaEffect::class,
        'colorize' => ColorizeEffect::class,
        'brightness' => BrightnessEffect::class,

        // Imagick only
        'colorblend' => ColorBlendEffect::class,
        'sepia' => SepiaEffect::class,
        'tint' => TintEffect::class,
        'contrast' => ContrastEffect::class,
        'modulate' => ModulateEffect::class,
        'normalize' => NormalizeEffect::class,
        'contraststretch' => ContrastStretchEffect::class,
        'posterize' => PosterizeEffect::class,
        'unsharpmask' => UnsharpMaskEffect::class,
        'clut' => ClutEffect::class,
        'levels' => LevelsEffect::class,
        'quantize' => QuantizeEffect::class,
        'gaussianblur' => GaussianBlurEffect::class,
        'motionblur' => MotionBlurEffect::class,
        'radialblur' => RadialBlurEffect::class,
        'oilpaint' => OilPaintEffect::class,
        'adaptiveblur' => AdaptiveBlurEffect::class,
        'adaptivesharpen' => AdaptiveSharpenEffect::class,
        'despeckle' => DespeckleEffect::class,
        'enhance' => EnhanceEffect::class,
        'equalize' => EqualizeEffect::class,
        'opacity' => OpacityEffect::class,
        'floodfillpaint' => FloodFillPaintEffect::class,
        'transparentpaint' => TransparentPaintEffect::class,
    ];
}
