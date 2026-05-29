<?php
namespace KiriminAjaOfficial\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Volumetric
{
    /**
     * Calculate a conservative cart box by choosing the smallest volume from a
     * set of concrete, axis-aligned stacking layouts.
     *
     * This intentionally does not claim to solve the general 3D bin-packing
     * problem. Every returned candidate represents an actually packable layout:
     * all items are placed in one row/stack along one axis while the two other
     * axes are the maximum dimensions required by any item in that layout.
     *
     * Product dimensions may be rotated per cart line because courier
     * volumetric dimensions describe the resulting package, not a fixed shelf
     * orientation.
     */
    public static function calculateSmallestBox($items)
    {
        if (empty($items)) {
            return ['length' => 0, 'width' => 0, 'height' => 0];
        }

        $normalizedItems = self::normalizeItems($items);
        if (empty($normalizedItems)) {
            return ['length' => 0, 'width' => 0, 'height' => 0];
        }

        $best = null;
        foreach (['length', 'width', 'height'] as $stackAxis) {
            $candidate = self::smallestStackAlongAxis($normalizedItems, $stackAxis);
            if ($candidate === null) {
                continue;
            }

            if ($best === null || self::compareBoxes($candidate, $best) < 0) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            return ['length' => 0, 'width' => 0, 'height' => 0];
        }

        return $best;
    }

    private static function normalizeItems($items)
    {
        $normalized = [];

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $dimensions = [
                'length' => max(0, (float) ($item['length'] ?? 0)),
                'width'  => max(0, (float) ($item['width'] ?? 0)),
                'height' => max(0, (float) ($item['height'] ?? 0)),
            ];

            if ($dimensions['length'] <= 0 || $dimensions['width'] <= 0 || $dimensions['height'] <= 0) {
                continue;
            }

            $normalized[] = [
                'qty' => $qty,
                'rotations' => self::itemRotations($dimensions),
            ];
        }

        return $normalized;
    }

    private static function smallestStackAlongAxis($items, $stackAxis)
    {
        $crossAxes = array_values(array_diff(['length', 'width', 'height'], [$stackAxis]));
        $thresholds = self::crossAxisThresholds($items, $crossAxes);
        $best = null;

        foreach ($thresholds[$crossAxes[0]] as $firstLimit) {
            foreach ($thresholds[$crossAxes[1]] as $secondLimit) {
                $box = [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                ];
                $box[$crossAxes[0]] = $firstLimit;
                $box[$crossAxes[1]] = $secondLimit;

                foreach ($items as $item) {
                    $bestRotation = null;
                    foreach ($item['rotations'] as $rotation) {
                        if ($rotation[$crossAxes[0]] > $firstLimit || $rotation[$crossAxes[1]] > $secondLimit) {
                            continue;
                        }

                        if ($bestRotation === null || $rotation[$stackAxis] < $bestRotation[$stackAxis]) {
                            $bestRotation = $rotation;
                        }
                    }

                    if ($bestRotation === null) {
                        continue 2;
                    }

                    $box[$stackAxis] += $bestRotation[$stackAxis] * $item['qty'];
                }

                if ($box[$stackAxis] <= 0) {
                    continue;
                }

                if ($best === null || self::compareBoxes($box, $best) < 0) {
                    $best = $box;
                }
            }
        }

        return $best;
    }

    private static function crossAxisThresholds($items, $crossAxes)
    {
        $thresholds = [
            $crossAxes[0] => [],
            $crossAxes[1] => [],
        ];

        foreach ($items as $item) {
            foreach ($item['rotations'] as $rotation) {
                $thresholds[$crossAxes[0]][] = $rotation[$crossAxes[0]];
                $thresholds[$crossAxes[1]][] = $rotation[$crossAxes[1]];
            }
        }

        foreach ($thresholds as $axis => $values) {
            $values = array_values(array_unique($values, SORT_REGULAR));
            sort($values, SORT_NUMERIC);
            $thresholds[$axis] = $values;
        }

        return $thresholds;
    }

    private static function itemRotations($dimensions)
    {
        $modes = [
            ['length' => 'length', 'width' => 'width', 'height' => 'height'],
            ['length' => 'length', 'width' => 'height', 'height' => 'width'],
            ['length' => 'width', 'width' => 'length', 'height' => 'height'],
            ['length' => 'width', 'width' => 'height', 'height' => 'length'],
            ['length' => 'height', 'width' => 'length', 'height' => 'width'],
            ['length' => 'height', 'width' => 'width', 'height' => 'length'],
        ];

        $rotations = [];
        foreach ($modes as $mode) {
            $key = $dimensions[$mode['length']] . 'x' . $dimensions[$mode['width']] . 'x' . $dimensions[$mode['height']];
            $rotations[$key] = [
                'length' => $dimensions[$mode['length']],
                'width'  => $dimensions[$mode['width']],
                'height' => $dimensions[$mode['height']],
            ];
        }

        return array_values($rotations);
    }

    private static function compareBoxes($left, $right)
    {
        $leftVolume = self::boxVolume($left);
        $rightVolume = self::boxVolume($right);

        if ($leftVolume < $rightVolume) {
            return -1;
        }

        if ($leftVolume > $rightVolume) {
            return 1;
        }

        $leftLongest = max($left['length'], $left['width'], $left['height']);
        $rightLongest = max($right['length'], $right['width'], $right['height']);

        if ($leftLongest < $rightLongest) {
            return -1;
        }

        if ($leftLongest > $rightLongest) {
            return 1;
        }

        return 0;
    }

    private static function boxVolume($box)
    {
        return $box['length'] * $box['width'] * $box['height'];
    }
}
