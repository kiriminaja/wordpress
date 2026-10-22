<?php
/**
 * Isolated render-filter harness. Set KIRIOF_WP_HTML_API_DIR to a real WordPress
 * wp-includes/html-api directory to exercise the actual tokenizer. WordPress is
 * intentionally not downloaded at test time or vendored into the plugin.
 * The default mode uses a deliberately limited test double, NOT a WP parser.
 */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$realWoo = 'real-woocommerce' === ( $argv[1] ?? '' );
$actual = $realWoo || 'wordpress' === ( $argv[1] ?? '' );
if ( $realWoo && ( ! getenv( 'KIRIOF_WOO_CHECKOUT_RENDERER' ) || ! is_file( getenv( 'KIRIOF_WOO_CHECKOUT_RENDERER' ) ) ) ) {
    echo json_encode( array( 'available' => false ) );
    exit;
}
if ( $actual ) {
    $directory = getenv( 'KIRIOF_WP_HTML_API_DIR' );
    if ( ! $directory || ! is_file( $directory . '/class-wp-html-tag-processor.php' ) ) {
        echo json_encode( array( 'available' => false ) );
        exit;
    }
    // WP 6.8+ decoder depends on wp-includes/class-wp-token-map.php.
    $tokenMap = is_file( $directory . '/class-wp-token-map.php' ) ? $directory . '/class-wp-token-map.php' : dirname( $directory ) . '/class-wp-token-map.php';
    if ( is_file( $tokenMap ) ) { require_once $tokenMap; }
    foreach ( array( 'html5-named-character-references.php', 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-tag-processor.php' ) as $file ) {
        require_once $directory . '/' . $file;
    }
    // WP core's escaping helpers are sufficient for these non-URI identities.
    function wp_kses_uri_attributes() { return array( 'href', 'src', 'action' ); }
    function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ); }
} elseif ( 'unavailable' !== ( $argv[1] ?? '' ) ) {
    /** Limited token test double: only fixture opening tags and attributes. */
    class WP_HTML_Tag_Processor {
        private string $html;
        private string $tag = '';
        private int $offset = 0;
        public function __construct( $html ) { $this->html = $html; }
        public function next_tag( $query ) {
            preg_match_all( '/<([a-z][a-z0-9]*)\b[^>]*>/i', $this->html, $matches, PREG_OFFSET_CAPTURE );
            foreach ( $matches[0] as $index => $match ) {
                $this->tag = $match[0];
                if ( strtoupper( $matches[1][$index][0] ) === $query['tag_name'] && in_array( $query['class_name'], preg_split( '/\s+/', $this->get_attribute( 'class' ) ?? '' ), true ) ) {
                    $this->offset = $match[1];
                    return true;
                }
            }
            return false;
        }
        public function get_attribute( $name ) {
            if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '(?:\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+)))?(?=\s|>)/i', $this->tag, $match ) ) {
                return count( $match ) === 1 ? true : html_entity_decode( $match[1] ?: ( $match[2] ?? '' ) ?: ( $match[3] ?? '' ), ENT_QUOTES );
            }
            return null;
        }
        public function set_attribute( $name, $value ) {
            $updated = substr( $this->tag, 0, -1 ) . ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '">';
            $this->html = substr_replace( $this->html, $updated, $this->offset, strlen( $this->tag ) );
            $this->tag = $updated;
        }
        public function get_updated_html() { return $this->html; }
    }
}
require ABSPATH . 'inc/Controllers/CheckoutController.php';
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
$shippingName = 'woocommerce/checkout-shipping-address-block';
$shipping = '<div class="wp-block-woocommerce-checkout-shipping-address-block"></div>';
if ( 'unavailable' === ( $argv[1] ?? '' ) ) {
    echo json_encode( array( 'input' => $shipping, 'output' => $controller->kiriof_identify_checkout_child( $shipping, array( 'blockName' => $shippingName ) ) ) );
    exit;
}
$summaryName = 'woocommerce/checkout-order-summary-block';
$summary = '<div class="wp-block-woocommerce-checkout-order-summary-block"></div>';
$district = $controller->kiriof_render_district_checkout_block();
$map = $controller->kiriof_render_map_checkout_block();
if ( $realWoo ) {
    require __DIR__ . '/checkout-layout-recovery-woocommerce-stubs.php';
    $source = getenv( 'KIRIOF_WOO_CHECKOUT_RENDERER' );
    require $source;
    $class = new ReflectionClass( \Automattic\WooCommerce\Blocks\BlockTypes\Checkout::class );
    $checkout = $class->newInstanceWithoutConstructor();
    $method = $class->getMethod( 'render' );
    $render = static fn( $content ) => $method->invoke( $checkout, array(), $content, new stdClass() );
    $child = static fn( $suffix, $content = '' ) => '<div class="wp-block-woocommerce-checkout-' . $suffix . '-block" data-block-name="woocommerce/checkout-' . $suffix . '-block">' . $content . '</div>';
    // Include every current migration sentinel. Only shipping lacks identity.
    $fields = $child( 'express-payment' ) . $child( 'contact-information' ) . $child( 'shipping-method' ) . $child( 'pickup-options' );
    $afterShipping = $district . $map . $child( 'billing-address' ) . $child( 'shipping-methods' ) . $child( 'payment' ) . $child( 'additional-information' ) . $child( 'order-note' ) . $child( 'terms' ) . $child( 'actions' );
    $summaryChildren = '';
    foreach ( array( 'cart-items', 'subtotal', 'fee', 'discount', 'coupon-form', 'shipping', 'taxes' ) as $suffix ) {
        $summaryChildren .= $child( 'order-summary-' . $suffix );
    }
    $fullLayout = static fn( $shippingChild ) => '<div class="wp-block-woocommerce-checkout" data-block-name="woocommerce/checkout">' . $child( 'fields', $fields . $shippingChild . $afterShipping ) . $child( 'totals', $child( 'order-summary', $summaryChildren ) ) . '</div>';
    $legacy = $fullLayout( $shipping );
    $normalized = $fullLayout( $controller->kiriof_identify_checkout_child( $shipping, array( 'blockName' => $shippingName ) ) );
    // Count actual class tokens through WordPress, not a model of Woo's regex.
    $counts = static function ( $html ) {
        $result = array();
        foreach ( array( 'root' => 'wp-block-woocommerce-checkout', 'contact' => 'wp-block-woocommerce-checkout-contact-information-block', 'shipping' => 'wp-block-woocommerce-checkout-shipping-address-block', 'actions' => 'wp-block-woocommerce-checkout-actions-block' ) as $key => $className ) {
            $processor = new WP_HTML_Tag_Processor( $html );
            $result[$key] = 0;
            while ( $processor->next_tag( array( 'tag_name' => 'DIV', 'class_name' => $className ) ) ) { ++$result[$key]; }
        }
        return $result;
    };
    $legacyRendered = $render( $legacy );
    $normalizedRendered = $render( $normalized );
    $shell = $district . $map;
    $rootRendered = $render( '<div class="wp-block-woocommerce-checkout"></div>' );
    echo json_encode( array(
        'available' => true, 'processor' => 'WordPress HTML API',
        'renderer' => $class->getName(), 'rendererFile' => $method->getFileName(), 'rendererSha256' => hash_file( 'sha256', $source ),
        'legacyLayout' => $legacy, 'legacyRendered' => $legacyRendered,
        'normalizedLayout' => $normalized, 'normalizedRendered' => $normalizedRendered,
        'inputCounts' => $counts( $legacy ), 'legacyCounts' => $counts( $legacyRendered ),
        'normalizedCounts' => $counts( $normalizedRendered ),
        'shell' => $shell, 'shellRendered' => $render( $shell ), 'shellCounts' => $counts( $render( $shell ) ),
        'rootRendered' => $rootRendered, 'rootCounts' => $counts( $rootRendered ),
    ), JSON_THROW_ON_ERROR );
    exit;
}
$layout = static fn( $child, $orderSummary ) => '<div class="wp-block-woocommerce-checkout" data-block-name="woocommerce/checkout"><div class="wp-block-woocommerce-checkout-fields-block" data-block-name="woocommerce/checkout-fields-block">' . $child . $child . $district . $map . '</div>' . $orderSummary . '</div>';
// This is WooCommerce's legacy empty-root expression, not a narrowed substitute.
$regex = '/<div class="[a-zA-Z0-9_\- ]*wp-block-woocommerce-checkout[a-zA-Z0-9_\- ]*"><\/div>/mi';
$defaultCheckout = '<div data-default-checkout="true">FULL DEFAULT CHECKOUT</div>';
$expand = static function ( $content ) use ( $regex, $defaultCheckout ) {
    return preg_match( $regex, $content, $matches ) ? str_replace( $matches[0], $defaultCheckout, $content ) : $content;
};
$normalizedShipping = $controller->kiriof_identify_checkout_child( $shipping, array( 'blockName' => $shippingName ) );
$normalizedSummary = $controller->kiriof_identify_checkout_child( $summary, array( 'blockName' => $summaryName ) );
$legacyLayout = $layout( $shipping, $summary );
$normalizedLayout = $layout( $normalizedShipping, $normalizedSummary );
$cases = array(
    'shipping' => array( $shipping, array( 'blockName' => $shippingName ) ),
    'summary' => array( $summary, array( 'blockName' => $summaryName ) ),
    'attributes' => array( '<aside data-keep="before"></aside><DIV id="shipping" class="custom wp-block-woocommerce-checkout-shipping-address-block keep" aria-label="A &amp; B" style="color:red" data-extra="unchanged"><span>Child content</span></DIV><div class="other">After</div>', array( 'blockName' => $shippingName ) ),
    'singleQuoted' => array( "<div class='wp-block-woocommerce-checkout-shipping-address-block' title='Keep me'></div>", array( 'blockName' => $shippingName ) ),
    'existing' => array( '<div class="wp-block-woocommerce-checkout-shipping-address-block" data-block-name="custom/protected" data-extra="keep"></div>', array( 'blockName' => $shippingName ) ),
    'emptyIdentity' => array( '<div class="wp-block-woocommerce-checkout-shipping-address-block" data-block-name=""></div>', array( 'blockName' => $shippingName ) ),
    'booleanIdentity' => array( '<div class="wp-block-woocommerce-checkout-shipping-address-block" data-block-name></div>', array( 'blockName' => $shippingName ) ),
    'root' => array( '<div class="wp-block-woocommerce-checkout"></div>', array( 'blockName' => 'woocommerce/checkout' ) ),
    'nonWoo' => array( $shipping, array( 'blockName' => 'kiriminaja-official/checkout-district' ) ),
    'missingName' => array( $shipping, array() ),
    'invalidName' => array( $shipping, array( 'blockName' => array( $shippingName ) ) ),
    'unknown' => array( $shipping, array( 'blockName' => 'woocommerce/checkout-unknown-block' ) ),
    'noDiv' => array( '<section class="wp-block-woocommerce-checkout-shipping-address-block"></section>', array( 'blockName' => $shippingName ) ),
    'substringClass' => array( '<div class="prefix-wp-block-woocommerce-checkout-shipping-address-block"></div>', array( 'blockName' => $shippingName ) ),
    'unrelated' => array( '<div class="wp-block-woocommerce-checkout-order-summary-block"></div>' . $shipping, array( 'blockName' => $shippingName ) ),
    'district' => array( $district, array( 'blockName' => 'kiriminaja-official/checkout-district' ) ),
    'map' => array( $map, array( 'blockName' => 'kiriminaja-official/map-checkout' ) ),
);
$results = array();
foreach ( $cases as $key => [ $input, $block ] ) {
    $output = $controller->kiriof_identify_checkout_child( $input, $block );
    $results[$key] = array( 'input' => $input, 'output' => $output, 'twice' => $controller->kiriof_identify_checkout_child( $output, $block ) );
}
echo json_encode( array(
    'available' => true,
    'processor' => $actual ? 'WordPress HTML API' : 'limited test double',
    'cases' => $results,
    'legacyLayout' => $legacyLayout,
    'legacyExpanded' => $expand( $legacyLayout ),
    'normalizedLayout' => $normalizedLayout,
    'normalizedExpanded' => $expand( $normalizedLayout ),
    'normalizedMatches' => preg_match_all( $regex, $normalizedLayout ),
    'shellMatches' => preg_match_all( $regex, $district . $map ),
    'rootExpanded' => $expand( $cases['root'][0] ),
) );
