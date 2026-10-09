<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\SettingRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Final Store API validation. Never changes the amount presented to the buyer. */
final class ExpressCheckoutValidationService {
    public const META_KEY = '_kiriof_express_validated';
    private SettingRepository $settings;
    private CheckoutServiceFactory $factory;

    public function __construct( SettingRepository $settings, CheckoutServiceFactory $factory ) {
        $this->settings = $settings;
        $this->factory = $factory;
    }

    /** Returns only durable, JSON-safe calculation data, never API payloads or secrets. */
    public function validate( $order, int $district, string $payment, bool $insurance ): array {
        $lines = array();
        foreach ( $order->get_items( 'shipping' ) as $line ) {
            $method = (string) $line->get_method_id();
            if ( 'kiriminaja-official' === $method || 0 === strpos( $method, 'kiriminaja-official_' ) || 0 === strpos( $method, 'kiriminaja-official:' ) ) {
                $lines[] = $line;
            }
        }
        if ( 1 !== count( $lines ) || 1 !== count( $order->get_items( 'shipping' ) ) || $district < 1 ) {
            $this->fail();
        }
        $line = $lines[0];
        $service = (string) $line->get_meta( 'kiriof_rate_service', true );
        $type = (string) $line->get_meta( 'kiriof_rate_service_type', true );
        if ( '' === $service || '' === $type || ! CourierServiceCatalog::isSupportedCourier( $service, array(), 'express' ) || ! $this->settings->isCourierServiceEnabled( $service, $type ) ) {
            $this->fail();
        }
        foreach ( array( 'api_key' ) as $key ) {
            $setting = $this->settings->getSettingByKey( $key );
            if ( empty( $setting->value ) ) {
                $this->fail();
            }
        }
        $expedition = $service . '_' . $type;
        $rate_id = 'kiriminaja-official_' . $expedition;
        if ( 'kiriminaja-official' !== $line->get_method_id() && $rate_id !== $line->get_method_id() ) {
            $this->fail();
        }
        $packages = WC()->shipping()->get_packages();
        $chosen = WC()->session->get( 'chosen_shipping_methods', array() );
        if ( 1 !== count( $packages ) ) {
            $this->fail();
        }
        $package_key = array_key_first( $packages );
        $package = $packages[$package_key];
        foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
            $getter = 'get_shipping_' . $field;
            if ( ! is_callable( array( $order, $getter ) ) || ! array_key_exists( $field, $package['destination'] ?? array() ) || (string) $order->$getter() !== (string) $package['destination'][$field] ) {
                $this->fail();
            }
        }
        $rate = $package['rates'][$rate_id] ?? null;
        if ( ! $rate || ( $chosen[$package_key] ?? '' ) !== $rate_id || $rate->get_id() !== $rate_id || 'kiriminaja-official' !== $rate->get_method_id() || (int) $line->get_instance_id() !== (int) $rate->get_instance_id() ) {
            $this->fail();
        }
        $zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
        $active = false;
        foreach ( $zone->get_shipping_methods( true ) as $method ) {
            if ( 'kiriminaja-official' === $method->id && (int) $method->get_instance_id() === (int) $rate->get_instance_id() && 'yes' === $method->enabled ) {
                $active = true;
            }
        }
        $meta = $rate->get_meta_data();
        foreach ( array( 'kiriof_rate_service', 'kiriof_rate_service_type', 'kiriof_rate_cod_available' ) as $key ) {
            if ( ! isset( $meta[$key] ) || (string) $line->get_meta( $key, true ) !== (string) $meta[$key] ) {
                $this->fail();
            }
        }
        if ( ! $active || ! $this->equal( $line->get_total(), $rate->get_cost() ) || ( 'cod' === $payment && 'yes' !== $meta['kiriof_rate_cod_available'] ) ) {
            $this->fail();
        }
        // Duplicate logistics fees are rejected before any pricing call.
        $fees = array( 'insurance' => array(), 'cod_fee' => array() );
        foreach ( $order->get_items( 'fee' ) as $fee ) {
            $kind = (string) $fee->get_meta( '_kiriof_fee_type', true );
            if ( '' === $kind ) {
                if ( in_array( $fee->get_name(), array( 'Insurance', __( 'Insurance', 'kiriminaja-official' ) ), true ) ) { $kind = 'insurance'; }
                if ( in_array( $fee->get_name(), array( 'COD Fee', __( 'COD Fee', 'kiriminaja-official' ) ), true ) ) { $kind = 'cod_fee'; }
            }
            if ( isset( $fees[$kind] ) ) { $fees[$kind][] = (float) $fee->get_total(); }
        }
        if ( count( $fees['insurance'] ) > 1 || count( $fees['cod_fee'] ) > 1 ) { $this->fail(); }
        // A present package origin is authoritative, including malformed values.
        if ( array_key_exists( 'origin', $package ) ) {
            $origin = $package['origin'];
        } else {
            $locations = new ShipmentLocationService( null, $this->settings );
            $origin = $locations->locationToOrigin( $locations->getDefaultLocation() );
        }
        $origin = self::normalizeOriginSnapshot( $origin );
        $response = $this->factory->calculation( array(
            'destination_area_id' => $district,
            'destination_postcode' => (string) ( $package['destination']['postcode'] ?? '' ),
            'expedition' => $expedition,
            'is_insurance' => $insurance,
            'is_cod' => 'cod' === $payment,
            'wc_cart_contents' => WC()->cart->get_cart(),
            'blocks_quote_validation' => true,
            'origin' => $origin,
        ) )->call();
        if ( 200 !== $response->status || empty( $response->data['calculation_result'] ) || empty( $response->data['carts_attribute'] ) ) { $this->fail(); }
        $calc = $response->data['calculation_result'];
        $selected = $calc['selected_expedition'] ?? null;
        if ( ! is_object( $selected ) || $service !== (string) ( $selected->service ?? '' ) || $type !== (string) ( $selected->service_type ?? '' ) ) { $this->fail(); }
        if ( 'cod' === $payment && ! in_array( $selected->cod ?? null, array( true, 1, '1', 'yes', 'true' ), true ) && ! in_array( $selected->setting->cod ?? null, array( true, 1, '1', 'yes', 'true' ), true ) ) { $this->fail(); }
        $insurance = $insurance || ! empty( $selected->force_insurance ) || (float) ( $calc['insurance_amt'] ?? 0 ) > 0;
        $adjusted = ( new ShippingDiscountCouponService() )->getAdjustedRatePricing( $selected, (float) $calc['ongkir_fee_amt'] );
        $shipping = (float) $adjusted['cost'];
        if ( ! $this->equal( $line->get_total(), $shipping ) ) { $this->fail(); }
        foreach ( array( 'insurance' => 'insurance_amt', 'cod_fee' => 'cod_amt' ) as $kind => $key ) {
            $expected = (float) ( $calc[$key] ?? 0 );
            if ( ! $this->equal( array_sum( $fees[$kind] ), $expected ) || ( $expected <= 0 && count( $fees[$kind] ) > 0 ) ) { $this->fail(); }
        }
        $items_total = 0.0;
        foreach ( $order->get_items( 'line_item' ) as $item ) { $items_total += (float) $item->get_total(); }
        if ( ! $this->equal( $items_total, $calc['cart_total_after_discount'] ?? $calc['cart_total_amt'] ) ) { $this->fail(); }
        // Tax and unrelated merchant fees remain WooCommerce's responsibility.
        $other_fees = (float) $order->get_total_fees() - array_sum( $fees['insurance'] ) - array_sum( $fees['cod_fee'] );
        $expected_total = $items_total + $shipping + (float) $calc['insurance_amt'] + (float) $calc['cod_amt'] + $other_fees + (float) $order->get_total_tax();
        if ( ! $this->equal( $order->get_total(), $expected_total ) ) { $this->fail(); }
        $calc['ongkir_fee_amt'] = $shipping;
        $calc['calc_total_amt'] = $items_total + $shipping + (float) $calc['insurance_amt'] + (float) $calc['cod_amt'];
        $calc['discount_amt'] = max( 0, (float) $calc['ongkir_fee_raw'] - $shipping );
        $calc['selected_expedition'] = array_intersect_key( (array) $selected, array_flip( array( 'service', 'service_type', 'service_name', 'cost', 'discount_amount', 'discount_percentage', 'insurance', 'force_insurance', 'cod' ) ) );
        $calc = array_intersect_key( $calc, array_flip( array( 'cart_total_amt', 'cart_total_before_discount', 'cart_total_after_discount', 'woo_discount_amount', 'woo_discount_description', 'cod_amt', 'insurance_amt', 'ongkir_fee_amt', 'ongkir_fee_raw', 'calc_total_amt', 'selected_expedition', 'discount_amt', 'discount_percentage' ) ) );
        $attributes = array_intersect_key( (array) $response->data['carts_attribute'], array_flip( array( 'weight', 'length', 'width', 'height', 'item_value' ) ) );
        // Whitelist the calculation only; cart product objects and pricing payloads are excluded.
        $data = json_decode( wp_json_encode( array( 'calculation_result' => $calc, 'carts_attribute' => $attributes ) ), true );
        if ( ! is_array( $data ) ) { $this->fail(); }
        return array( 'version' => 1, 'rate_id' => $rate_id, 'instance_id' => (int) $rate->get_instance_id(), 'expedition' => $expedition, 'payment_method' => $payment, 'destination_id' => $district, 'is_insurance' => $insurance, 'validated_calculation' => $data, 'origin_snapshot' => $origin );
    }

    /** Courier address/contact fields are required; Express does not require coordinates. */
    public static function normalizeOriginSnapshot( $origin ): array {
        if ( ! is_array( $origin ) || ! isset( $origin['origin_sub_district_id'] )
            || ! is_scalar( $origin['origin_sub_district_id'] )
            || ! ctype_digit( (string) $origin['origin_sub_district_id'] )
            || (int) $origin['origin_sub_district_id'] < 1 ) {
            throw new \InvalidArgumentException( 'Invalid Express checkout origin.' );
        }
        foreach ( array( 'origin_name', 'origin_phone', 'origin_address' ) as $field ) {
            if ( ! isset( $origin[$field] ) || ! is_scalar( $origin[$field] ) || '' === trim( (string) $origin[$field] ) ) {
                throw new \InvalidArgumentException( 'Invalid Express checkout origin.' );
            }
        }
        $snapshot = array();
        foreach ( array( 'id', 'location_id', 'location_name', 'origin_name', 'origin_phone', 'origin_address', 'origin_address_2', 'origin_sub_district', 'origin_city', 'origin_state', 'origin_country', 'origin_sub_district_id', 'origin_zip_code', 'origin_latitude', 'origin_longitude' ) as $field ) {
            if ( isset( $origin[$field] ) && ! is_scalar( $origin[$field] ) ) {
                throw new \InvalidArgumentException( 'Invalid Express checkout origin.' );
            }
            $snapshot[$field] = in_array( $field, array( 'id', 'location_id', 'origin_sub_district_id' ), true )
                ? (int) ( $origin[$field] ?? 0 ) : (string) ( $origin[$field] ?? '' );
        }
        return $snapshot;
    }

    private function equal( $left, $right ): bool {
        return is_numeric( $left ) && is_numeric( $right ) && is_finite( (float) $left ) && is_finite( (float) $right ) && abs( (float) $left - (float) $right ) < 0.005;
    }

    private function fail(): void {
        throw new \InvalidArgumentException( 'Invalid Express checkout quote.' );
    }
}
