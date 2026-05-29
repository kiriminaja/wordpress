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
        $totalItemVolume = 0;

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $length = max(0, (float) ($item['length'] ?? 0));
            $width = max(0, (float) ($item['width'] ?? 0));
            $height = max(0, (float) ($item['height'] ?? 0));
            $totalItemVolume += $length * $width * $height * $qty;

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
            return self::conservativeBox(['length' => $lVert, 'width' => $wVert, 'height' => $hVert], $totalItemVolume);
        }

        if ($volHor <= $volSide) {
            return self::conservativeBox(['length' => $lHor, 'width' => $wHor, 'height' => $hHor], $totalItemVolume);
        }

        return self::conservativeBox(['length' => $lSide, 'width' => $wSide, 'height' => $hSide], $totalItemVolume);
    }

    private static function conservativeBox($box, $minimumVolume)
    {
        $length = max(0, (float) ($box['length'] ?? 0));
        $width = max(0, (float) ($box['width'] ?? 0));
        $height = max(0, (float) ($box['height'] ?? 0));
        $boxVolume = $length * $width * $height;

        if ($minimumVolume <= 0 || $boxVolume >= $minimumVolume) {
            return ['length' => $length, 'width' => $width, 'height' => $height];
        }

        if ($length >= $width && $length >= $height && $width > 0 && $height > 0) {
            $length = $minimumVolume / ($width * $height);
        } elseif ($width >= $height && $length > 0 && $height > 0) {
            $width = $minimumVolume / ($length * $height);
        } elseif ($length > 0 && $width > 0) {
            $height = $minimumVolume / ($length * $width);
        }

        return ['length' => $length, 'width' => $width, 'height' => $height];
    }
}
