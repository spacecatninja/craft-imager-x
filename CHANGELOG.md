# Imager X Changelog

## 6.3.0 - 2026-11-13

> [!IMPORTANT]  
> This release contains security fixes, and updating is recommended. It also adds some limits that are enabled by default, and changes some behaviour:
> - When querying by `id`, the `imagerTransform` GraphQL query now only returns assets from volumes that the schema can read. Schemas that use it need read access to the relevant volumes.
> - More IP ranges are now blocked for remote images, including `100.64.0.0/10` which is used by CGNAT and Tailscale. Enable `skipExternalUrlValidation` if you need to transform images from such hosts.
> - Transforms are now only saved with image file extensions. With Imagick, sources with other extensions are saved as `jpg` unless a `format` is set.
> - Remote downloads, image sizes and the `@imagerTransform` GraphQL directive are now limited by default, see the new config settings below. If you've raised `CURLOPT_TIMEOUT` in `curlOptions`, raise `remoteDownloadTimeout` too.
> - With `useForNativeTransforms` enabled, each size in Craft's `srcset` GraphQL field now counts towards `maxGraphqlComplexity`. Raise it if queries using `srcset` are rejected.

### Added
- Added the `remoteMaxFileSize` config setting (default 64 MB), which limits the size of remote image downloads.
- Added the `remoteDownloadTimeout` config setting (default 120 seconds), which limits the total time a remote image download can take, including redirects.
- Added the `maxTransformPixels` config setting (default 100 megapixels), which limits the size of images created while transforming. Images that are no larger than the source image are always allowed.
- Added the `graphqlMaxTransformSize` config setting (default 6000), which limits the width and height of transforms made with the `@imagerTransform` GraphQL directive.
- Added the `graphqlAllowCustomTransforms` config setting. When set to `false`, the `@imagerTransform` GraphQL directive can only be used with named transforms.

### Changed
- Changed the `imagerTransform` GraphQL query to apply the schema's volume scope when querying by `id`.
- Changed the `imagerTransform` GraphQL query to only accept local paths with a file extension in `safeFileFormats` in the `url` argument.
- Changed the `@imagerTransform` GraphQL directive to return `null` for invalid transform parameters.
- Changed transforms to only be saved with image file extensions. An unsupported `format` now throws an exception.
- Changed remote images to be checked after downloading. They must be a supported image type, or match the file extension of the URL, like a PDF from a `.pdf` URL.
- Changed `useForCpThumbs` and `useForNativeTransforms` to leave control panel thumbnails and previews of assets in volumes without public URLs to Craft.
- Changed Craft's `srcset` GraphQL field to count each size towards `maxGraphqlComplexity` when `useForNativeTransforms` is enabled, the same as Craft counts `url` with a transform.

### Fixed
- Fixed several security issues.
- Fixed `removeTransformsOnAssetFileops` not removing transforms when an asset was moved without being renamed.
- Fixed a potential infinite loop when using the `frames` parameter.
- Fixed remote images that couldn't be opened being kept in the source cache.
- Fixed partially downloaded remote images being left behind when a download failed.
- Fixed some path checks failing on Windows.

## 6.2.0 - 2026-11-04

> [!IMPORTANT]  
> Dominant colors are now calculated differently, and existing images may get a different dominant color after updating. Cached colors from earlier versions are not reused.

### Changed
- `getDominantColor()` now returns the most common color in the image. Previously it returned the first color of the Color Thief palette, which is ranked by how much of the color space a group of colors spans, not just by how many pixels it has. A small, varied detail could beat a large, flat area, so an image that is 90% red with a blue detail would get blue as its dominant color. This also applies to the `dominantColor` generate flag and the `dominantColor` return type of the `@imagerTransform` GraphQL directive.
- `getDominantColor()` and `getColorPalette()` no longer ignore white. Color Thief skips pixels where all channels are above 250, so a mostly white image got the color of whatever else was in it as its dominant color, and a white image got `null`.
- `getDominantColor()` now returns the actual average color of the matching pixels, so a white image gets `#ffffff` and a black image gets `#000000`, instead of `#fcfcfc` and `#040404`.
- `ksubileau/color-thief-php` 2.0 or later is now required.

### Fixed
- Fixed `getColorPalette()` throwing a `TypeError` instead of returning `null` when an image couldn't be analyzed.

## 6.1.3 - 2026-10-26

### Fixed
- Fixed transformed images having their file type detected with `finfo` on every render, also when the transform already existed. For JPEG, PNG, GIF, WebP and AVIF the mime type is now taken from the `getimagesize()` call that reads the image dimensions (fixes #315).

## 6.1.2 - 2026-10-13

### Fixed
- Fixed native crop transforms not using the asset's focal point when `useForNativeTransforms` is enabled, so they now match Craft (fixes #314).

## 6.1.1 - 2026-09-21

### Fixed
- Fixed date interval parsing for all durations (fixes #313).

## 6.1.0 - 2026-07-16

### Added
- Added a `--runtimeCache` option to the `imager-x/clean` console command for cleaning expired files in the runtime cache (fixes #311).
- Added an `--exclude` option to the `imager-x/clean` console command for excluding subpaths from cleaning. When cleaning the runtime cache, `temp`, `pdf-adapter` and `video-adapter` are always excluded.
- Added a `--force` option to the `imager-x/generate` console command for forcing transforms to be generated even if they already exist and have not expired (fixes #310).

### Fixed
- Fixed a race condition where concurrent first transforms of the same remote volume asset or external URL could publish a partial file to the source cache and reuse it until it expired. Source file downloads are now serialized with a mutex and published atomically with `rename()` (fixes #309).
- Fixed duplicate transform queue jobs when elements were saved in multi-site installs. Identical jobs are now deduplicated per request, and volume-based auto generation skips propagation saves (fixes #307).

## 6.0.1 - 2026-07-02

### Fixed
- Fixed additional SSRF bypasses in external URL validation: redirects are now followed manually and re-validated on every hop, the connection is pinned to the validated IP to mitigate DNS rebinding, all resolved A/AAAA records are checked (not just the first), and downloads are restricted to the `http`/`https` protocols. The `skipExternalUrlValidation` config setting still disables these checks.
- Fixed a path traversal issue by confining string-based local image sources to the web root / imager system path.
- Fixed `safeFileFormats` validation to URL sources in the `imagerTransform` GraphQL query.
- Fixed potential command injection in the image optimizers and custom encoder by escaping file path arguments with `escapeshellarg()`.
- Fixed the cache clear key comparison to be constant-time, and added validation of the `--volume` handle in the `imager-x/clean` console command.

## 6.0.0 - 2026-05-24

> [!WARNING]  
> This release contains breaking changes. Please review the release notes carefully before upgrading.

### Removed
- Removed Imgix support from core. Imgix is now available as a separate plugin, `spacecatninja/imager-x-imgix-transformer`. A deprecation notice is logged if old Imgix-related config settings are present. Please install the plugin and migrate your Imgix configuration to `config/imager-x-imgix-transformer.php`, see [documentation for details](https://github.com/spacecatninja/craft-imager-x-imgix-transformer).
- Removed AWS and GCS external storage drivers from core. These are now available as separate plugins, `spacecatninja/imager-x-aws-storage-driver` and `spacecatninja/imager-x-gcs-storage-driver`.
- Removed Kraken and Tinify optimizers from core. These are now available as separate plugins, `spacecatninja/imager-x-kraken-optimizer` and `spacecatninja/imager-x-tinify-optimizer`.

### Changed
- Changed `safeFileFormats` config setting, added `webp` to the default.
- Changed optimizer config key from `tinypng` to `tinify`.


## 5.2.1 - 2026-03-01

### Added
- Added skipExternalUrlValidation config setting to skip the new external URL validation check.

### Fixed
- Fixed SSRF vulnerability by blocking private/reserved IP ranges for external URL downloads.
- Fixed option escaping for shell commands.
- Fixed handling of cache clear key.

## 5.2.0 - 2026-02-28

### Added
- Added support for auto generating images in content blocks, and added support for expanded syntax to `fields` auto generate config (fixes #305 and #39).
- Added support for specifying offset and limit per field in the format `fieldHandle[offset:limit]` (fixes #301).

### Changed
- Changed from `Asset::EVENT_DEFINE_URL` to `Asset::EVENT_BEFORE_DEFINE_URL` when replacing native transforms with Imager X for performance (thanks, @johnnynotsolucky).

## 5.1.7 - 2025-11-14

### Fixed
- Fixed an issue where the width and height of Imgix transforms would be wrong if both `width` and `height` were provided, and mode was `fit`. (fixes #303, thanks @white-ruud).

## 5.1.6 - 2025-07-24

### Fixed
- Fixed asset check in all GQL related code to allow any file kind, to account for adapters (fixes #297, thanks @denisyilmaz).

## 5.1.5 - 2025-07-16

### Added
- Added config setting `disableACL` to AWS external storage (fixes #296).

### Fixed
- Fixed a type issue that could occur when setting opacity for letterbox.

## 5.1.4 - 2025-06-07

### Added
- Added support for dominantColor as a return type for `imagerTransform` GraphQL directive (fixes #290).

### Fixed
- Fixed an issue where Imgix URLs could have double slashes if `useCloudSourcePath` was `null` (fixes #289).

## 5.1.3 - 2025-03-25

### Fixed
- Fixed an issue where TransformJob was throwing an error if an asset couldn’t be found, which would result in errors when replacing assets.

## 5.1.2 - 2025-02-27

### Fixed
- Fixed an issue where GD could throw an error because the output format extension wasn’t an allowed one (addresses #184)

## 5.1.1 - 2025-01-07

### Fixed
- Fixed an issue that could occur if an assets volume didn’t have public URLs enabled (fixes #280).

## 5.1.0 - 2024-12-01

### Added
- Added support for credential less authentication for AWS external storage, the storage config now take a `useCredentialLessAuth` config setting that can be set to `true`. It should now behave the same as the AWS volume from P&T (closes #254).

### Changed
- Changed/repurposed behaviour of `hideClearCachesForUserGroups` config setting (which was no longer used), to hide clear cache element actions for user groups (closes #278).

## 5.0.3 - 2024-11-08

### Fixed
- Fixed an issue where Imager would try to remove transforms for non-image assets (fixes #273).

## 5.0.2 - 2024-08-08

### Fixed
- Fixed auto generation for fields inside matrix entry types (fixes #270).

## 5.0.1 - 2024-06-24

### Fixed
- Fixed an issue that could occur if slashes were used in config override parameters (fixes #268).

### Changed
- Changed event for registrering extensions to be more in line with what Craft recommends.

## 5.0.0 - 2024-04-10

### Added
- Added support for Craft 5
- Added support for creating queue jobs when using the generate console command.
- Added support for limit and offset when using the generate console command.
- Added support for using `{transformName}` in `filenamePattern` (resolves #257)

### Fixed
- Fixed incorrect closure check for transform params and improved error handling.
- Fixed icon method for utility
- Fixed use of deprecated method `ensureTopFolder`.

### Changed
- Changed behaviour of avif and jxl transforms, they are now processed via Imagine.
