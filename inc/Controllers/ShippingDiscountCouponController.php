<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use KiriminAjaOfficial\Services\ShippingDiscountCouponService;
use KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService;

class ShippingDiscountCouponController {
    private const COUPON_TYPE = 'kiriof_shipping_discount';
    private const META_REGIONS = '_kiriof_coupon_regions';
    private const META_COURIERS = '_kiriof_coupon_couriers';

    public function register() {
        add_filter( 'woocommerce_coupon_discount_types', array( $this, 'registerDiscountType' ) );
        add_filter( 'woocommerce_coupon_is_valid_for_cart', array( $this, 'validateShippingCouponForCart' ), 20, 2 );
        add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'filterShippingMethodLabel' ), 20, 2 );
        add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'renderUsageRestrictionFields' ), 20, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'saveCouponOptions' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueCouponAdminAssets' ) );
        add_action( 'wp_ajax_kiriof_refresh_coupon_regions', array( $this, 'refreshRegionCacheAjax' ) );
        add_action( 'wp_ajax_kiriof_get_coupon_region_cities', array( $this, 'getCitiesByProvinceAjax' ) );
    }

    public function registerDiscountType( $types ) {
        $types[ self::COUPON_TYPE ] = __( 'Shipping Discount', 'kiriminaja-official' );
        return $types;
    }

    public function validateShippingCouponForCart( $valid, $coupon ) {
        $service = new ShippingDiscountCouponService();
        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return $valid;
        }

        if ( ! $valid ) {
            return false;
        }

        $validation = $service->validateCouponForCart( $coupon );
        if ( $validation['valid'] ) {
            return true;
        }

        $message = $validation['message'] ?? '';
        if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
            if ( ! function_exists( 'wc_has_notice' ) || ! wc_has_notice( $message, 'error' ) ) {
                wc_add_notice( $message, 'error' );
            }
        }

        return false;
    }

    public function filterShippingMethodLabel( $label, $method ) {
        return ( new ShippingDiscountCouponService() )->formatShippingMethodLabel( (string) $label, $method );
    }

    public function renderUsageRestrictionFields( $coupon_id = 0, $coupon = null ) {
        $coupon_id = absint( $coupon_id );
        $savedRegions = $this->getSavedRegions( $coupon_id );
        $savedCouriers = $this->getSavedCouriers( $coupon_id );
        $regionRepo = new ShippingDiscountRegionRepository();

        if ( $regionRepo->getProvinceCount() < 1 ) {
            ( new ShippingDiscountRegionCacheService() )->refreshAll();
        }

        $provinces = $regionRepo->getProvinces();
        $couriers = $this->getCourierOptions();
        $isCacheStale = $regionRepo->isCacheStale();

        echo '<div class="options_group show_if_' . esc_attr( self::COUPON_TYPE ) . ' kiriof-shipping-discount-options">';

        echo '<p class="form-field">';
        echo '<label for="kiriof_coupon_regions_builder">' . esc_html__( 'Allowed Regions', 'kiriminaja-official' ) . '</label>';
        echo '<span class="wrap">';
        echo '<button type="button" class="button kiriof-add-region-button">' . esc_html__( 'Add Region', 'kiriminaja-official' ) . '</button> ';
        echo '<button type="button" class="button-link kiriof-refresh-regions-button">' . esc_html__( 'Refresh Region Data', 'kiriminaja-official' ) . '</button>';
        echo '</span>';
        echo '<span class="description">' . esc_html__( 'Leave empty to allow all shipping destinations.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';

        echo '<input type="hidden" id="kiriof_coupon_regions" name="' . esc_attr( self::META_REGIONS ) . '" value="' . esc_attr( wp_json_encode( $savedRegions ) ) . '" />';

        echo '<div class="kiriof-region-builder" hidden>';
        echo '<p class="form-field">';
        echo '<label for="kiriof_coupon_region_mode">' . esc_html__( 'Selection Mode', 'kiriminaja-official' ) . '</label>';
        echo '<select id="kiriof_coupon_region_mode" class="short">';
        echo '<option value="all_province">' . esc_html__( 'All Province', 'kiriminaja-official' ) . '</option>';
        echo '<option value="all_city_in_province">' . esc_html__( 'All City inside Province', 'kiriminaja-official' ) . '</option>';
        echo '<option value="specific_city">' . esc_html__( 'Specific City', 'kiriminaja-official' ) . '</option>';
        echo '</select>';
        echo '</p>';

        echo '<p class="form-field kiriof-region-province-field">';
        echo '<label for="kiriof_coupon_region_province">' . esc_html__( 'Province', 'kiriminaja-official' ) . '</label>';
        echo '<select id="kiriof_coupon_region_province" class="short">';
        echo '<option value="">' . esc_html__( 'Select a province', 'kiriminaja-official' ) . '</option>';
        foreach ( $provinces as $province ) {
            echo '<option value="' . esc_attr( $province->id ) . '">' . esc_html( $province->name ) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        echo '<p class="form-field kiriof-region-city-field" hidden>';
        echo '<label for="kiriof_coupon_region_cities">' . esc_html__( 'Cities', 'kiriminaja-official' ) . '</label>';
        echo '<select id="kiriof_coupon_region_cities" class="wc-enhanced-select" multiple="multiple" style="width: 50%;"></select>';
        echo '<span class="description">' . esc_html__( 'Choose one or more cities inside the selected province.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';

        echo '<p class="form-field">';
        echo '<button type="button" class="button button-secondary kiriof-confirm-region-button">' . esc_html__( 'Save Region', 'kiriminaja-official' ) . '</button>';
        echo '</p>';
        echo '</div>';

        echo '<div class="kiriof-region-chip-list">';
        foreach ( $savedRegions as $region ) {
            echo '<span class="kiriof-region-chip">' . esc_html( $this->formatRegionLabel( $region ) ) . '<button type="button" class="kiriof-remove-chip" aria-label="' . esc_attr__( 'Remove region', 'kiriminaja-official' ) . '">&times;</button></span>';
        }
        echo '</div>';

        echo '<p class="form-field">';
        echo '<label for="kiriof_coupon_couriers">' . esc_html__( 'Allowed Couriers', 'kiriminaja-official' ) . '</label>';
        echo '<select id="kiriof_coupon_couriers" name="' . esc_attr( self::META_COURIERS ) . '[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;">';
        foreach ( $couriers as $courier ) {
            echo '<option value="' . esc_attr( $courier['id'] ) . '" ' . selected( in_array( $courier['id'], $savedCouriers, true ), true, false ) . '>' . esc_html( $courier['text'] ) . '</option>';
        }
        echo '</select>';
        echo '<span class="description">' . esc_html__( 'Leave empty to apply the shipping discount to all couriers.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';

        if ( $isCacheStale ) {
            echo '<p class="form-field"><span class="description">' . esc_html__( 'Region data is older than 24 hours. It will be refreshed automatically in the background.', 'kiriminaja-official' ) . '</span></p>';
        }

        echo '</div>';
    }

    public function saveCouponOptions( $post_id, $coupon ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $regions = isset( $_POST[ self::META_REGIONS ] )
            ? sanitize_text_field( wp_unslash( $_POST[ self::META_REGIONS ] ) )
            : '[]';
        $couriers = isset( $_POST[ self::META_COURIERS ] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ self::META_COURIERS ] ) )
            : array();

        update_post_meta( $post_id, self::META_REGIONS, wp_json_encode( $this->normalizeRegions( $regions ) ) );
        update_post_meta( $post_id, self::META_COURIERS, array_values( array_unique( array_filter( $couriers ) ) ) );
    }

    public function enqueueCouponAdminAssets( $hook ) {
        if ( ! $this->isCouponAdminScreen() ) {
            return;
        }

        $regionRepo = new ShippingDiscountRegionRepository();
        $currentType = '';
        if ( isset( $_GET['post'] ) ) {
            $coupon = new \WC_Coupon( absint( $_GET['post'] ) );
            $currentType = $coupon->get_discount_type();
        }

        wp_enqueue_style(
            'kiriof-coupon-admin-style',
            KIRIOF_URL . 'assets/admin/css/kj-coupon-admin.css',
            array( 'select2' ),
            KIRIOF_VERSION
        );
        wp_enqueue_script(
            'kiriof-coupon-admin-script',
            KIRIOF_URL . 'assets/admin/js/kj-coupon-admin.js',
            array( 'jquery', 'select2' ),
            KIRIOF_VERSION,
            true
        );

        wp_localize_script(
            'kiriof-coupon-admin-script',
            'kiriofCouponAdmin',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( KIRIOF_NONCE ),
                'discountType' => self::COUPON_TYPE,
                'isCacheStale' => $regionRepo->isCacheStale(),
                'strings' => array(
                    'allProvinceLabel' => __( 'All provinces in Indonesia', 'kiriminaja-official' ),
                    'allCitiesLabel' => __( 'All cities in %s', 'kiriminaja-official' ),
                    'specificCityLabel' => __( '%1$s, %2$s', 'kiriminaja-official' ),
                    'chooseProvince' => __( 'Please choose a province first.', 'kiriminaja-official' ),
                    'chooseCity' => __( 'Please choose at least one city.', 'kiriminaja-official' ),
                    'cacheRefreshing' => __( 'Refreshing region data…', 'kiriminaja-official' ),
                    'cacheRefreshed' => __( 'Region data refreshed.', 'kiriminaja-official' ),
                    'cacheRefreshFailed' => __( 'Failed to refresh region data.', 'kiriminaja-official' ),
                    'removeRegion' => __( 'Remove region', 'kiriminaja-official' ),
                ),
                'currentType' => $currentType,
            )
        );
    }

    public function refreshRegionCacheAjax() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'kiriminaja-official' ) ), 403 );
        }

        $service = ( new ShippingDiscountRegionCacheService() )->refreshAll();
        if ( 200 !== $service->status ) {
            wp_send_json_error( array( 'message' => $service->message ), 400 );
        }

        wp_send_json_success( $service->data );
    }

    public function getCitiesByProvinceAjax() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'kiriminaja-official' ) ), 403 );
        }

        $provinceId = isset( $_POST['province_id'] ) ? absint( wp_unslash( $_POST['province_id'] ) ) : 0;
        $repo = new ShippingDiscountRegionRepository();
        $cities = $repo->getCitiesByProvinceId( $provinceId );

        if ( empty( $cities ) && $provinceId > 0 ) {
            ( new ShippingDiscountRegionCacheService() )->refreshProvinceCities( $provinceId );
            $cities = $repo->getCitiesByProvinceId( $provinceId );
        }

        wp_send_json_success(
            array_map(
                function ( $city ) {
                    return array(
                        'id' => (int) $city->id,
                        'text' => (string) $city->name,
                    );
                },
                $cities
            )
        );
    }

    private function isCouponAdminScreen(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        return $screen && 'shop_coupon' === $screen->post_type;
    }

    private function getSavedRegions( int $couponId ): array {
        $raw = get_post_meta( $couponId, self::META_REGIONS, true );
        if ( empty( $raw ) ) {
            return array();
        }

        return $this->normalizeRegions( $raw );
    }

    private function getSavedCouriers( int $couponId ): array {
        $raw = get_post_meta( $couponId, self::META_COURIERS, true );
        if ( is_array( $raw ) ) {
            return array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            return array_values( array_filter( array_map( 'sanitize_text_field', explode( ',', $raw ) ) ) );
        }

        return array();
    }

    private function normalizeRegions( $raw ): array {
        $regions = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $regions ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $regions as $region ) {
            if ( ! is_array( $region ) ) {
                continue;
            }

            $type = sanitize_key( $region['type'] ?? '' );
            if ( ! in_array( $type, array( 'all_province', 'all_city_in_province', 'specific_city' ), true ) ) {
                continue;
            }

            $item = array(
                'type' => $type,
                'province_id' => sanitize_text_field( (string) ( $region['province_id'] ?? '' ) ),
                'province_name' => sanitize_text_field( (string) ( $region['province_name'] ?? '' ) ),
            );

            if ( 'specific_city' === $type ) {
                $item['city_id'] = sanitize_text_field( (string) ( $region['city_id'] ?? '' ) );
                $item['city_name'] = sanitize_text_field( (string) ( $region['city_name'] ?? '' ) );
            }

            $normalized[] = $item;
        }

        return array_values( $normalized );
    }

    private function getCourierOptions(): array {
        $service = ( new KiriminajaApiService() )->get_couriers();
        if ( 200 !== $service->status || empty( $service->data ) ) {
            return array();
        }

        $options = array();
        foreach ( (array) $service->data as $courier ) {
            $courier = (object) $courier;
            if ( empty( $courier->code ) || empty( $courier->name ) ) {
                continue;
            }

            $label = (string) $courier->name;
            if ( ! empty( $courier->type ) ) {
                $label .= ' (' . (string) $courier->type . ')';
            }

            $options[] = array(
                'id' => (string) $courier->code,
                'text' => $label,
            );
        }

        return $options;
    }

    private function formatRegionLabel( array $region ): string {
        if ( 'all_province' === $region['type'] ) {
            return __( 'All provinces in Indonesia', 'kiriminaja-official' );
        }

        if ( 'all_city_in_province' === $region['type'] ) {
            return sprintf( __( 'All cities in %s', 'kiriminaja-official' ), $region['province_name'] );
        }

        return sprintf( __( '%1$s, %2$s', 'kiriminaja-official' ), $region['city_name'] ?? '', $region['province_name'] );
    }
}