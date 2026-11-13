# Upgrading to Imager X 7 (Craft 6)

This guide lists what changed in Imager X 7 and what you need to update in your project, templates and custom code. It applies when upgrading from Imager X 6 (Craft 5), and from the 7.0.0 alpha and beta releases, which ran on Craft 6 through the Yii 2 adapter.

Templates that only use `craft.imagerx` / `craft.imager` and the `srcset` filter, and projects that only configure Imager X through its config files, are not affected by the class changes below. They mostly affect plugins and modules that extend Imager X: transformers, external storages, effects, optimizers and adapters.

## Requirements

- Craft CMS 6.0.0 or later
- PHP 8.5 or later

## Upgrade steps

1. Upgrade your project to Craft 6 first.
2. Update the plugin: `composer require spacecatninja/imager-x:^7.0.0`.
3. Review the breaking changes below and update your code.

## Breaking changes

### Registering transformers, effects, optimizers, external storages and adapters

The `EVENT_REGISTER_*` events on the plugin class were removed. Extensions now register their classes in a registry, resolved from the container. Registries are singletons, so it doesn't matter whether your plugin boots before or after Imager X.

| Before | After |
| --- | --- |
| `ImagerX::EVENT_REGISTER_TRANSFORMERS` | `SpaceCatNinja\ImagerX\Transformers\Transformers` |
| `ImagerX::EVENT_REGISTER_EFFECTS` | `SpaceCatNinja\ImagerX\Effects\Effects` |
| `ImagerX::EVENT_REGISTER_OPTIMIZERS` | `SpaceCatNinja\ImagerX\Optimizers\Optimizers` |
| `ImagerX::EVENT_REGISTER_EXTERNAL_STORAGES` | `SpaceCatNinja\ImagerX\ExternalStorage\ExternalStorages` |
| `ImagerX::EVENT_REGISTER_ADAPTERS` | `SpaceCatNinja\ImagerX\Adapters\Adapters` |

Before:

```php
use spacecatninja\imagerx\events\RegisterTransformersEvent;
use spacecatninja\imagerx\ImagerX;
use yii\base\Event;

Event::on(ImagerX::class, ImagerX::EVENT_REGISTER_TRANSFORMERS,
    function (RegisterTransformersEvent $event) {
        $event->transformers['imgix'] = Imgix::class;
    }
);
```

After, in your plugin's or service provider's `boot()`:

```php
use SpaceCatNinja\ImagerX\Transformers\Transformers;

public function boot(Transformers $transformers): void
{
    $transformers->register('imgix', Imgix::class);
}
```

Each registry has `register($handle, $class)`, `remove($handle)`, `has($handle)`, `get($handle)` and `all()`. `register()` throws an `InvalidArgumentException` if the class doesn't implement the registry's interface (`TransformerInterface`, `ImagerEffectsInterface`, `ImagerOptimizeInterface`, `ImagerStorageInterface` or `ImagerAdapterInterface`). Adapters are registered by file extension.

The `RegisterTransformersEvent`, `RegisterEffectsEvent`, `RegisterOptimizersEvent`, `RegisterExternalStoragesEvent` and `RegisterAdaptersEvent` classes were removed, along with these static members of `ImagerService`:

| Before | After |
| --- | --- |
| `ImagerService::registerTransformer($handle, $class)` | `app(Transformers::class)->register($handle, $class)` |
| `ImagerService::registerEffect($handle, $class)` | `app(Effects::class)->register($handle, $class)` |
| `ImagerService::registerOptimizer($handle, $class)` | `app(Optimizers::class)->register($handle, $class)` |
| `ImagerService::registerExternalStorage($handle, $class)` | `app(ExternalStorages::class)->register($handle, $class)` |
| `ImagerService::registerAdapter($handle, $class)` | `app(Adapters::class)->register($handle, $class)` |
| `ImagerService::$transformers`, `$effects`, `$optimizers`, `$storage`, `$adapters` | `app(Transformers::class)->all()` etc. |

As before, transformers other than `craft`, and external storages, are only used in Imager X Pro.

### Services

Services are no longer components on the plugin. `ImagerX::$plugin` was removed, and the plugin no longer has `imager`, `imagerx`, `color`, `placeholder`, `generate`, `storage` and `optimizer` properties. Inject the service class, or resolve it from the container:

| Before | After |
| --- | --- |
| `ImagerX::$plugin->imagerx`, `ImagerX::getInstance()->imager` | `app(\SpaceCatNinja\ImagerX\Services\ImagerService::class)` |
| `ImagerX::$plugin->color` | `app(\SpaceCatNinja\ImagerX\Services\ImagerColorService::class)` |
| `ImagerX::$plugin->placeholder` | `app(\SpaceCatNinja\ImagerX\Services\PlaceholderService::class)` |
| `ImagerX::$plugin->generate` | `app(\SpaceCatNinja\ImagerX\Services\GenerateService::class)` |
| `ImagerX::$plugin->storage` | `app(\SpaceCatNinja\ImagerX\Services\StorageService::class)` |
| `ImagerX::$plugin->optimizer` | `app(\SpaceCatNinja\ImagerX\Services\OptimizerService::class)` |
| `ImagerX::$plugin->getSettings()` | `app(\SpaceCatNinja\ImagerX\Settings::class)` |
| `ImagerX::getInstance()->is(ImagerX::EDITION_PRO)` | `app(\SpaceCatNinja\ImagerX\ImagerX::class)->isPro()` |

### Console commands

The console commands are now Artisan commands. The Craft 5 names still work with `php craft`, so existing cron jobs and deploy scripts don't need to change.

| Craft 5 | Craft 6 |
| --- | --- |
| `php craft imager-x/generate` | `php craft imager-x:generate` |
| `php craft imager-x/clean` | `php craft imager-x:clean` |
| `php craft imager-x/clear-caches/all` | `php craft imager-x:clear-caches:all` |
| `php craft imager-x/clear-caches/transforms-cache` | `php craft imager-x:clear-caches:transforms-cache` |
| `php craft imager-x/clear-caches/runtime-cache` | `php craft imager-x:clear-caches:runtime-cache` |

- The option names are unchanged (`--volume`, `--folderId`, `--recursive`, `--runtimeCache`, ...). Boolean options can be passed on their own (`--force`) or with a value (`--force=1`, `--force=0`).
- The short option aliases (`-v`, `-fid`, `-r`, `-f`, `-t`, `-q`, `-l`, `-o`, `-e`, `-d`) were removed, because they clash with Artisan's own options (`-v` is verbosity, `-q` is quiet). Use the full option names.
- `imager-x:clean` asks for confirmation before deleting files when run interactively. Pass `--interactive=0` or `--no-interaction` to skip it.
- Commands exit with `1` on errors, and `2` when the volume or field is missing or unknown. They used to exit with Yii's `69` and `66`.

The 7.0.0 alpha and beta names (`php artisan craft:imager-x:generate`, ...) still work as aliases.

### Settings and config

Craft 6 reads plugin config files from `config/craft/`. Move your Imager X config files there:

| Before | After |
| --- | --- |
| `config/imager-x.php` | `config/craft/imager-x.php` |
| `config/imager-x-transforms.php` | `config/craft/imager-x-transforms.php` |
| `config/imager-x-generate.php` | `config/craft/imager-x-generate.php` |

The settings themselves are unchanged. Plugins and modules that read the settings can inject `SpaceCatNinja\ImagerX\Settings`, which is bound in the container.

### Routes and action URLs

The action URLs are unchanged, and all of them require a logged-in user:

- `actions/imager-x/cache/clear-transforms?key=...` and `actions/imager-x/cache/clear-remote-images?key=...` now return `{"success": true}` as JSON.
- `actions/imager-x/utility/generate-transforms` and `actions/imager-x/utility/clear-cache` are used by the Imager X utility. They require `POST` and access to the utility.

### Classes and namespaces

The root namespace changed from `spacecatninja\imagerx` to `SpaceCatNinja\ImagerX`, and classes moved to Laravel-style folders. Class names are unchanged, except where noted.

| Before | After |
| --- | --- |
| `spacecatninja\imagerx\ImagerX` | `SpaceCatNinja\ImagerX\ImagerX` |
| `spacecatninja\imagerx\models\Settings` | `SpaceCatNinja\ImagerX\Settings` |
| `spacecatninja\imagerx\models\*` | `SpaceCatNinja\ImagerX\Data\*` |
| `spacecatninja\imagerx\adapters\*` | `SpaceCatNinja\ImagerX\Adapters\*` |
| `spacecatninja\imagerx\effects\*` | `SpaceCatNinja\ImagerX\Effects\*` |
| `spacecatninja\imagerx\elementactions\*` | `SpaceCatNinja\ImagerX\ElementActions\*` |
| `spacecatninja\imagerx\events\*` | `SpaceCatNinja\ImagerX\Events\*` |
| `spacecatninja\imagerx\exceptions\*` | `SpaceCatNinja\ImagerX\Exceptions\*` |
| `spacecatninja\imagerx\externalstorage\*` | `SpaceCatNinja\ImagerX\ExternalStorage\*` |
| `spacecatninja\imagerx\gql\arguments\*` | `SpaceCatNinja\ImagerX\Gql\Arguments\*` |
| `spacecatninja\imagerx\gql\directives\*` | `SpaceCatNinja\ImagerX\Gql\Directives\*` |
| `spacecatninja\imagerx\gql\interfaces\*` | `SpaceCatNinja\ImagerX\Gql\Interfaces\*` |
| `spacecatninja\imagerx\gql\queries\*` | `SpaceCatNinja\ImagerX\Gql\Queries\*` |
| `spacecatninja\imagerx\gql\resolvers\*` | `SpaceCatNinja\ImagerX\Gql\Resolvers\*` |
| `spacecatninja\imagerx\gql\types\*` | `SpaceCatNinja\ImagerX\Gql\Types\*` |
| `spacecatninja\imagerx\gql\types\generators\*` | `SpaceCatNinja\ImagerX\Gql\Types\Generators\*` |
| `spacecatninja\imagerx\helpers\*` | `SpaceCatNinja\ImagerX\Support\*` |
| `spacecatninja\imagerx\lib\*` | `SpaceCatNinja\ImagerX\Support\*` |
| `spacecatninja\imagerx\jobs\*` | `SpaceCatNinja\ImagerX\Jobs\*` |
| `spacecatninja\imagerx\optimizers\*` | `SpaceCatNinja\ImagerX\Optimizers\*` |
| `spacecatninja\imagerx\services\*` | `SpaceCatNinja\ImagerX\Services\*` |
| `spacecatninja\imagerx\traits\*` | `SpaceCatNinja\ImagerX\Concerns\*` |
| `spacecatninja\imagerx\transformers\*` | `SpaceCatNinja\ImagerX\Transformers\*` |
| `spacecatninja\imagerx\twigextensions\*` | `SpaceCatNinja\ImagerX\Twig\*` |
| `spacecatninja\imagerx\utilities\*` | `SpaceCatNinja\ImagerX\Utilities\*` |
| `spacecatninja\imagerx\variables\*` | `SpaceCatNinja\ImagerX\Twig\*` |

The classes most often used by extensions:

| Before | After |
| --- | --- |
| `spacecatninja\imagerx\transformers\TransformerInterface` | `SpaceCatNinja\ImagerX\Transformers\TransformerInterface` |
| `spacecatninja\imagerx\externalstorage\ImagerStorageInterface` | `SpaceCatNinja\ImagerX\ExternalStorage\ImagerStorageInterface` |
| `spacecatninja\imagerx\effects\ImagerEffectsInterface` | `SpaceCatNinja\ImagerX\Effects\ImagerEffectsInterface` |
| `spacecatninja\imagerx\optimizers\ImagerOptimizeInterface` | `SpaceCatNinja\ImagerX\Optimizers\ImagerOptimizeInterface` |
| `spacecatninja\imagerx\adapters\ImagerAdapterInterface` | `SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface` |
| `spacecatninja\imagerx\models\TransformedImageInterface` | `SpaceCatNinja\ImagerX\Data\TransformedImageInterface` |
| `spacecatninja\imagerx\models\BaseTransformedImageModel` | `SpaceCatNinja\ImagerX\Data\BaseTransformedImageModel` |
| `spacecatninja\imagerx\models\LocalSourceImageModel` | `SpaceCatNinja\ImagerX\Data\LocalSourceImageModel` |
| `spacecatninja\imagerx\models\ConfigModel` | `SpaceCatNinja\ImagerX\Data\ConfigModel` |
| `spacecatninja\imagerx\exceptions\ImagerException` | `SpaceCatNinja\ImagerX\Exceptions\ImagerException` |
| `spacecatninja\imagerx\helpers\ImagerHelpers` | `SpaceCatNinja\ImagerX\Support\ImagerHelpers` |
| `spacecatninja\imagerx\services\ImagerService` | `SpaceCatNinja\ImagerX\Services\ImagerService` |

## Need help?

Report issues at https://github.com/spacecatninja/craft-imager-x/issues.
