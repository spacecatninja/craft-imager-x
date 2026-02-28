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
    /**
     * @param ElementInterface|Element $element
     * @param string $handle
     * @return array|null
     */
    public static function getFieldsInElementByHandle(ElementInterface|Element $element, string $handle): ?array
    {
        $handle = preg_replace('/\[.*\]$/', '', $handle);
        
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

    /**
     * @param string $handle
     * @return array
     */
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

    /**
     * Parses a field handle string to extract offset and limit values.
     *
     * The method uses a pattern to identify and extract content inside square brackets (`[]`).
     * If the matched content exists, it further splits it by a colon (`:`) to derive the offset and limit.
     *
     * @param string $handle The field handle string to parse. It should contain the offset and/or limit in the form `[offset:limit]`.
     *                       If no colon (`:`) is present, only the offset is extracted.
     *                       If no valid pattern is matched, the default values are returned.
     * @return array Returns an array where the first element represents the offset as an integer
     *               and the second element represents the limit as an integer or null if not defined.
     */
    public static function getOffsetAndLimitFromFieldHandle(string $handle): array
    {
        preg_match('/\[(.*?)\]/', $handle, $matches);
        
        if (empty($matches[1])) {
            return [0, null];
        }
        
        $parts = explode(':', $matches[1]);
        
        if (count($parts) === 1) {
            return [(int)$parts[0], null];
        }
        
        $offset = $parts[0] !== '' ? (int)$parts[0] : 0;
        $limit = $parts[1] !== '' ? (int)$parts[1] : null;
        
        return [$offset, $limit];
    }
}
