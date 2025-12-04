<?php
namespace KiriminAjaOfficial\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DimensionConverter
{
    /**
     * Convert dimension to centimeters based on WooCommerce dimension unit setting.
     *
     * @param float|int $dimension The dimension value to convert.
     * @param string|null $unit The unit of the dimension (m, cm, mm, in, yd). If null, will use WooCommerce setting.
     * @return float Converted dimension in centimeters.
     */
    public static function toCm($dimension, $unit = null)
    {
        if ($unit === null) {
            $unit = get_option('woocommerce_dimension_unit') ?? 'cm';
        }
        switch (strtolower($unit)) {
            case 'm':
                return $dimension * 100;
            case 'cm':
                return $dimension;
            case 'mm':
                return $dimension * 0.1;
            case 'in':
                return $dimension * 2.54;
            case 'yd':
                return $dimension * 91.44;
            default:
                return $dimension;
        }
    }
}