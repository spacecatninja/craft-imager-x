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
