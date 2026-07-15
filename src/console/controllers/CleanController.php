<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\console\controllers;

use Craft;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\helpers\FileHelper;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;
use yii\helpers\Console;

class CleanController extends Controller
{
    /**
     * @var string[] Subpaths that are always excluded when cleaning the runtime cache.
     */
    private const RUNTIME_CACHE_EXCLUDES = ['temp', 'pdf-adapter', 'video-adapter'];

    /**
     * @var string Handle of volume to clean transforms for.
     */
    public string $volume = '';

    /**
     * @var bool Cleans the runtime cache when set to true.
     */
    public bool $runtimeCache = false;

    /**
     * @var array Comma-separated list of subpaths to exclude, relative to the path being cleaned. When cleaning the runtime cache, `temp`, `pdf-adapter` and `video-adapter` are always excluded.
     */
    public array $exclude = [];

    /**
     * @var string|null Overrides the default cache duration settings.
     */
    public ?string $duration = null;


    // Public Methods
    // =========================================================================
    /**
     * @param string $actionID
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        return array_merge($options, [
            'volume',
            'runtimeCache',
            'exclude',
            'duration',
            'interactive',
        ]);
    }

    public function optionAliases(): array
    {
        return [
            'v' => 'volume',
            'e' => 'exclude',
            'd' => 'duration',
        ];
    }

    /**
     * Cleans image transforms in the local system path.
     */
    public function actionIndex(): int
    {
        $config = ImagerService::getConfig();
        $path = $config->imagerSystemPath;
        $configDuration = $config->cacheDuration;
        $noun = $this->runtimeCache ? 'files' : 'transforms';
        $excludes = array_filter(array_map(static fn(string $exclude): string => trim($exclude, "/ \t"), $this->exclude));
        $this->volume = trim($this->volume);

        if ($this->runtimeCache && !empty($this->volume)) {
            $this->error('Runtime cache and volume cannot be used together.');
            return ExitCode::UNAVAILABLE;
        }

        if ($this->runtimeCache) {
            $configDuration = $config->cacheDurationRemoteFiles;
            $path = FileHelper::normalizePath(Craft::$app->getPath()->getRuntimePath() . '/imager/');
            $excludes = array_unique(array_merge($excludes, self::RUNTIME_CACHE_EXCLUDES));
        } elseif (!empty($this->volume)) {
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $this->volume)) {
                $this->error('Invalid volume handle "' . $this->volume . '".');
                return ExitCode::UNAVAILABLE;
            }

            if (!$config->addVolumeToPath) {
                $this->error('Cannot clean transforms by volume when `addVolumeToPath` is `false`.');
                return ExitCode::UNAVAILABLE;
            }
            
            $path = FileHelper::normalizePath($path . DIRECTORY_SEPARATOR . $this->volume);
        }

        if (!$this->duration && $configDuration === false) {
            $this->error(sprintf('`%s` has been set to `false`, and no duration has been passed to the console command. Nothing to do.', $this->runtimeCache ? 'cacheDurationRemoteFiles' : 'cacheDuration'));
            return ExitCode::UNAVAILABLE;
        }

        if (!$this->duration) {
            $this->duration = $configDuration;
        }

        if (!is_dir($path)) {
            $this->error("Path not found, cache is probably empty.");
            return ExitCode::OK;
        }

        $this->success(sprintf('> Scanning %s', $path));

        if (!empty($excludes)) {
            $this->message(sprintf('> Excluding %s', implode(', ', $excludes)));
        }

        $files = $this->filterExcludedFiles(FileHelper::filesInPath($path), $path, $excludes);

        if (empty($files)) {
            $this->error(sprintf('No %s found.', $noun));
            return ExitCode::OK;
        }

        $numFiles = count($files);
        $expiredFiles = [];
        $this->success(sprintf('> Found %d %s.', $numFiles, $noun));

        foreach ($files as $file) {
            if (is_file($file) && $this->fileHasExpired($file)) {
                $expiredFiles[] = $file;
            }
        }

        if (empty($expiredFiles)) {
            $this->success(sprintf("No expired %s found, you're all good.", $noun));
            return ExitCode::OK;
        }

        $numExpiredFiles = count($expiredFiles);

        if ($this->interactive) {
            $promptReply = Console::prompt(sprintf('> Found %d expired %s. Do you want to delete them (y/N)?', $numExpiredFiles, $noun));

            if (strtolower($promptReply) !== 'y') {
                $this->error("> Aborting.");
                return ExitCode::OK;
            }
        }

        $this->message(sprintf('> Deleting %d %s.', $numExpiredFiles, $noun));
        Console::startProgress(0, $numExpiredFiles);
        $current = 0;

        foreach ($expiredFiles as $expiredFile) {
            ++$current;
            Console::updateProgress($current, $numExpiredFiles);
            unlink($expiredFile);
        }

        Console::endProgress();
        $this->success("> Done.");
        return ExitCode::OK;
    }

    public function success(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_GREEN);
    }

    public function message(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_GREY);
    }

    public function error(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_RED);
    }

    private function fileHasExpired(string $file): bool
    {
        return FileHelper::lastModifiedTime($file) + $this->duration < time();
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
