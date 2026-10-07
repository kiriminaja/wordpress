<?php

/**
 * Read the actual legacy assets in their WordPress dependency order.
 * Structural regression tests must inspect all modules, not just the ready entry.
 * This is source inspection only: the scripts remain separate browser globals.
 */
function kiriof_legacy_checkout_source(): string
{
    $root = dirname(__DIR__, 2);
    $paths = array(
        'assets/buyer/js/checkout/state.js',
        'assets/buyer/js/checkout/blocks-compatibility.js',
        'assets/buyer/js/checkout/classic-district.js',
        'assets/buyer/js/checkout/shipping-payment.js',
        'assets/buyer/js/form-billing-address.js',
    );
    $sources = array();
    foreach ($paths as $path) {
        $source = file_get_contents($root . '/' . $path);
        if ($source === false) {
            throw new RuntimeException('Cannot read legacy checkout module: ' . $path);
        }
        $sources[] = $source;
    }
    return implode("\n", $sources);
}
