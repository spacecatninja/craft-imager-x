<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
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
     * @param ElementInterface|Element $element
     * @param FieldLayout $layout
     * @param string $handle
     * @return null|ElementQuery
     */
    public static function getFieldInFieldLayoutByHandle($element, $layout, $handle)
    {
        return $layout->getFieldByHandle($handle) ? $element->{$handle} : null;
    }

    /**
     * @param ElementInterface|Element $element
     * @param string $handle
     * @return array|null
     */
    public static function getFieldsInElementByHandle($element, $handle)
    {
        if (strpos($handle, ':') !== false) {
            $segments = self::getFieldHandleSegments($handle);
            
            if (empty($segments)) {
                return null;
            }

            list($matrixFieldHandle, $matrixBlockType, $matrixBlockFieldHandle) = $segments;

            $matrixField = $element->{$matrixFieldHandle} ?? null;

            if (!$matrixField || !$matrixField instanceof MatrixBlockQuery) {
                return null;
            }

            $blockQuery = clone $matrixField;
            $blocks = $blockQuery->all();
            
            $fields = [];

            /* @var MatrixBlock $block */
            foreach ($blocks as $block) {
                if ($block->type->handle !== $matrixBlockType || !($block->{$matrixBlockFieldHandle} ?? null)) {
                    continue;
                }
                
                $fields[] = $block->{$matrixBlockFieldHandle};
            }

            return !empty($fields) ? $fields : null;
        }
        
        if ($element->{$handle}) {
            return [$element->{$handle}];
        }

        return null;
    }

    /**
     * @param string $handle
     * @return array
     */
    protected static function getFieldHandleSegments($handle):array 
    {
        $segments = preg_split('/(\:|\.)/', $handle);
        
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
