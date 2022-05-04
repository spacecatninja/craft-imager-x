<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\models;

interface TransformedImageInterface
{
    public function getPath(): string;

    public function getFilename(): string;

    public function getUrl(): string;

    public function getExtension(): string;

    public function getMimeType(): string;

    public function getWidth(): int;

    public function getHeight(): int;

    public function getSize(string $unit = 'b', int $precision = 2): mixed;

    public function getDataUri(): string;

    public function getBase64Encoded(): string;

    public function getPlaceholder(array $settings = []): string;

    public function getIsNew(): bool;
}
