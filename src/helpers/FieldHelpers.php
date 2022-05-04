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
use craft\elements\db\ElementQuery;
use craft\elements\db\MatrixBlockQuery;
use craft\elements\MatrixBlock;
use craft\models\FieldLayout;

class FieldHelpers
{
    /**
     * @param Element|ElementInterface $element
     *
     */
    public static function getFieldInFieldLayoutByHandle(ElementInterface|Element $element, FieldLayout $layout, string $handle): ?ElementQuery
    {
        return $layout->getFieldByHandle($handle) !== null ? $element->{$handle} : null;
    }

    /**
     * @param Element|ElementInterface $element
     *
     */
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
            
            if (!$parentField instanceof MatrixBlockQuery && !$parentField instanceof \verbb\supertable\elements\db\SuperTableBlockQuery && !$parentField instanceof \benf\neo\elements\db\BlockQuery) {
                return null;
            }

            $blockQuery = clone $parentField;
            $blocks = $blockQuery->all();
            
            $fields = [];

            /* @var MatrixBlock $block */
            foreach ($blocks as $block) {
                if (($block->getType()->handle !== $parentBlockType && $parentBlockType !== '*') || !($block->{$parentBlockFieldHandle} ?? null)) {
                    continue;
                }
                
                $fields[] = $block->{$parentBlockFieldHandle};
            }

            return empty($fields) ? null : $fields;
        }
        
        if (isset($element->{$handle})) {
            return [$element->{$handle}];
        }

        return null;
    }

    protected static function getFieldHandleSegments(string $handle): array
    {
        $segments = preg_split('#(\:|\.)#', $handle);
        
        if (!is_array($segments)) {
            $segments = [];
        }

        if (count($segments) !== 3) {
            $msg = Craft::t('imager-x', 'Invalid field format handle for “{handle}“. Either use a single string for fields directly on the element, or a string with the format “myMatrixField:myMatrixBlockType.myMatrixBlockField“.', ['handle' => $handle]);
            Craft::error($msg, __METHOD__);
            return [];
        }
        
        return $segments;
    }
}
