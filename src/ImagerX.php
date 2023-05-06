<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterGqlDirectivesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\ReplaceAssetEvent;
use craft\gql\TypeManager;
use craft\models\ImageTransform;
use craft\services\Assets;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\Plugins;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use GraphQL\Type\Definition\Type;

use spacecatninja\imagerx\effects\AdaptiveBlurEffect;
use spacecatninja\imagerx\effects\AdaptiveSharpenEffect;
use spacecatninja\imagerx\effects\BlurEffect;
use spacecatninja\imagerx\effects\BrightnessEffect;
use spacecatninja\imagerx\effects\ClutEffect;
use spacecatninja\imagerx\effects\ColorBlendEffect;
use spacecatninja\imagerx\effects\ColorizeEffect;
use spacecatninja\imagerx\effects\ContrastEffect;
use spacecatninja\imagerx\effects\ContrastStretchEffect;
use spacecatninja\imagerx\effects\DespeckleEffect;
use spacecatninja\imagerx\effects\EnhanceEffect;
use spacecatninja\imagerx\effects\EqualizeEffect;
use spacecatninja\imagerx\effects\FloodFillPaintEffect;
use spacecatninja\imagerx\effects\GammaEffect;
use spacecatninja\imagerx\effects\GaussianBlurEffect;
use spacecatninja\imagerx\effects\GreyscaleEffect;
use spacecatninja\imagerx\effects\LevelsEffect;
use spacecatninja\imagerx\effects\ModulateEffect;
use spacecatninja\imagerx\effects\MotionBlurEffect;
use spacecatninja\imagerx\effects\NegativeEffect;
use spacecatninja\imagerx\effects\NormalizeEffect;
use spacecatninja\imagerx\effects\OilPaintEffect;
use spacecatninja\imagerx\effects\OpacityEffect;
use spacecatninja\imagerx\effects\PosterizeEffect;
use spacecatninja\imagerx\effects\QuantizeEffect;
use spacecatninja\imagerx\effects\RadialBlurEffect;
use spacecatninja\imagerx\effects\SepiaEffect;
use spacecatninja\imagerx\effects\SharpenEffect;
use spacecatninja\imagerx\effects\TintEffect;
use spacecatninja\imagerx\effects\TransparentPaintEffect;
use spacecatninja\imagerx\effects\UnsharpMaskEffect;

use spacecatninja\imagerx\elementactions\ClearTransformsElementAction;
use spacecatninja\imagerx\elementactions\GenerateTransformsAction;
use spacecatninja\imagerx\elementactions\ImgixPurgeElementAction;
use spacecatninja\imagerx\events\RegisterAdaptersEvent;
use spacecatninja\imagerx\events\RegisterEffectsEvent;
use spacecatninja\imagerx\events\RegisterExternalStoragesEvent;
use spacecatninja\imagerx\events\RegisterOptimizersEvent;
use spacecatninja\imagerx\events\RegisterTransformersEvent;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\externalstorage\AwsStorage;
use spacecatninja\imagerx\externalstorage\GcsStorage;
use spacecatninja\imagerx\gql\directives\ImagerSrcset;
use spacecatninja\imagerx\gql\directives\ImagerTransform;
use spacecatninja\imagerx\gql\interfaces\ImagerTransformedImageInterface;
use spacecatninja\imagerx\gql\queries\ImagerQuery;
use spacecatninja\imagerx\gql\resolvers\ImagerResolver;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\helpers\VersionHelpers;
use spacecatninja\imagerx\models\GenerateSettings;
use spacecatninja\imagerx\models\Settings;
use spacecatninja\imagerx\models\TransformedImageInterface;

use spacecatninja\imagerx\optimizers\GifsicleOptimizer;
use spacecatninja\imagerx\optimizers\JpegoptimOptimizer;
use spacecatninja\imagerx\optimizers\JpegtranOptimizer;
use spacecatninja\imagerx\optimizers\KrakenOptimizer;
use spacecatninja\imagerx\optimizers\MozjpegOptimizer;
use spacecatninja\imagerx\optimizers\OptipngOptimizer;
use spacecatninja\imagerx\optimizers\PngquantOptimizer;
use spacecatninja\imagerx\optimizers\TinypngOptimizer;

use spacecatninja\imagerx\services\GenerateService;
use spacecatninja\imagerx\services\ImagerColorService;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\services\ImgixService;
use spacecatninja\imagerx\services\OptimizerService;
use spacecatninja\imagerx\services\PlaceholderService;
use spacecatninja\imagerx\services\StorageService;

use spacecatninja\imagerx\transformers\CraftTransformer;
use spacecatninja\imagerx\transformers\ImgixTransformer;
use spacecatninja\imagerx\twigextensions\ImagerTwigExtension;
use spacecatninja\imagerx\utilities\ImagerUtility;
use spacecatninja\imagerx\variables\ImagerVariable;

use yii\base\Event;

/**
 * Class Imager
 *
 * @author    André Elvan
 * @package   Imager X
 * @since     2.0.0
 *
 * @property  ImagerService      $imager
 * @property  ImagerService      $imagerx
 * @property  ImagerColorService $color
 * @property  PlaceholderService $placeholder
 * @property  ImgixService       $imgix
 * @property  GenerateService    $generate
 * @property  StorageService     $storage
 * @property  OptimizerService   $optimizer
 */
class ImagerX extends Plugin
{
    // Events
    // =========================================================================
    /**
     * @var string
     */
    public const EVENT_REGISTER_TRANSFORMERS = 'imagerxRegisterTransformers';

    /**
     * @var string
     */
    public const EVENT_REGISTER_EXTERNAL_STORAGES = 'imagerxRegisterExternalStorages';

    /**
     * @var string
     */
    public const EVENT_REGISTER_EFFECTS = 'imagerxRegisterEffects';

    /**
     * @var string
     */
    public const EVENT_REGISTER_OPTIMIZERS = 'imagerxRegisterOptimizers';

    /**
     * @var string
     */
    public const EVENT_REGISTER_ADAPTERS = 'imagerxRegisterAdapters';

    // Static Properties
    // =========================================================================
    /**
     * @var string
     */
    public const EDITION_LITE = 'lite';

    /**
     * @var string
     */
    public const EDITION_PRO = 'pro';

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * ImagerX::$plugin
     *
     * @var ImagerX
     */
    public static ImagerX $plugin;

    // Public Methods
    // =========================================================================

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'imager' => ImagerService::class,
            'imagerx' => ImagerService::class,
            'placeholder' => PlaceholderService::class,
            'color' => ImagerColorService::class,
            'imgix' => ImgixService::class,
            'generate' => GenerateService::class,
            'storage' => StorageService::class,
            'optimizer' => OptimizerService::class,
        ]);

        // Load additional config files
        $this->loadAdditionalConfigs();

        // Create config model
        $config = ImagerService::getConfig();

        // Add our Twig extensions
        Craft::$app->view->registerTwigExtension(new ImagerTwigExtension());

        // Register our variables
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('imager', ImagerVariable::class);
                $variable->set('imagerx', ImagerVariable::class);
            }
        );

        // Register utility
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = ImagerUtility::class;
            }
        );

        // Event listener for clearing caches when an asset is replaced
        Event::on(Assets::class, Assets::EVENT_AFTER_REPLACE_ASSET,
            static function(ReplaceAssetEvent $event) use ($config) {
                ImagerX::$plugin->imagerx->removeTransformsForAsset($event->asset);

                // If Imgix purging is possible, do that too
                if ($config->imgixEnableAutoPurging && ImgixService::getCanPurge()) {
                    ImagerX::$plugin->imgix->purgeAssetFromImgix($event->asset);
                }
            }
        );

        // Register element actions
        $this->registerElementActions();

        // Register overrides for native transforms and thumbs
        $this->registerNativeOverrides();

        // Register GraphQL functionality
        $this->registerGraphQL();

        // Register generate listeners
        $this->registerGenerateListeners();

        // Register listener for clearing transforms on asset delete/move
        if ($config->removeTransformsOnAssetFileops) {
            $this->registerRemoveTransformsListeners();
        }

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,

            function() {
                // Register transformers
                $this->registerTransformers();

                // Register effects
                $this->registerEffects();

                // Register optimizers
                $this->registerOptimizers();

                // Register external storage options
                $this->registerExternalStorages();
                
                // Register adapters
                $this->registerAdapters();
            }
        );

        // Register console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'spacecatninja\imagerx\console\controllers';
        }
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Load additional config files
     */
    private function loadAdditionalConfigs(): void
    {
        // Named transforms
        $transformsConfig = Craft::$app->config->getConfigFromFile('imager-x-transforms');

        foreach ($transformsConfig as $transformname => $transformConfig) {
            ImagerService::registerNamedTransform($transformname, $transformConfig);
        }

        // Generate setup
        $generateConfig = Craft::$app->config->getConfigFromFile('imager-x-generate');
        ImagerService::$generateConfig = new GenerateSettings($generateConfig);
    }

    /**
     * Register element actions
     */
    private function registerElementActions(): void
    {
        // Register element action to assets for clearing transforms
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS,
            static function(RegisterElementActionsEvent $event) {
                $config = ImagerService::getConfig();

                $event->actions[] = ClearTransformsElementAction::class;

                if (ImagerX::getInstance()?->is(ImagerX::EDITION_PRO)) {
                    // If Imgix purging is possible, add element action for purging – unless the element action is disabled
                    if ($config->imgixEnablePurgeElementAction && ImgixService::getCanPurge()) {
                        $event->actions[] = ImgixPurgeElementAction::class;
                    }

                    // If any volume transforms were configured, add generate transforms element action
                    $generateVolumeConfig = ImagerService::$generateConfig['volumes'] ?? null;

                    if ($generateVolumeConfig) {
                        $event->actions[] = GenerateTransformsAction::class;
                    }
                }
            }
        );
    }

    /**
     * Register events for overriding native functionality
     */
    private function registerNativeOverrides(): void
    {
        $config = ImagerService::getConfig();

        // Event listener for overriding Craft's internal transform functionality
        if ($config->useForNativeTransforms) {
            Event::on(Asset::class, Asset::EVENT_DEFINE_URL,
                static function(DefineAssetUrlEvent $event) {
                    if ($event->transform !== null && $event->asset->kind === 'image' && \in_array(strtolower($event->asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                        try {
                            $transform = $event->transform;

                            // Transform is an ImageTransform
                            if ($transform instanceof ImageTransform) {
                                $transform = ImagerHelpers::normalizeAssetTransformToObject($transform);
                            }

                            // Transform is a named asset transform
                            if (is_string($transform)) {
                                $assetTransform = Craft::$app->getImageTransforms()->getTransformByHandle($transform);

                                if ($assetTransform) {
                                    $transform = ImagerHelpers::normalizeAssetTransformToObject($assetTransform);
                                } else {
                                    Craft::error('Unknown asset transform handle supplied to native transform', __METHOD__);
                                    $transform = [];
                                }
                            }

                            if (is_array($transform)) {
                                // We need to reset the transform here to avoid an infinite loop
                                $event->asset->setTransform(null);

                                // Do the transform
                                $transformedImage = ImagerX::$plugin->imagerx->transformImage($event->asset, $transform);

                                if ($transformedImage !== null) {
                                    $event->url = $transformedImage->getUrl();
                                }
                            }
                        } catch (ImagerException $e) {
                            return null;
                        }
                    }
                }
            );
        }

        // Event listener for overriding Craft's internal thumb url
        if ($config->useForCpThumbs) {
            Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL,
                static function(DefineAssetThumbUrlEvent $event) {
                    if ($event->asset->kind === 'image' && \in_array(strtolower($event->asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                        try {
                            /** @var TransformedImageInterface $transformedImage */
                            $transformedImage = ImagerX::$plugin->imagerx->transformImage($event->asset, ['width' => $event->width, 'height' => $event->height, 'mode' => 'fit']);

                            if ($transformedImage !== null) {
                                $event->url = $transformedImage->getUrl();
                            }
                        } catch (ImagerException) {
                            // just ignore
                        }
                    }
                }
            );
        }
    }

    /**
     * Register GraphQL event listeners
     */
    private function registerGraphQL(): void
    {
        if (self::getInstance()?->is(self::EDITION_PRO)) {
            // Register types
            Event::on(
                Gql::class,
                Gql::EVENT_REGISTER_GQL_TYPES,
                static function(RegisterGqlTypesEvent $event) {
                    Craft::debug(
                        'Gql::EVENT_REGISTER_GQL_TYPES',
                        __METHOD__
                    );
                    $event->types[] = ImagerTransformedImageInterface::class;
                }
            );

            // Register query
            Event::on(Gql::class,
                Gql::EVENT_REGISTER_GQL_QUERIES,
                static function(RegisterGqlQueriesEvent $event) {
                    $queries = ImagerQuery::getQueries();
                    foreach ($queries as $key => $value) {
                        $event->queries[$key] = $value;
                    }
                }
            );

            // Register directives
            Event::on(Gql::class,
                Gql::EVENT_REGISTER_GQL_DIRECTIVES,
                static function(RegisterGqlDirectivesEvent $event) {
                    $event->directives[] = ImagerTransform::class;
                    $event->directives[] = ImagerSrcset::class;
                }
            );

            if (VersionHelpers::craftIs('3.4')) {
                /*
                 * Adds queries to AssetInterface, see https://github.com/spacecatninja/craft-imager-x/pull/111
                 */
                Event::on(TypeManager::class,
                    TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
                    static function(DefineGqlTypeFieldsEvent $event) {
                        if ($event->typeName !== 'AssetInterface') {
                            return;
                        }

                        $event->fields['imagerTransform'] = [
                            'name' => 'imagerTransform',
                            'type' => Type::listOf(ImagerTransformedImageInterface::getType()),
                            'args' => [
                                'transform' => [
                                    'name' => 'transform',
                                    'type' => Type::string(),
                                    'description' => 'The handle of the named transform you want to generate.',
                                ],
                            ],
                            'resolve' => ImagerResolver::class.'::resolve',
                            'description' => 'Returns a list of images produced from the named Imager X transform.',
                        ];
                    }
                );
            }
        }
    }

    /**
     * Register event listeners for generate functionality
     */
    private function registerGenerateListeners(): void
    {
        if (self::getInstance()?->is(self::EDITION_PRO)) {
            if (ImagerService::$generateConfig === null) {
                return;
            }

            Event::on(Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                static function(ElementEvent $event) {
                    /** @var GenerateSettings $config */
                    $config = ImagerService::$generateConfig;

                    /** @var Element $element */
                    $element = $event->element;
                    
                    if ($element instanceof Asset && $element->getScenario() === Asset::SCENARIO_INDEX) {
                        return;
                    }

                    if (ImagerX::$plugin->generate->shouldGenerateByVolumes($element)) {
                        ImagerX::$plugin->generate->processAssetByVolumes($element);
                    }

                    if ($element->getIsRevision()) {
                        return;
                    }

                    if (!$config->generateForDrafts && $element->getIsDraft()) {
                        return;
                    }

                    if (ImagerX::$plugin->generate->shouldGenerateByElements($element)) {
                        ImagerX::$plugin->generate->processElementByElements($element);
                    }

                    if (ImagerX::$plugin->generate->shouldGenerateByFields($element)) {
                        ImagerX::$plugin->generate->processElementByFields($element);
                    }
                });
        }
    }

    /**
     * Register event listeners for removing transforms automatically.
     */
    private function registerRemoveTransformsListeners(): void
    {
        $config = ImagerService::getConfig();

        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT,
            static function(ElementEvent $event) use ($config) {
                if ($event->element instanceof Asset) {
                    /** @var Asset $asset */
                    $asset = $event->element;

                    ImagerX::$plugin->imagerx->removeTransformsForAsset($asset);

                    // If Imgix purging is possible, do that too
                    if ($config->imgixEnableAutoPurging && ImgixService::getCanPurge()) {
                        ImagerX::$plugin->imgix->purgeAssetFromImgix($asset);
                    }
                }
            }
        );

        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT,
            static function(ElementEvent $event) use ($config) {
                /** @var Element $element */
                $element = $event->element;

                if ($element instanceof Asset && $element->getScenario() === Asset::SCENARIO_FILEOPS) {
                    ImagerX::$plugin->imagerx->removeTransformsForAsset($element);

                    // If Imgix purging is possible, do that too
                    if ($config->imgixEnableAutoPurging && ImgixService::getCanPurge()) {
                        ImagerX::$plugin->imgix->purgeAssetFromImgix($element);
                    }
                }
            }
        );
    }

    /**
     * Register built-in transformers
     */
    private function registerTransformers(): void
    {
        if (self::getInstance()?->is(self::EDITION_PRO)) {
            $data = [
                'craft' => CraftTransformer::class,
                'imgix' => ImgixTransformer::class,
            ];

            $event = new RegisterTransformersEvent([
                'transformers' => $data,
            ]);

            $this->trigger(self::EVENT_REGISTER_TRANSFORMERS, $event);

            foreach ($event->transformers as $handle => $class) {
                ImagerService::registerTransformer($handle, $class);
            }
        } else {
            ImagerService::registerTransformer('craft', CraftTransformer::class);
        }
    }

    /**
     * Register effects
     */
    private function registerEffects(): void
    {
        $data = [
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

        $event = new RegisterEffectsEvent([
            'effects' => $data,
        ]);

        $this->trigger(self::EVENT_REGISTER_EFFECTS, $event);

        foreach ($event->effects as $handle => $class) {
            ImagerService::registerEffect($handle, $class);
        }
    }

    /**
     * Register optimizers
     */
    private function registerOptimizers(): void
    {
        $data = [
            'jpegoptim' => JpegoptimOptimizer::class,
            'jpegtran' => JpegtranOptimizer::class,
            'mozjpeg' => MozjpegOptimizer::class,
            'optipng' => OptipngOptimizer::class,
            'pngquant' => PngquantOptimizer::class,
            'gifsicle' => GifsicleOptimizer::class,
            'tinypng' => TinypngOptimizer::class,
            'kraken' => KrakenOptimizer::class,
        ];

        $event = new RegisterOptimizersEvent([
            'optimizers' => $data,
        ]);

        $this->trigger(self::EVENT_REGISTER_OPTIMIZERS, $event);

        foreach ($event->optimizers as $handle => $class) {
            ImagerService::registerOptimizer($handle, $class);
        }
    }

    /**
     * Register external storage options
     */
    private function registerExternalStorages(): void
    {
        if (self::getInstance()?->is(self::EDITION_PRO)) {
            $data = [
                'aws' => AwsStorage::class,
                'gcs' => GcsStorage::class,
            ];

            $event = new RegisterExternalStoragesEvent([
                'storages' => $data,
            ]);

            $this->trigger(self::EVENT_REGISTER_EXTERNAL_STORAGES, $event);

            foreach ($event->storages as $handle => $class) {
                ImagerService::registerExternalStorage($handle, $class);
            }
        }
    }
    
    /**
     * Register adapters
     */
    private function registerAdapters(): void
    {
        $data = [];

        $event = new RegisterAdaptersEvent([
            'adapters' => $data,
        ]);

        $this->trigger(self::EVENT_REGISTER_ADAPTERS, $event);

        foreach ($event->adapters as $handle => $class) {
            ImagerService::registerAdapter($handle, $class);
        }
    }

    
}
