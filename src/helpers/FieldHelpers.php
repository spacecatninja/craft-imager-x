<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use Craft;
use craft\base\Element;

use craft\base\ElementInterface;
use craft\elements\db\EntryQuery;

class FieldHelpers
{
    public static function getFieldsInElementByHandle(ElementInterface|Element $element, string $handle): ?array
    {
        if (str_contains($handle, ':')) {
            $segments = self::getFieldHandleSegments($handle);

            if (empty($segments)) {
                return null;
            }

            [$parentFieldHandle, $parentBlockType, $parentBlockFieldHandle] = $segments;

            $parentField = $element->{$parentFieldHandle} ?? null;

            if (!$parentField) {
                return null;
            }

            if (!$parentField instanceof EntryQuery && !$parentField instanceof \benf\neo\elements\db\BlockQuery) {
                return null;
            }

            $blockQuery = clone $parentField;
            $blocks = $blockQuery->all();
            
            $fields = [];

            foreach ($blocks as $block) {
                if (($block->getType()->handle !== $parentBlockType && $parentBlockType !== '*') || !($block->{$parentBlockFieldHandle} ?? null)) {
                    continue;
                }
                
                $fields[] = $block->{$parentBlockFieldHandle};
            }

            return empty($fields) ? null : $fields;
        } 
        
        if (str_contains($handle, '.')) {
            $segments = self::getFieldHandleSegments($handle);

            if (empty($segments) || !isset($element->{$segments[0]})) {
                return null;
            }
            
            return [$element->{$segments[0]}->{$segments[1]}];
        }
        
        if (isset($element->{$handle})) {
            return [$element->{$handle}];
        }

        return null;
    }

    protected static function getFieldHandleSegments(string $handle): array
    {
        $msg = Craft::t('imager-x', 'Invalid field format handle for “{handle}“. Either use a single string for fields directly on the element, or a string with the format “contentBlockField.myField“ or “myMatrixField:myMatrixEntryType.myField“.', ['handle' => $handle]);
        
        if (str_contains($handle, ':')) {
            $segments = preg_split('#(\:|\.)#', $handle);

            if (!is_array($segments) || count($segments) !== 3) {
                Craft::error($msg, __METHOD__);
                return [];
            }
        } else {
            $segments = preg_split('#(\.)#', $handle);

            if (!is_array($segments) || count($segments) !== 2) {
                Craft::error($msg, __METHOD__);
                return [];
            }
        }
        
        return $segments;
    }
}
