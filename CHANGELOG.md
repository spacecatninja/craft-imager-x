# Imager X Changelog

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
