# Imager X Changelog

## Unreleased

### Fixed
- Fixed GraphQL support on Craft 6.0.0-beta.1, where querying any GraphQL schema threw an exception when Imager X was installed.

## 7.0.0-beta.1 - 2026-11-14

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
- `getDominantColor()` now returns the most common color in the image. Previously it returned the first color of the Color Thief palette, which is ranked by how much of the color space a group of colors spans, not just by how many pixels it has. A small, varied detail could beat a large, flat area, so an image that is 90% red with a blue detail would get blue as its dominant color. This also applies to the `dominantColor` generate flag and the `dominantColor` return type of the `@imagerTransform` GraphQL directive.
- `getDominantColor()` and `getColorPalette()` no longer ignore white. Color Thief skips pixels where all channels are above 250, so a mostly white image got the color of whatever else was in it as its dominant color, and a white image got `null`.
- `getDominantColor()` now returns the actual average color of the matching pixels, so a white image gets `#ffffff` and a black image gets `#000000`, instead of `#fcfcfc` and `#040404`.
- `ksubileau/color-thief-php` 2.0 or later is now required.

### Fixed
- Fixed several security issues.
- Fixed `removeTransformsOnAssetFileops` not removing transforms when an asset was moved without being renamed.
- Fixed a potential infinite loop when using the `frames` parameter.
- Fixed remote images that couldn't be opened being kept in the source cache.
- Fixed partially downloaded remote images being left behind when a download failed.
- Fixed some path checks failing on Windows.
- Fixed `getColorPalette()` throwing a `TypeError` instead of returning `null` when an image couldn't be analyzed.
- Fixed transformed images having their file type detected with `finfo` on every render, also when the transform already existed. For JPEG, PNG, GIF, WebP and AVIF the mime type is now taken from the `getimagesize()` call that reads the image dimensions (fixes #315).
- Fixed native crop transforms not using the asset's focal point when `useForNativeTransforms` is enabled, so they now match Craft (fixes #314).
- Fixed the Imager X utility being unstyled on Craft 6, and the transform picker not showing when “Use configured transforms” was turned off.

## 7.0.0-alpha.3 - 2026-10-03

### Added
- Added `imagine/imagine` as a dependency, since Craft 6 no longer requires it.
- Added a `--runtimeCache` option to the `imager-x/clean` console command for cleaning expired files in the runtime cache (fixes #311).
- Added an `--exclude` option to the `imager-x/clean` console command for excluding subpaths from cleaning. When cleaning the runtime cache, `temp`, `pdf-adapter` and `video-adapter` are always excluded.
- Added a `--force` option to the `imager-x/generate` console command for forcing transforms to be generated even if they already exist and have not expired (fixes #310).

### Fixed
- Fixed compatibility with Craft 6.0.0-alpha.19, where `craft\elements\Asset` is no longer an alias of `CraftCms\Cms\Asset\Elements\Asset`. Assets were not recognised, and transforms silently returned `null`.
- Fixed downloading source files from asset volumes on Craft 6.0.0-alpha.19, where `Assets::downloadFile()` now expects the volume’s filesystem disk.
- Fixed a path traversal issue by confining string-based local image sources to the web root / imager system path. This was listed in 7.0.0-alpha.2, but the fix was not included in that release.
- Fixed a race condition where concurrent first transforms of the same remote volume asset or external URL could publish a partial file to the source cache and reuse it until it expired. Source file downloads are now serialized with a mutex and published atomically with `rename()` (fixes #309).
- Fixed duplicate transform queue jobs when elements were saved in multi-site installs. Identical jobs are now deduplicated per request, and volume-based auto generation skips propagation saves (fixes #307).
- Fixed date interval parsing for all durations (fixes #313).

## 7.0.0-alpha.2 - 2026-07-02

### Fixed
- Fixed additional SSRF bypasses in external URL validation: redirects are now followed manually and re-validated on every hop, the connection is pinned to the validated IP to mitigate DNS rebinding, all resolved A/AAAA records are checked (not just the first), and downloads are restricted to the `http`/`https` protocols. The `skipExternalUrlValidation` config setting still disables these checks.
- Fixed a path traversal issue by confining string-based local image sources to the web root / imager system path.
- Fixed `safeFileFormats` validation to URL sources in the `imagerTransform` GraphQL query.
- Fixed potential command injection in the image optimizers and custom encoder by escaping file path arguments with `escapeshellarg()`.
- Fixed the cache clear key comparison to be constant-time, and added validation of the `--volume` handle in the `imager-x/clean` console command.

## 7.0.0-alpha.1 - 2026-05-25

### Added
- Added compability layer for Craft 6.
