<?php
/** Minimal dependencies for invoking the downloaded Checkout::render only. */
namespace Automattic\WooCommerce\Blocks\BlockTypes {
    abstract class AbstractBlock {}
}
namespace {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
    function is_wc_endpoint_url( $endpoint ) { return false; }
    // Woo 10.6 checks fraud protection before its template migrations. Keep it
    // disabled; no service initialization, tracking or network calls are needed.
    function wc_get_container() {
        return new class {
            public function get( $class ) {
                return new class {
                    public function feature_is_enabled() { return false; }
                };
            }
        };
    }
}
