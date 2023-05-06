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
     * @var string Handle of volume to clean transforms for
     */
    public string $volume = '';
    
    /**
     * @var string|null Overrides the default cache duration settings
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
            'duration',
            'interactive',
        ]);
    }

    public function optionAliases(): array
    {
        return [
            'v' => 'volume',
            'd' => 'duration',
        ];
    }

    /**
     * Cleans image transforms in the local system path.
     */
    public function actionIndex(): int
    {
        $config = ImagerService::getConfig();
        $systemPath = $config->imagerSystemPath;
        $this->volume = trim($this->volume);
        
        if (!empty($this->volume)) {
            if (!$config->addVolumeToPath) {
                $this->error('Cannot clean transforms by volume when `addVolumeToPath` is `false`.');
                return ExitCode::UNAVAILABLE;
            }
            
            $systemPath = FileHelper::normalizePath($systemPath . DIRECTORY_SEPARATOR . $this->volume);
        }
        
        if (!$this->duration && $config->cacheDuration === false) {
            $this->error('`cacheDuration` has been set to `false`, and no duration has been passed to the console command. Nothing to do.');
            return ExitCode::UNAVAILABLE;
        }
        
        if (!$this->duration) {
            $this->duration = $config->cacheDuration;
        }
        
        if (!is_dir($systemPath)) {
            $this->error("Path not found, cache is probably empty.");
            return ExitCode::OK;
        }
        
        $this->success(sprintf('> Scanning %s', $systemPath));
        
        $files = FileHelper::filesInPath($systemPath);
        
        if (empty($files)) {
            $this->error("No transforms found.");
            return ExitCode::OK;
        }
        
        $numFiles = count($files);
        $expiredFiles = [];
        $this->success(sprintf('> Found %d transformed images.', $numFiles));

        foreach ($files as $file) {
            if (is_file($file) && $this->fileHasExpired($file)) {
                $expiredFiles[] = $file;
            }
        }
        
        if (empty($expiredFiles)) {
            $this->success("No expired transforms found, you're all good.");
            return ExitCode::OK;
        }
        
        $numExpiredFiles = count($expiredFiles);
        
        if ($this->interactive) {
            $promptReply = Console::prompt(sprintf('> Found %d expired transforms. Do you want to delete them (y/N)?', $numExpiredFiles));
            
            if (strtolower($promptReply) !== 'y') {
                $this->error("> Aborting.");
                return ExitCode::OK;
            }
        }
        
        $this->message(sprintf('> Deleting %d transforms.', $numExpiredFiles));
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
}
