<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Path;
use Illuminate\Console\Command;
use SpaceCatNinja\ImagerX\Console\Concerns\ParsesBooleanOptions;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Support\FileHelper;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use CraftCms\Cms\Support\File;

class CleanCommand extends Command
{
    use CraftCommand;
    use ParsesBooleanOptions;

    /**
     * @var string[] Subpaths that are always excluded when cleaning the runtime cache.
     */
    private const array RUNTIME_CACHE_EXCLUDES = ['temp', 'pdf-adapter', 'video-adapter'];

    #[\Override]
    protected $signature = 'imager-x:clean
        {--volume= : Handle of volume to clean transforms for.}
        {--runtimeCache=false : Cleans the runtime cache when set to true.}
        {--exclude= : Comma-separated list of subpaths to exclude, relative to the path being cleaned. When cleaning the runtime cache, `temp`, `pdf-adapter` and `video-adapter` are always excluded.}
        {--duration= : Overrides the default cache duration settings. Accepts a number of seconds, or any duration string supported by PHP\'s `DateInterval`, eg. `P30D`.}
        {--interactive=true : Whether to ask for confirmation before deleting expired files.}';

    #[\Override]
    protected $description = 'Cleans image transforms in the local system path.';

    #[\Override]
    protected $aliases = ['imager-x/clean', 'craft:imager-x:clean'];

    private int $durationInSeconds = 0;

    public function handle(): int
    {
        $config = ImagerService::getConfig();
        $path = $config->imagerSystemPath;
        $configDuration = $config->cacheDuration;
        $runtimeCache = $this->booleanOption('runtimeCache');
        $volume = trim((string)$this->option('volume'));
        $duration = $this->option('duration');
        $noun = $runtimeCache ? 'files' : 'transforms';
        $excludes = array_filter(array_map(static fn(string $exclude): string => trim($exclude, "/ \t"), explode(',', (string)$this->option('exclude'))));

        if ($runtimeCache && $volume !== '') {
            $this->error('Runtime cache and volume cannot be used together.');
            return self::FAILURE;
        }

        if ($runtimeCache) {
            $configDuration = $config->cacheDurationRemoteFiles;
            $path = File::normalizePath(Path::runtime() . '/imager/');
            $excludes = array_unique(array_merge($excludes, self::RUNTIME_CACHE_EXCLUDES));
        } elseif ($volume !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $volume)) {
                $this->error('Invalid volume handle "' . $volume . '".');
                return self::FAILURE;
            }

            if (!$config->addVolumeToPath) {
                $this->error('Cannot clean transforms by volume when `addVolumeToPath` is `false`.');
                return self::FAILURE;
            }

            $path = File::normalizePath($path . DIRECTORY_SEPARATOR . $volume);
        }

        if (!$duration && $configDuration === false) {
            $this->error(sprintf('`%s` has been set to `false`, and no duration has been passed to the console command. Nothing to do.', $runtimeCache ? 'cacheDurationRemoteFiles' : 'cacheDuration'));
            return self::FAILURE;
        }

        $resolvedDuration = $duration ?: $configDuration;
        $durationInSeconds = ImagerHelpers::normalizeCacheDuration($resolvedDuration);

        if ($durationInSeconds === null) {
            $this->error(sprintf('Invalid duration %s. Expected a number of seconds, or a duration string supported by PHP\'s `DateInterval`, eg. `P30D`.', ImagerHelpers::cacheDurationToString($resolvedDuration)));
            return self::FAILURE;
        }

        if ($durationInSeconds === false) {
            $this->error(sprintf('`%s` has been set to `false`, and no duration has been passed to the console command. Nothing to do.', $runtimeCache ? 'cacheDurationRemoteFiles' : 'cacheDuration'));
            return self::FAILURE;
        }

        $this->durationInSeconds = $durationInSeconds;

        if (!is_dir($path)) {
            $this->error('Path not found, cache is probably empty.');
            return self::SUCCESS;
        }

        $this->info(sprintf('> Scanning %s', $path));

        if (!empty($excludes)) {
            $this->line(sprintf('> Excluding %s', implode(', ', $excludes)));
        }

        $files = $this->filterExcludedFiles(FileHelper::filesInPath($path), $path, $excludes);

        if (empty($files)) {
            $this->error(sprintf('No %s found.', $noun));
            return self::SUCCESS;
        }

        $this->info(sprintf('> Found %d %s.', count($files), $noun));

        $expiredFiles = array_values(array_filter($files, fn(string $file): bool => is_file($file) && $this->fileHasExpired($file)));

        if (empty($expiredFiles)) {
            $this->info(sprintf("No expired %s found, you're all good.", $noun));
            return self::SUCCESS;
        }

        $numExpiredFiles = count($expiredFiles);

        if ($this->input->isInteractive() && $this->booleanOption('interactive')) {
            if (!$this->confirm(sprintf('> Found %d expired %s. Do you want to delete them?', $numExpiredFiles, $noun))) {
                $this->error('> Aborting.');
                return self::SUCCESS;
            }
        }

        $this->line(sprintf('> Deleting %d %s.', $numExpiredFiles, $noun));
        $this->withProgressBar($expiredFiles, static fn(string $expiredFile) => unlink($expiredFile));
        $this->newLine();
        $this->info('> Done.');

        return self::SUCCESS;
    }

    private function fileHasExpired(string $file): bool
    {
        return File::lastModified($file) + $this->durationInSeconds < time();
    }

    /**
     * Filters out files inside any of the excluded subpaths.
     *
     * @param string[] $files
     * @param string[] $excludes
     * @return string[]
     */
    private function filterExcludedFiles(array $files, string $basePath, array $excludes): array
    {
        if (empty($excludes)) {
            return $files;
        }

        $basePath = realpath($basePath);

        if ($basePath === false) {
            return $files;
        }

        $prefixes = array_map(static fn(string $exclude): string => $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $exclude) . DIRECTORY_SEPARATOR, $excludes);

        return array_filter($files, static function(string $file) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($file, $prefix)) {
                    return false;
                }
            }

            return true;
        });
    }
}
