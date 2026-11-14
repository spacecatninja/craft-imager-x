Imager X for Craft 6
====================

Imager X is a Craft plugin for doing image transforms, optimizations and manipulations.

Installation
------------
To install Imager X, follow these steps:

1. Install with composer via `composer require spacecatninja/imager-x` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings > Plugins, or from the command line via `php craft plugin:install imager-x`.
3. [Configure it](http://imager-x.spacecat.ninja/configuration.html). On Craft 6, the config files go in `config/craft/`: `config/craft/imager-x.php`, `config/craft/imager-x-transforms.php` and `config/craft/imager-x-generate.php`.

Imager X 7 requires Craft 6.0.0 or higher, and PHP 8.5 or higher. Upgrading from Imager X 6, or from a plugin that extends Imager X? See [UPGRADE.md](UPGRADE.md).

Console commands
----------------
The Craft 5 names, like `php craft imager-x/generate`, still work.

- `php craft imager-x:generate` generates transforms by volume, folder or field (Imager X Pro).
- `php craft imager-x:clean` deletes expired transforms, or expired files in the runtime cache with `--runtimeCache`.
- `php craft imager-x:clear-caches:all`, `imager-x:clear-caches:transforms-cache` and `imager-x:clear-caches:runtime-cache` clear the caches.

Run `php craft help <command>` for the options.

Documentation
-------------
Complete documentation can be found at [imager-x.spacecat.ninja](http://imager-x.spacecat.ninja/).


Price, license and support
--------------------------
This is a commercial plugin, available in [the Craft plugin store](https://plugins.craftcms.com/imager-x?craft5). 


Changelog
---------
See [CHANGELOG.MD](https://raw.githubusercontent.com/spacecatninja/craft-imager-x/master/CHANGELOG.md).
