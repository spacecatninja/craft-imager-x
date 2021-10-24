<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\console\controllers;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\base\Volume;
use craft\base\VolumeInterface;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;

use craft\helpers\FileHelper;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\services\ImagerService;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;
use yii\helpers\Console;

class CleanController extends Controller
{
    /**
     * @var string|null Handle of volume to clean transforms for
     */
    public $volume;
    
    /**
     * @var string|null Overrides the default cache duration settings
     */
    public $duration;
    
    
    // Public Methods
    // =========================================================================

    /**
     * @param string $actionsID
     * @return array|string[]
     */
    public function options($actionsID): array
    {
        $options = parent::options($actionsID);
        
        return array_merge($options, [
            'volume',
            'duration',
            'interactive',
        ]);
    }

    /**
     * @return array
     */
    public function optionAliases(): array
    {
        return [
            'v' => 'volume',
            'd' => 'duration',
        ];
    }

    /**
     * Generates image transforms by volume/folder or fields.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        if (!ImagerX::getInstance()->is(ImagerX::EDITION_PRO)) {
            $this->error('Console commands are only available in Imager X Pro. You need to upgrade to use this awesome feature (it\'s so worth it!).');
            return ExitCode::UNAVAILABLE;
        }
        
        $config = ImagerService::getConfig();
        $systemPath = $config->imagerSystemPath;
        $this->volume = trim($this->volume);
        
        if (!empty($this->volume)) {
            if (!$config->addVolumeToPath) {
                $this->error('Cannot clean transforms by volume when `addVolumeToPath` is `false`.');
                return ExitCode::UNAVAILABLE;
            }
            
            $systemPath = FileHelper::normalizePath($systemPath.DIRECTORY_SEPARATOR.$this->volume);
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
        
        $this->success("> Scanning $systemPath");
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($systemPath));
        $files = []; 
        
        foreach ($rii as $file) {
        
            if ($file->isDir()){ 
                continue;
            }
        
            $files[] = $file->getPathname();
        }
        
        if (empty($files)) {
            $this->error("No transforms found.");
            return ExitCode::OK;
        }
        
        $numFiles = count($files);
        $expiredFiles = [];
        $this->success("> Found {$numFiles} transformed images.");

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($this->fileHasExpired($file)) {
                    $expiredFiles[] = $file;
                }
            }
        }
        
        if (empty($expiredFiles)) {
            $this->success("No expired transforms found, you're all good.");
            return ExitCode::OK;
        }
        
        $numExpiredFiles = count($expiredFiles);
        
        if ($this->interactive !== false) {
            $promptReply = Console::prompt("> Found $numExpiredFiles expired transforms. Do you want to delete them (y/N)?");
            
            if (strtolower($promptReply) !== 'y') {
                $this->error("> Aborting.");
                return ExitCode::OK;
            }
        }
        
        $this->message("> Deleting $numExpiredFiles transforms.");
        Console::startProgress(0, $numExpiredFiles);
        $current = 0;

        foreach ($expiredFiles as $expiredFile) {
            $current++;
            Console::updateProgress($current, $numExpiredFiles);
            unlink($expiredFile);
        }
    
        Console::endProgress();
        $this->success("> Done.");
        return ExitCode::OK;
    }
    
    /**
     * @param string $text
     */
    public function success($text = '')
    {
        $this->stdout("$text\n", BaseConsole::FG_GREEN);
    }

    /**
     * @param string $text
     */
    public function message($text = '')
    {
        $this->stdout("$text\n", BaseConsole::FG_GREY);
    }

    /**
     * @param string $text
     */
    public function error($text = '')
    {
        $this->stdout("$text\n", BaseConsole::FG_RED);
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    private function fileHasExpired(string $file): bool
    {
        return FileHelper::lastModifiedTime($file) + $this->duration < time();
    }
    
}
