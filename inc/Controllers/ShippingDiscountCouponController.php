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
    private const META_REGIONS = '_kiriof_coupon_regions';
    private const META_COURIERS = '_kiriof_coupon_couriers';
    private const META_COMBINATIONS = '_kiriof_coupon_combinations';

    public function register() {
        add_filter( 'woocommerce_coupon_discount_types', array( $this, 'registerDiscountType' ) );
        add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'registerCouponDataTabs' ) );
        add_filter( 'woocommerce_coupon_is_valid_for_cart', array( $this, 'validateShippingCouponForCart' ), 20, 2 );
        add_filter( 'manage_edit-shop_coupon_columns', array( $this, 'registerCouponListColumns' ) );
        add_action( 'manage_shop_coupon_posts_custom_column', array( $this, 'renderCouponListColumn' ), 10, 2 );
        add_action( 'woocommerce_coupon_data_panels', array( $this, 'renderCouponDataPanels' ) );
        add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'renderUsageRestrictionFields' ), 20, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'saveCouponOptions' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueCouponAdminAssets' ) );
        add_action( 'wp_ajax_kiriof_refresh_coupon_regions', array( $this, 'refreshRegionCacheAjax' ) );
        add_action( 'wp_ajax_kiriof_get_coupon_region_status', array( $this, 'getRegionCacheStatusAjax' ) );
        add_action( 'wp_ajax_kiriof_get_coupon_region_cities', array( $this, 'getCitiesByProvinceAjax' ) );
        add_action( 'wp_ajax_kiriof_get_current_shipping_discount', array( $this, 'getCurrentShippingDiscountAjax' ) );
        add_action( 'wp_ajax_nopriv_kiriof_get_current_shipping_discount', array( $this, 'getCurrentShippingDiscountAjax' ) );
        add_action( ShippingDiscountRegionCacheService::CRON_HOOK, array( $this, 'refreshRegionCacheCron' ) );
    }

    public function registerDiscountType( $types ) {
        $types[ ShippingDiscountCouponService::FIXED_COUPON_TYPE ] = __( 'Fixed shipping discount', 'kiriminaja-official' );
        $types[ ShippingDiscountCouponService::PERCENTAGE_COUPON_TYPE ] = __( 'Percentage shipping discount', 'kiriminaja-official' );
        return $types;
    }

    public function registerCouponDataTabs( $tabs ) {
        $tabs['kiriof_area_restrictions'] = array(
            'label' => __( 'Area Restrictions', 'kiriminaja-official' ),
            'target' => 'kiriof_area_restrictions_coupon_data',
            'class' => $this->getShippingCouponVisibilityClasses(),
        );

        $tabs['kiriof_courier_restrictions'] = array(
            'label' => __( 'Courier Restrictions', 'kiriminaja-official' ),
            'target' => 'kiriof_courier_restrictions_coupon_data',
            'class' => $this->getShippingCouponVisibilityClasses(),
        );

        $tabs['kiriof_usage_combinations'] = array(
            'label' => __( 'Usage Combinations', 'kiriminaja-official' ),
            'target' => 'kiriof_usage_combinations_coupon_data',
            'class' => $this->getShippingCouponVisibilityClasses(),
        );

        return $tabs;
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

    public function getCurrentShippingDiscountAjax() {
        if ( isset( $_POST['nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'message' => __( 'Security check failed.', 'kiriminaja-official' ) ), 403 );
            }
        }

        $service = new ShippingDiscountCouponService();
        $summary = $service->getCurrentShippingDiscountSummary();
        $amount  = (float) $summary['amount'];

        wp_send_json_success( array(
            'amount'    => $amount,
            'formatted' => $amount > 0 ? wp_strip_all_tags( wc_price( $amount ) ) : '',
            'label'     => (string) ( $summary['label'] ?? __( 'Shipping Discount', 'kiriminaja-official' ) ),
            'codes'     => (array) ( $summary['codes'] ?? array() ),
            'rate_label' => (string) ( $summary['rate_label'] ?? '' ),
            'current_cost' => (float) ( $summary['current_cost'] ?? 0 ),
            'original_cost' => (float) ( $summary['original_cost'] ?? 0 ),
            'formatted_current_cost' => ! empty( $summary['current_cost'] ) ? wp_strip_all_tags( wc_price( (float) $summary['current_cost'] ) ) : '',
            'formatted_original_cost' => ! empty( $summary['original_cost'] ) ? wp_strip_all_tags( wc_price( (float) $summary['original_cost'] ) ) : '',
        ) );
    }

    public function registerCouponListColumns( $columns ) {
        $inserted = array();
        foreach ( $columns as $key => $label ) {
            $inserted[ $key ] = $label;
            if ( 'coupon_amount' === $key ) {
                $inserted['kiriof_coupon_combinations'] = __( 'Combinations', 'kiriminaja-official' );
            }
        }

        if ( ! isset( $inserted['kiriof_coupon_combinations'] ) ) {
            $inserted['kiriof_coupon_combinations'] = __( 'Combinations', 'kiriminaja-official' );
        }

        return $inserted;
    }

    public function renderCouponListColumn( $column, $post_id ) {
        if ( 'kiriof_coupon_combinations' !== $column ) {
            return;
        }

        $coupon = new \WC_Coupon( $post_id );
        if ( ! $this->isShippingDiscountType( $coupon->get_discount_type() ) ) {
            echo '&mdash;';
            return;
        }

        $enabledTypes = $this->getSavedCombinations( $post_id );
        $types = $this->getCombinationTypes();

        echo '<div class="kiriof-combination-column">';
        foreach ( $types as $type => $config ) {
            $isEnabled = in_array( $type, $enabledTypes, true );
            $title = $isEnabled
                ? sprintf( __( 'Can combine with %s', 'kiriminaja-official' ), $config['label'] )
                : sprintf( __( 'Cannot combine with %s', 'kiriminaja-official' ), $config['label'] );

            echo '<span class="kiriof-combination-badge ' . esc_attr( $isEnabled ? 'is-enabled' : 'is-disabled' ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $config['short'] ) . '</span>';
        }
        echo '</div>';
    }

    public function renderCouponDataPanels() {
        $coupon_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        echo '<div id="kiriof_area_restrictions_coupon_data" class="panel woocommerce_options_panel kiriof-shipping-discount-panel ' . esc_attr( implode( ' ', $this->getShippingCouponVisibilityClasses() ) ) . '">';
        $this->renderAreaRestrictionFields( $coupon_id );
        $this->renderTabFooter();
        echo '</div>';

        echo '<div id="kiriof_courier_restrictions_coupon_data" class="panel woocommerce_options_panel kiriof-shipping-discount-panel ' . esc_attr( implode( ' ', $this->getShippingCouponVisibilityClasses() ) ) . '">';
        $this->renderCourierRestrictionFields( $coupon_id );
        $this->renderTabFooter();
        echo '</div>';

        echo '<div id="kiriof_usage_combinations_coupon_data" class="panel woocommerce_options_panel kiriof-shipping-discount-panel ' . esc_attr( implode( ' ', $this->getShippingCouponVisibilityClasses() ) ) . '">';
        $this->renderUsageCombinationFields( $coupon_id );
        $this->renderTabFooter();
        echo '</div>';
    }

    public function renderUsageRestrictionFields( $coupon_id = 0, $coupon = null ) {
        unset( $coupon_id, $coupon );
    }

    private function renderAreaRestrictionFields( int $coupon_id ): void {
        $savedRegions       = $this->getSavedRegions( $coupon_id );
        $regionRepo         = new ShippingDiscountRegionRepository();
        $regionCacheService = new ShippingDiscountRegionCacheService();

        $provinces      = $regionRepo->getProvinces();
        $isCacheStale   = $regionRepo->isCacheStale();
        $cacheStatus    = $regionCacheService->getStatus();
        $isCachePending = $regionCacheService->isRefreshPending();

        echo '<div class="options_group kiriof-shipping-discount-options">';
        echo '<p class="form-field">';
        echo '<label for="kiriof_coupon_region_search">' . esc_html__( 'Allowed Regions', 'kiriminaja-official' ) . '</label>';
        echo '<span class="wrap">';
        echo '<button type="button" class="button-link kiriof-refresh-regions-button">' . esc_html__( 'Refresh Region Data', 'kiriminaja-official' ) . '</button>';
        echo '</span>';
        echo '<span class="description">' . esc_html__( 'Leave empty to allow all shipping destinations.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';

        echo '<input type="hidden" id="kiriof_coupon_regions" name="' . esc_attr( self::META_REGIONS ) . '" value="' . esc_attr( wp_json_encode( $savedRegions ) ) . '" />';
        echo '<input type="hidden" id="kiriof_coupon_region_scope_value" name="kiriof_coupon_region_scope" value="all" />';
        echo '<div class="kiriof-region-picker">';
        echo '<div class="kiriof-region-picker-mode">';
        echo '<button type="button" class="kiriof-region-toggle" data-scope="all">' . esc_html__( 'All Indonesian Regions', 'kiriminaja-official' ) . '</button>';
        echo '<button type="button" class="kiriof-region-toggle" data-scope="selected">' . esc_html__( 'Selected Regions', 'kiriminaja-official' ) . '</button>';
        echo '</div>';

        echo '<div class="kiriof-region-picker-toolbar">';
        echo '<label class="screen-reader-text" for="kiriof_coupon_region_search">' . esc_html__( 'Search region, province, or city', 'kiriminaja-official' ) . '</label>';
        echo '<input type="search" id="kiriof_coupon_region_search" class="regular-text" placeholder="' . esc_attr__( 'Search region, province, or city', 'kiriminaja-official' ) . '" />';
        echo '<div class="kiriof-region-picker-stats">';
        echo '<span class="kiriof-region-stat" data-kind="islands"></span>';
        echo '<span class="kiriof-region-stat" data-kind="provinces"></span>';
        echo '<span class="kiriof-region-stat" data-kind="cities"></span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="kiriof-region-picker-tree"></div>';
        echo '</div>';

        if ( $isCachePending && empty( $provinces ) ) {
            echo '<p class="form-field"><span class="description">' . esc_html__( 'Coverage data is being prepared in the background. Please wait a moment, then reload this tab.', 'kiriminaja-official' ) . '</span></p>';
        } elseif ( $isCacheStale ) {
            echo '<p class="form-field"><span class="description">' . esc_html__( 'Region data is older than 24 hours. It will be refreshed automatically in the background.', 'kiriminaja-official' ) . '</span></p>';
        }

        if ( 'error' === ( $cacheStatus['state'] ?? '' ) && ! empty( $cacheStatus['last_error'] ) ) {
            echo '<p class="form-field"><span class="description">' . esc_html( $cacheStatus['last_error'] ) . '</span></p>';
        }

        echo '</div>';
    }

    private function renderCourierRestrictionFields( int $coupon_id ): void {
        $savedCouriers = $this->getSavedCouriers( $coupon_id );
        $couriers = $this->getCourierOptions();

        echo '<div class="options_group kiriof-shipping-discount-options">';
        echo '<p class="form-field">';
        echo '<label for="kiriof_coupon_couriers">' . esc_html__( 'Allowed Couriers', 'kiriminaja-official' ) . '</label>';
        echo '<select id="kiriof_coupon_couriers" name="' . esc_attr( self::META_COURIERS ) . '[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;">';
        foreach ( $couriers as $courier ) {
            echo '<option value="' . esc_attr( $courier['id'] ) . '" ' . selected( in_array( $courier['id'], $savedCouriers, true ), true, false ) . '>' . esc_html( $courier['text'] ) . '</option>';
        }
        echo '</select>';
        echo '<span class="description">' . esc_html__( 'Leave empty to apply this shipping coupon to all couriers.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';
        echo '</div>';
    }

    private function renderUsageCombinationFields( int $coupon_id ): void {
        $savedCombinations = $this->getSavedCombinations( $coupon_id );

        echo '<div class="options_group kiriof-shipping-discount-options">';
        echo '<p class="form-field" style="display: flex;flex-direction: column;">';
        echo '<label>' . esc_html__( 'Allow Combinations', 'kiriminaja-official' ) . '</label>';
        echo '<span class="wrap kiriof-combination-options" style="display: flex;flex-direction: column;">';
        foreach ( $this->getCombinationTypes() as $type => $config ) {
            echo '<label class="kiriof-combination-option">';
            echo '<input type="checkbox" name="' . esc_attr( self::META_COMBINATIONS ) . '[]" value="' . esc_attr( $type ) . '" ' . checked( in_array( $type, $savedCombinations, true ), true, false ) . ' /> ';
            echo esc_html( $config['label'] );
            echo '</label>';
        }
        echo '</span>';
        echo '<span class="description">' . esc_html__( 'Leave selected to allow this shipping discount coupon to stack with the checked discount types.', 'kiriminaja-official' ) . '</span>';
        echo '</p>';
        echo '</div>';
    }

    private function renderTabFooter(): void {
        echo '<div class="kiriof-coupon-tab-footer" style="padding: 12px">';
        echo esc_html__( 'Powered by KiriminAja Discount Extension', 'kiriminaja-official' );
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
        $combinations = isset( $_POST[ self::META_COMBINATIONS ] )
            ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ self::META_COMBINATIONS ] ) )
            : array();
        $discountType = $coupon instanceof \WC_Coupon ? $coupon->get_discount_type() : '';

        update_post_meta( $post_id, self::META_REGIONS, wp_json_encode( $this->normalizeRegions( $regions ) ) );
        update_post_meta( $post_id, self::META_COURIERS, array_values( array_unique( array_filter( $couriers ) ) ) );

        if ( $this->isShippingDiscountType( $discountType ) ) {
            $allowedTypes = array_keys( $this->getCombinationTypes() );
            update_post_meta( $post_id, self::META_COMBINATIONS, array_values( array_intersect( $allowedTypes, $combinations ) ) );
        } else {
            delete_post_meta( $post_id, self::META_COMBINATIONS );
        }
    }

    public function enqueueCouponAdminAssets( $hook ) {
        if ( ! $this->isCouponAdminScreen() ) {
            return;
        }

        // Ensure tables exist and seed from bundle if DB is empty —
        // must happen before getRegionPickerTreeData() reads from DB.
        if ( class_exists( '\KiriminAjaOfficial\Migration\SetupMigration' ) ) {
            ( new \KiriminAjaOfficial\Migration\SetupMigration() )->register();
        }

        $regionRepo         = new ShippingDiscountRegionRepository();
        $regionCacheService = new ShippingDiscountRegionCacheService();

        if ( $regionRepo->getProvinceCount() < 1 ) {
            $regionCacheService->seedFromBundledData( $regionRepo );
        }

        if ( $regionRepo->isCacheStale() ) {
            $regionCacheService->scheduleRefresh();
            spawn_cron();
        }

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
                'discountTypes' => $this->getShippingCouponTypes(),
                'isCacheStale' => $regionRepo->isCacheStale(),
                'isCachePending' => $regionCacheService->isRefreshPending(),
                'regionTree' => $this->getRegionPickerTreeData( $regionRepo ),
                'strings' => array(
                    'allRegionsLabel' => __( 'All Indonesian Regions', 'kiriminaja-official' ),
                    'selectedRegionsLabel' => __( 'Selected Regions', 'kiriminaja-official' ),
                    'allProvinceLabel' => __( 'All provinces in Indonesia', 'kiriminaja-official' ),
                    'allCitiesLabel' => __( 'All cities in %s', 'kiriminaja-official' ),
                    'specificCityLabel' => __( '%1$s, %2$s', 'kiriminaja-official' ),
                    'searchRegions' => __( 'Search region, province, or city', 'kiriminaja-official' ),
                    'chooseProvince' => __( 'Please choose a province first.', 'kiriminaja-official' ),
                    'chooseCity' => __( 'Please choose at least one city.', 'kiriminaja-official' ),
                    'cacheRefreshing' => __( 'Refreshing region data…', 'kiriminaja-official' ),
                    'cacheRefreshed' => __( 'Region data refreshed.', 'kiriminaja-official' ),
                    'cacheRefreshFailed' => __( 'Failed to refresh region data.', 'kiriminaja-official' ),
                    'cachePreparing' => __( 'Coverage data is being prepared in the background. Please wait a moment, then reload this tab.', 'kiriminaja-official' ),
                    'islands' => __( 'Islands', 'kiriminaja-official' ),
                    'provinces' => __( 'Provinces', 'kiriminaja-official' ),
                    'cities' => __( 'Cities', 'kiriminaja-official' ),
                    'noRegionMatches' => __( 'No regions match your search.', 'kiriminaja-official' ),
                    'selectRegionBeforeSave' => __( 'Choose at least one city or switch back to all regions before saving this coupon.', 'kiriminaja-official' ),
                    'refreshRegionData' => __( 'Refresh Region Data', 'kiriminaja-official' ),
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

        $cacheService = new ShippingDiscountRegionCacheService();
        $cacheService->scheduleRefresh( true );
        spawn_cron();

        wp_send_json_success( array( 'state' => 'scheduled' ) );
    }

    public function getRegionCacheStatusAjax() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ), 403 );
        }

        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'kiriminaja-official' ) ), 403 );
        }

        $cacheService = new ShippingDiscountRegionCacheService();
        $regionRepo   = new ShippingDiscountRegionRepository();

        wp_send_json_success(
            array(
                'status'         => $cacheService->getStatus(),
                'province_count' => $regionRepo->getProvinceCount(),
                'city_count'     => $regionRepo->getCityCount(),
            )
        );
    }

    public function refreshRegionCacheCron() {
        ( new ShippingDiscountRegionCacheService() )->refreshAll();
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
            $cacheService = new ShippingDiscountRegionCacheService();
            if ( ! $cacheService->isRefreshPending() ) {
                $cacheService->refreshProvinceCities( $provinceId );
            }
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

    private function getSavedCombinations( int $couponId ): array {
        $allowedTypes = array_keys( $this->getCombinationTypes() );
        $raw = get_post_meta( $couponId, self::META_COMBINATIONS, true );

        if ( empty( $raw ) ) {
            return $allowedTypes;
        }

        if ( is_array( $raw ) ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', $raw ) ) );
        }

        if ( is_string( $raw ) ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
        }

        return $allowedTypes;
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

    private function getCombinationTypes(): array {
        return array(
            ShippingDiscountCouponService::FIXED_COUPON_TYPE => array(
                'label' => __( 'Fixed shipping discount', 'kiriminaja-official' ),
                'short' => 'SHIPF',
            ),
            ShippingDiscountCouponService::PERCENTAGE_COUPON_TYPE => array(
                'label' => __( 'Percentage shipping discount', 'kiriminaja-official' ),
                'short' => 'SHIPP',
            ),
            'fixed_cart' => array(
                'label' => __( 'Fixed cart discount', 'kiriminaja-official' ),
                'short' => 'CART',
            ),
            'percent' => array(
                'label' => __( 'Percentage discount', 'kiriminaja-official' ),
                'short' => 'PCT',
            ),
            'fixed_product' => array(
                'label' => __( 'Fixed product discount', 'kiriminaja-official' ),
                'short' => 'PROD',
            ),
        );
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

    private function getShippingCouponTypes(): array {
        return ( new ShippingDiscountCouponService() )->getShippingCouponTypes();
    }

    private function getShippingCouponVisibilityClasses(): array {
        return array_map(
            static function ( string $type ): string {
                return 'show_if_' . $type;
            },
            $this->getShippingCouponTypes()
        );
    }

    private function isShippingDiscountType( string $discountType ): bool {
        return in_array( $discountType, $this->getShippingCouponTypes(), true );
    }

    private function getRegionPickerTreeData( ShippingDiscountRegionRepository $regionRepo ): array {
        $groupedCities = array();
        foreach ( $regionRepo->getCities() as $city ) {
            $provinceId = (string) ( $city->province_id ?? '' );
            if ( '' === $provinceId ) {
                continue;
            }

            if ( ! isset( $groupedCities[ $provinceId ] ) ) {
                $groupedCities[ $provinceId ] = array();
            }

            $groupedCities[ $provinceId ][] = array(
                'id' => (string) $city->id,
                'name' => (string) $city->name,
            );
        }

        $groups = array();
        foreach ( $regionRepo->getProvinces() as $province ) {
            $provinceId = (string) $province->id;
            $groupKey = $this->getIslandKeyForProvince( (string) $province->name );

            if ( ! isset( $groups[ $groupKey ] ) ) {
                $groups[ $groupKey ] = array(
                    'id' => $groupKey,
                    'name' => $this->getIslandLabel( $groupKey ),
                    'provinces' => array(),
                );
            }

            $groups[ $groupKey ]['provinces'][] = array(
                'id' => $provinceId,
                'name' => (string) $province->name,
                'cities' => $groupedCities[ $provinceId ] ?? array(),
            );
        }

        $orderedGroups = array();
        foreach ( array( 'sumatra', 'java', 'bali_nusa_tenggara', 'kalimantan', 'sulawesi', 'maluku', 'papua', 'other' ) as $groupKey ) {
            if ( isset( $groups[ $groupKey ] ) ) {
                $orderedGroups[] = $groups[ $groupKey ];
            }
        }

        return $orderedGroups;
    }

    private function getIslandKeyForProvince( string $provinceName ): string {
        $name = strtolower( $provinceName );

        if ( preg_match( '/aceh|sumatera|sumatra|riau|jambi|bengkulu|lampung|bangka|belitung/', $name ) ) {
            return 'sumatra';
        }

        if ( preg_match( '/jakarta|jawa|banten|yogyakarta/', $name ) ) {
            return 'java';
        }

        if ( preg_match( '/bali|nusa tenggara/', $name ) ) {
            return 'bali_nusa_tenggara';
        }

        if ( preg_match( '/kalimantan/', $name ) ) {
            return 'kalimantan';
        }

        if ( preg_match( '/sulawesi|gorontalo/', $name ) ) {
            return 'sulawesi';
        }

        if ( preg_match( '/maluku/', $name ) ) {
            return 'maluku';
        }

        if ( preg_match( '/papua/', $name ) ) {
            return 'papua';
        }

        return 'other';
    }

    private function getIslandLabel( string $groupKey ): string {
        $labels = array(
            'sumatra' => __( 'Sumatra', 'kiriminaja-official' ),
            'java' => __( 'Java', 'kiriminaja-official' ),
            'bali_nusa_tenggara' => __( 'Bali & Nusa Tenggara', 'kiriminaja-official' ),
            'kalimantan' => __( 'Kalimantan', 'kiriminaja-official' ),
            'sulawesi' => __( 'Sulawesi', 'kiriminaja-official' ),
            'maluku' => __( 'Maluku', 'kiriminaja-official' ),
            'papua' => __( 'Papua', 'kiriminaja-official' ),
            'other' => __( 'Other Regions', 'kiriminaja-official' ),
        );

        return $labels[ $groupKey ] ?? $labels['other'];
    }
}
