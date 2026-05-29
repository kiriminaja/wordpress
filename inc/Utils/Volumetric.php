<?php
namespace KiriminAjaOfficial\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Volumetric
{
    public static function calculateSmallestBox($items)
    {
        if (empty($items)) {
            return ['length' => 0, 'width' => 0, 'height' => 0];
        }

        $lVert = 0; $wVert = 0; $hVert = 0;
        $lHor = 0; $wHor = 0; $hHor = 0;
        $lSide = 0; $wSide = 0; $hSide = 0;

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $length = (float) ($item['length'] ?? 0);
            $width = (float) ($item['width'] ?? 0);
            $height = (float) ($item['height'] ?? 0);

            $hVert += $height * $qty;
            if ($length > $lVert) { $lVert = $length; }
            if ($width > $wVert) { $wVert = $width; }

            $lHor += $length * $qty;
            if ($height > $hHor) { $hHor = $height; }
            if ($width > $wHor) { $wHor = $width; }

            $wSide += $width * $qty;
            if ($height > $hSide) { $hSide = $height; }
            if ($length > $lSide) { $lSide = $length; }
        }

        $volVert = $lVert * $wVert * $hVert;
        $volHor = $lHor * $wHor * $hHor;
        $volSide = $lSide * $wSide * $hSide;

        if ($volVert <= $volHor && $volVert <= $volSide) {
            return ['length' => $lVert, 'width' => $wVert, 'height' => $hVert];
        }

        if ($volHor <= $volSide) {
            return ['length' => $lHor, 'width' => $wHor, 'height' => $hHor];
        }

        return ['length' => $lSide, 'width' => $wSide, 'height' => $hSide];
    }
}
