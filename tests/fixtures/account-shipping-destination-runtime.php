<?php

namespace KiriminAjaOfficial\Services {
    // Define the typed lookup double before the production autoloader can load it.
    class CheckoutServiceFactory {
        public function districtSearch( $postcode ) {
            $GLOBALS['lookups'][] = $postcode;
            switch ( $GLOBALS['input']['lookup_mode'] ?? 'valid' ) {
                case 'throw': throw new \RuntimeException( 'Offline API failure.' );
                case 'failure': return (object) array( 'status' => 503, 'data' => array() );
                case 'unknown': return (object) array( 'status' => 200, 'data' => array() );
                case 'malformed': return null;
            }
            return (object) array( 'status' => 200, 'data' => $GLOBALS['input']['rows'] ?? array( array( 'id' => 222, 'text' => 'Canonical district' ), array( 'id' => 333, 'text' => 'Other district' ) ) );
        }
    }
}
namespace KiriminAjaOfficial\Base {
    class Enqueue {
        public function register_buyer_checkout_assets( bool $localize = false ): void {
            wp_register_script( 'kiriof-buyer-state', '/assets/buyer/dist/kiriminaja-buyer-state.js', array(), 'test', true );
        }
        public function buyer_checkout_config(): array {
            return array( 'ajaxUrl' => '/wp-admin/admin-ajax.php', 'nonce' => 'lookup-nonce', 'map' => array( 'enabled' => true, 'tiles' => 'https://tiles.example/{z}/{x}/{y}' ), 'i18n' => array( 'selectDistrict' => 'Select Subdistrict', 'district' => 'Subdistrict', 'retry' => 'Try again', 'mapTitle' => 'Pin Location', 'mapOptional' => 'Optional for Express.', 'mapHelp' => 'Move map', 'mapKeyboard' => 'Use arrow keys', 'mapPermission' => 'Allow location', 'mapLocate' => 'Locate me' ) );
        }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'KIRIOF_DIR', ABSPATH );
    define( 'KIRIOF_VERSION', 'test' );
    $input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
    $GLOBALS['input'] = $input;
    $GLOBALS['current_user'] = $input['current_user'] ?? 7;
    $GLOBALS['meta'] = $input['meta'] ?? array();
    foreach ( array( 'writes', 'hooks', 'lookups', 'notices', 'styles', 'scripts', 'localized', 'fields', 'nonces' ) as $key ) { $GLOBALS[$key] = array(); }
    $_POST = $input['post'] ?? array();
    function get_current_user_id() { return $GLOBALS['current_user']; }
    function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
    function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
    function wp_unslash( $value ) { return stripslashes( $value ); }
    function absint( $value ) { return abs( (int) $value ); }
    function __( $text, $domain = '' ) { return $text; }
    function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_html( $text ) { return esc_attr( $text ); }
    function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
    function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
    function get_user_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
    function update_user_meta( $id, $key, $value ) { $GLOBALS['writes'][] = array( $id, $key, $value ); $GLOBALS['meta'][$id][$key] = $value; }
    function delete_user_meta( $id, $key ) { $GLOBALS['writes'][] = array( $id, $key, null ); unset( $GLOBALS['meta'][$id][$key] ); }
    function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority, $args ); }
    function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority, $args ); }
    function wp_verify_nonce( $nonce, $action ) { return 'valid-nonce' === $nonce && 'kiriof_save_account_destination' === $action; }
    function wp_nonce_field( $action, $name ) { $GLOBALS['nonces'][] = array( $action, $name ); echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="valid-nonce" />'; }
    function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['notices'][] = array( $message, $type ); }
    function wc_notice_count( $type ) { return count( array_filter( $GLOBALS['notices'], static fn( $notice ) => $type === $notice[1] ) ); }
    function is_account_page() { return $GLOBALS['input']['account_page'] ?? true; }
    function is_wc_endpoint_url( $endpoint ) { return 'edit-address' === $endpoint && ( $GLOBALS['input']['edit_address'] ?? true ); }
    function get_query_var( $key ) { return $GLOBALS['input']['endpoint_type'] ?? 'shipping'; }
    function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/kiriminaja/'; }
    function wp_enqueue_style( ...$args ) { $GLOBALS['styles'][] = $args; }
    function wp_script_is( $handle, $status = 'registered' ) { return in_array( $handle, array_column( $GLOBALS['scripts'], 0 ), true ); }
    function wp_register_script( ...$args ) { $GLOBALS['scripts'][] = $args; }
    function wp_enqueue_script( ...$args ) { $GLOBALS['scripts'][] = $args; }
    function wp_localize_script( ...$args ) { $GLOBALS['localized'][] = $args; }
    function woocommerce_form_field( $key, $args, $value ) {
        $GLOBALS['fields'][] = array( $key, $args, $value );
        echo '<p class="form-row form-row-wide"><label for="' . esc_attr( $args['id'] ) . '">' . esc_html( $args['label'] ) . '</label><select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '"';
        foreach ( $args['custom_attributes'] as $name => $attribute ) { echo ' ' . esc_attr( $name ) . '="' . esc_attr( $attribute ) . '"'; }
        echo '>';
        foreach ( $args['options'] as $id => $label ) { echo '<option value="' . esc_attr( $id ) . '"' . ( (string) $id === (string) $value ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></p>';
    }
    final class AccountDestinationSession {
        public array $values;
        public function __construct( array $values ) { $this->values = $values; }
        public function get( $key, $default = null ) { return array_key_exists( $key, $this->values ) ? $this->values[$key] : $default; }
        public function set( $key, $value ) { $this->values[$key] = $value; }
        public function __unset( $key ) { unset( $this->values[$key] ); }
    }
    final class AccountDestinationCustomer {
        public function get_id() { return $GLOBALS['input']['customer_id'] ?? 7; }
        public function get_shipping_address_1() { return $GLOBALS['candidate']['address_1']; }
        public function get_shipping_address_2() { return $GLOBALS['candidate']['address_2']; }
        public function get_shipping_city() { return $GLOBALS['candidate']['city']; }
        public function get_shipping_state() { return $GLOBALS['candidate']['state']; }
        public function get_shipping_postcode() { return $GLOBALS['candidate']['postcode']; }
        public function get_shipping_country() { return $GLOBALS['candidate']['country']; }
    }
    $GLOBALS['candidate'] = $input['candidate'] ?? array( 'address_1' => 'Main street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
    $GLOBALS['wc'] = (object) array( 'customer' => new AccountDestinationCustomer(), 'session' => new AccountDestinationSession( $input['session'] ?? array() ) );
    function WC() { return $GLOBALS['wc']; }
    require ABSPATH . 'inc/Services/BuyerDestination.php';
    require ABSPATH . 'inc/Services/CustomerDistrictService.php';
    require ABSPATH . 'inc/Services/CustomerShippingDestinationService.php';
    require ABSPATH . 'inc/Controllers/AccountShippingDestinationController.php';
    require ABSPATH . 'inc/Controllers/AccountAddressController.php';
    $service = new \KiriminAjaOfficial\Services\CustomerShippingDestinationService();
    $controller = new \KiriminAjaOfficial\Controllers\AccountShippingDestinationController( $service, new \KiriminAjaOfficial\Services\CheckoutServiceFactory() );
    $legacy = new \KiriminAjaOfficial\Controllers\AccountAddressController();
    $result = array( 'steps' => array() );
    $validated = new \ReflectionProperty( $controller, 'validated' );
    ob_start();
    foreach ( $input['operations'] ?? array( 'validate', 'saved' ) as $operation ) {
        if ( is_array( $operation ) ) { $_POST = $operation['post']; $operation = 'validate'; }
        switch ( $operation ) {
            case 'register': $controller->register(); break;
            case 'form': $controller->form(); break;
            case 'layout': $result['layout'] = $controller->shippingFields( $input['address_fields'] ?? array(), $input['type'] ?? 'shipping' ); break;
            case 'badges': $controller->badges( $input['type'] ?? 'shipping' ); break;
            case 'assets': $controller->assets(); break;
            case 'validate': $controller->validate( $input['user_id'] ?? 7, $input['type'] ?? 'shipping', array(), new AccountDestinationCustomer() ); break;
            case 'legacy_fields': $result['legacy_fields'] = $legacy->addDistrictFields( array( 'shipping_postcode' => array( 'value' => '12345' ), 'shipping_kiriof_destination_area' => array(), '_wc_shipping/kiriminaja-official/kiriof_destination_area' => array() ), 'shipping' ); break;
            case 'legacy_validate': $legacy->validateDistrict( 7, 'shipping', array(), new AccountDestinationCustomer() ); break;
            case 'legacy_saved': $legacy->saveDistrict( 7, 'shipping' ); break;
            case 'persist_address': foreach ( $GLOBALS['candidate'] as $key => $value ) { $GLOBALS['meta'][$input['user_id'] ?? 7]['shipping_' . $key] = $value; } break;
            case 'other_error': wc_add_notice( 'Woo address validation failed.', 'error' ); break;
            case 'clear_errors': $GLOBALS['notices'] = array(); break;
            case 'saved': $controller->saved( $input['user_id'] ?? 7, $input['type'] ?? 'shipping', array(), new AccountDestinationCustomer() ); break;
        }
        $result['steps'][] = array( 'operation' => $operation, 'writes' => $GLOBALS['writes'], 'validated' => $validated->getValue( $controller ), 'session' => WC()->session->values );
    }
    $result['html'] = ob_get_clean();
    $result['destination'] = $service->get( $input['user_id'] ?? 7 );
    $result['session'] = WC()->session->values;
    foreach ( array( 'meta', 'writes', 'hooks', 'lookups', 'notices', 'styles', 'scripts', 'localized', 'fields', 'nonces' ) as $key ) { $result[$key] = $GLOBALS[$key]; }
    echo json_encode( $result, JSON_THROW_ON_ERROR );
}
