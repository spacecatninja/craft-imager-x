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

use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Folders;
use CraftCms\Cms\Asset\Volumes;
use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Database\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SpaceCatNinja\ImagerX\Console\Concerns\ParsesBooleanOptions;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Services\GenerateService;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class GenerateCommand extends Command
{
    use CraftCommand;
    use ParsesBooleanOptions;

    #[\Override]
    protected $signature = 'imager-x:generate
        {--volume= : Handle of volume to generate transforms for.}
        {--folderId= : Folder ID to generate transforms for.}
        {--recursive=false : Enable or disable recursive handling of folders.}
        {--force=false : When set to true, transforms will be created even if they already exist and have not expired.}
        {--field= : Field to generate transforms for.}
        {--transforms= : Which transforms to generate.}
        {--queue=false : Enable or disable queue jobs instead of direct transforms.}
        {--limit= : Number of assets to process.}
        {--offset= : Offset for assets to process.}';

    #[\Override]
    protected $description = 'Generates image transforms by volume/folder or fields.';

    #[\Override]
    protected $aliases = ['imager-x/generate', 'craft:imager-x:generate'];

    public function handle(ImagerX $plugin, GenerateService $generate, Volumes $volumes, Folders $folders): int
    {
        if (!$plugin->isPro()) {
            $this->error("Console commands are only available in Imager X Pro. You need to upgrade to use this awesome feature (it's so worth it!).");

            return self::FAILURE;
        }

        $volumeHandle = trim((string)$this->option('volume'));
        $fieldHandle = trim((string)$this->option('field'));
        $transformsOption = trim((string)$this->option('transforms'));
        $queue = $this->booleanOption('queue');
        $force = $this->booleanOption('force');

        $volumeSpecified = $volumeHandle !== '';
        $fieldSpecified = $fieldHandle !== '';

        if ($volumeSpecified && $fieldSpecified) {
            $this->error("Both volume and field is specified. That doesn't make sense, it's either or.");

            return self::INVALID;
        }

        if (!$volumeSpecified && !$fieldSpecified) {
            $this->error('No volume or field were specified');

            return self::INVALID;
        }

        $targetVolume = null;

        if ($volumeSpecified) {
            $targetVolume = $volumes->getAllVolumes()->firstWhere('handle', $volumeHandle);

            if ($targetVolume === null) {
                $this->error(sprintf('No volumes with handle %s exists', $volumeHandle));

                return self::INVALID;
            }
        }

        $targetFieldIds = [];

        if ($fieldSpecified) {
            $targetFieldIds = DB::table(Table::FIELDS)
                ->where('handle', $fieldHandle)
                ->pluck('id')
                ->all();

            if (empty($targetFieldIds)) {
                $this->error(sprintf('No field with handle %s exists', $fieldHandle));

                return self::INVALID;
            }
        }

        $transforms = $transformsOption !== '' ? explode(',', $transformsOption) : [];

        // Get transforms from config if none were passed in
        if ($volumeSpecified && empty($transforms)) {
            $transforms = app(GenerateSettings::class)->volumes[$volumeHandle] ?? [];
        }

        if ($fieldSpecified && empty($transforms)) {
            $transforms = app(GenerateSettings::class)->fields[$fieldHandle] ?? [];
        }

        $assets = $volumeSpecified
            ? $this->getAssetsByVolume($targetVolume, $folders)
            : $this->getAssetsByField($fieldHandle, $targetFieldIds);

        if (empty($assets)) {
            $this->error('No transformable assets found');

            return self::SUCCESS;
        }

        $numTransforms = is_countable($transforms) ? count($transforms) : 0;
        $total = count($assets);
        $current = 0;
        $this->newLine();
        $this->line(sprintf('<fg=yellow>> Generating %d named transform(s) for %d image(s).</>', $numTransforms, $total));

        foreach ($assets as $asset) {
            ++$current;
            $filename = $asset->filename;

            $this->output->write('    - [' . ($current * $numTransforms) . '/' . $total * $numTransforms . "] ($asset->id) " . (strlen($filename) > 50 ? (substr($filename, 0, 47) . '...') : $filename) . '... ');

            if (GenerateService::shouldTransformElement($asset)) {
                if ($queue) {
                    $generate->createTransformJob($asset, $transforms, $force);
                } else {
                    $generate->generateTransformsForAsset($asset, $transforms, $force);
                }

                $this->line('<fg=green>' . ($queue ? 'queued' : 'done') . '</>');
            } else {
                $this->line('<fg=red>skipped</>');
            }
        }

        $this->line('<fg=yellow>> Done.</>');

        return self::SUCCESS;
    }

    /**
     * @return Asset[]
     */
    private function getAssetsByVolume(object $volume, Folders $folders): array
    {
        $recursive = $this->booleanOption('recursive');
        $folderId = $this->option('folderId');

        $this->info(sprintf('> Volume `%s`', $volume->handle));

        $query = Asset::find()
            ->volume($volume)
            ->kind('image')
            ->offset($this->intOption('offset'))
            ->limit($this->intOption('limit'));

        if (!empty($folderId)) {
            $query->folderId((int)$folderId);
        } else {
            $folder = $folders->getRootFolderByVolumeId($volume->id);

            if ($folder) {
                $query->folderId($folder->id);
            }
        }

        $this->info($recursive ? '> Recursive' : '> Not recursive');
        $query->includeSubfolders($recursive);

        return $query->all();
    }

    /**
     * @param int[] $fieldIds
     * @return Asset[]
     */
    private function getAssetsByField(string $fieldHandle, array $fieldIds): array
    {
        if (count($fieldIds) > 1) {
            $this->info('> Processing ' . count($fieldIds) . sprintf(' fields with handle `%s`', $fieldHandle));
        } else {
            $this->info(sprintf('> Processing field with handle `%s`', $fieldHandle));
        }

        $assetIds = DB::table(Table::RELATIONS, 'relations')
            ->join(Table::ELEMENTS . ' as elements', 'elements.id', '=', 'relations.targetId')
            ->whereIn('relations.fieldId', $fieldIds)
            ->where('elements.type', Asset::class)
            ->distinct()
            ->pluck('elements.id')
            ->all();

        if (empty($assetIds)) {
            return [];
        }

        return Asset::find()
            ->id($assetIds)
            ->kind('image')
            ->offset($this->intOption('offset'))
            ->limit($this->intOption('limit'))
            ->all();
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (int)$value;
    }
}
