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
        add_filter( 'woocommerce_cart_coupon_types', array( $this, 'registerRuntimeCartCouponTypes' ) );
        add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'registerCouponDataTabs' ) );
        add_filter( 'woocommerce_coupon_is_valid_for_cart', array( $this, 'validateShippingCouponForCart' ), 20, 2 );
        add_filter( 'woocommerce_coupon_message', array( $this, 'customizeCouponSuccessMessage' ), 10, 3 );
        add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'validateShippingCouponForProduct' ), 20, 4 );
        add_filter( 'woocommerce_coupon_get_discount_amount', array( $this, 'zeroItemDiscountForShippingCoupon' ), 20, 5 );
        add_action( 'woocommerce_applied_coupon', array( $this, 'invalidateShippingRatesAfterCouponChange' ), 5, 1 );
        add_action( 'woocommerce_removed_coupon', array( $this, 'invalidateShippingRatesAfterCouponChange' ), 5, 1 );
        add_action( 'woocommerce_applied_coupon', array( $this, 'handleAppliedShippingCoupon' ), 20, 1 );
        add_action( 'woocommerce_applied_coupon', array( $this, 'invalidateShippingRatesAfterCouponChange' ), 30, 1 );
        add_action( 'woocommerce_removed_coupon', array( $this, 'invalidateShippingRatesAfterCouponChange' ), 30, 1 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'enforceShippingCouponRestrictions' ), 20, 1 );
        add_filter( 'manage_edit-shop_coupon_columns', array( $this, 'registerCouponListColumns' ) );
        add_action( 'manage_shop_coupon_posts_custom_column', array( $this, 'renderCouponListColumn' ), 10, 2 );
        add_action( 'woocommerce_coupon_data_panels', array( $this, 'renderCouponDataPanels' ) );
        add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'renderUsageRestrictionFields' ), 20, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'saveCouponOptions' ), 10, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'normalizeCouponAmount' ), 5, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueCouponAdminAssets' ) );
        add_action( 'add_meta_boxes', array( $this, 'registerAreaRestrictionsMetabox' ) );
        add_action( 'restrict_manage_posts', array( $this, 'renderCouponExtensionFilter' ) );
        add_action( 'pre_get_posts', array( $this, 'filterCouponsByExtension' ) );
        add_action( 'wp_ajax_kiriof_refresh_coupon_regions', array( $this, 'refreshRegionCacheAjax' ) );
        add_action( 'wp_ajax_kiriof_flush_couriers_cache', array( $this, 'flushCouriersCache' ) );
        add_action( 'wp_ajax_kiriof_get_coupon_region_status', array( $this, 'getRegionCacheStatusAjax' ) );
        add_action( 'wp_ajax_kiriof_get_coupon_region_cities', array( $this, 'getCitiesByProvinceAjax' ) );
        add_action( 'wp_ajax_kiriof_get_current_shipping_discount', array( $this, 'getCurrentShippingDiscountAjax' ) );
        add_action( 'wp_ajax_nopriv_kiriof_get_current_shipping_discount', array( $this, 'getCurrentShippingDiscountAjax' ) );
        add_action( 'wp_ajax_kiriof_get_applied_coupon_scopes', array( $this, 'getAppliedCouponScopesAjax' ) );
        add_action( 'wp_ajax_nopriv_kiriof_get_applied_coupon_scopes', array( $this, 'getAppliedCouponScopesAjax' ) );
        add_action( ShippingDiscountRegionCacheService::CRON_HOOK, array( $this, 'refreshRegionCacheCron' ) );
    }

    public function registerDiscountType( $types ) {
        $types[ ShippingDiscountCouponService::FIXED_COUPON_TYPE ] = __( 'Fixed shipping discount', 'kiriminaja-official' );
        $types[ ShippingDiscountCouponService::PERCENTAGE_COUPON_TYPE ] = __( 'Percentage shipping discount', 'kiriminaja-official' );
        return $types;
    }

    public function registerRuntimeCartCouponTypes( $types ) {
        return array_values( array_unique( array_merge( (array) $types, $this->getShippingCouponTypes() ) ) );
    }

    public function registerCouponDataTabs( $tabs ) {
        return $tabs;
    }

    public function validateShippingCouponForCart( $valid, $coupon ) {
        $service = new ShippingDiscountCouponService();
        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return $valid;
        }

        if ( ! $valid ) {
            $this->logShippingCouponEvent(
                'warning',
                'Shipping discount coupon rejected before KiriminAja validation.',
                $coupon,
                array(
                    'hook' => 'woocommerce_coupon_is_valid_for_cart',
                )
            );
            return false;
        }

        $validation = $service->validateCouponForCart( $coupon );
        if ( $validation['valid'] ) {
            $this->logShippingCouponEvent(
                'info',
                'Shipping discount coupon passed cart validation.',
                $coupon,
                array(
                    'hook' => 'woocommerce_coupon_is_valid_for_cart',
                )
            );
            $service->clearValidationNotices();
            return true;
        }

        $message = $validation['message'] ?? '';
        $this->logShippingCouponEvent(
            'warning',
            'Shipping discount coupon failed cart validation.',
            $coupon,
            array(
                'hook' => 'woocommerce_coupon_is_valid_for_cart',
                'validation_message' => $message,
            )
        );
        if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
            if ( ! function_exists( 'wc_has_notice' ) || ! wc_has_notice( $message, 'error' ) ) {
                wc_add_notice( $message, 'error' );
            }
        }

        return false;
    }

    public function validateShippingCouponForProduct( $valid, $product, $coupon, $values ) {
        unset( $product, $values );

        $service = new ShippingDiscountCouponService();
        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return $valid;
        }

        return true;
    }

    public function zeroItemDiscountForShippingCoupon( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
        unset( $discounting_amount, $cart_item, $single );

        $service = new ShippingDiscountCouponService();
        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return $discount;
        }

        return 0;
    }

    public function handleAppliedShippingCoupon( $coupon_code ) {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return;
        }

        $coupon_code = sanitize_text_field( (string) $coupon_code );
        if ( '' === $coupon_code ) {
            return;
        }

        $service = new ShippingDiscountCouponService();
        $coupon  = new \WC_Coupon( $coupon_code );

        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return;
        }

        $validation = $service->validateCouponForCart( $coupon );
        if ( $validation['valid'] ) {
            $this->logShippingCouponEvent(
                'info',
                'Applied shipping discount coupon remains valid.',
                $coupon,
                array(
                    'hook' => 'woocommerce_applied_coupon',
                )
            );
            $service->clearValidationNotices();
            return;
        }

        WC()->cart->remove_coupon( $coupon_code );

        $message = $validation['message'] ?? '';
        $this->logShippingCouponEvent(
            'warning',
            'Applied shipping discount coupon removed after validation.',
            $coupon,
            array(
                'hook' => 'woocommerce_applied_coupon',
                'validation_message' => $message,
            )
        );
        if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
            if ( function_exists( 'wc_clear_notices' ) ) {
                wc_clear_notices();
            }
            wc_add_notice( $message, 'error' );
        }
    }

    public function customizeCouponSuccessMessage( $msg, $msg_code, $coupon ) {
        if ( 200 !== $msg_code || ! $coupon instanceof \WC_Coupon ) {
            return $msg;
        }

        $service = new ShippingDiscountCouponService();
        if ( ! $service->isShippingCoupon( $coupon ) ) {
            return $msg;
        }

        $code = $coupon->get_code();
        if ( '' === $code ) {
            return $msg;
        }

        $nativeCodes = array();
        if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_coupons' ) ) {
            foreach ( WC()->cart->get_coupons() as $activeCoupon ) {
                if ( ! $activeCoupon instanceof \WC_Coupon || $service->isShippingCoupon( $activeCoupon ) ) {
                    continue;
                }
                $nativeCodes[] = $activeCoupon->get_code();
            }
        }

        if ( ! empty( $nativeCodes ) ) {
            $list = implode( ', ', $nativeCodes );
            return sprintf(
                /* translators: 1: shipping coupon code, 2: comma-separated list of active cart coupons */
                __( 'Shipping discount "%1$s" applied and combined with: %2$s.', 'kiriminaja-official' ),
                $code,
                $list
            );
        }

        return sprintf(
            /* translators: %s: coupon code */
            __( 'Shipping discount "%s" applied to your cart.', 'kiriminaja-official' ),
            $code
        );
    }

    public function invalidateShippingRatesAfterCouponChange( $coupon_code = '' ): void {
        unset( $coupon_code );

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        if ( isset( WC()->session ) && WC()->session ) {
            WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );
        }

        $packages = array();
        if ( isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_shipping_packages' ) ) {
            $packages = (array) WC()->cart->get_shipping_packages();
        }

        if ( isset( WC()->session ) && WC()->session ) {
            foreach ( array_keys( $packages ) as $package_index ) {
                WC()->session->set( 'shipping_for_package_' . $package_index, false );
            }
        }

        if ( isset( WC()->shipping ) && WC()->shipping() && method_exists( WC()->shipping(), 'reset_shipping' ) ) {
            WC()->shipping()->reset_shipping();
        }

        $this->logShippingCouponEvent(
            'info',
            'Shipping rates invalidated after coupon change.',
            null,
            array(
                'hook' => current_filter(),
                'package_count' => count( $packages ),
                'applied_coupons' => $this->getAppliedCouponCodesForLog(),
            )
        );
    }

    public function enforceShippingCouponRestrictions( $cart ) {
        static $is_running = false;

        if ( $is_running || ! $cart || ! method_exists( $cart, 'get_coupons' ) ) {
            return;
        }

        $service = new ShippingDiscountCouponService();
        if ( ! $service->hasActiveShippingCouponInCart() ) {
            $service->clearValidationNotices();
            return;
        }

        $removed = false;
        $is_running = true;

        foreach ( (array) $cart->get_coupons() as $coupon ) {
            if ( ! $service->isShippingCoupon( $coupon ) ) {
                continue;
            }

            $validation = $service->validateCouponForCart( $coupon );
            if ( $validation['valid'] ) {
                $this->logShippingCouponEvent(
                    'info',
                    'Active shipping discount coupon remains valid during totals calculation.',
                    $coupon,
                    array(
                        'hook' => 'woocommerce_before_calculate_totals',
                    )
                );
                $service->clearValidationNotices();
                continue;
            }

            $cart->remove_coupon( (string) $coupon->get_code() );
            $removed = true;

            $message = $validation['message'] ?? '';
            $this->logShippingCouponEvent(
                'warning',
                'Active shipping discount coupon removed during totals calculation.',
                $coupon,
                array(
                    'hook' => 'woocommerce_before_calculate_totals',
                    'validation_message' => $message,
                )
            );
            if ( '' !== $message && function_exists( 'wc_add_notice' ) ) {
                if ( ! function_exists( 'wc_has_notice' ) || ! wc_has_notice( $message, 'error' ) ) {
                    wc_add_notice( $message, 'error' );
                }
            }
        }

        $is_running = false;

        if ( $removed && method_exists( $cart, 'calculate_totals' ) ) {
            $cart->calculate_totals();
        }
    }

    private function logShippingCouponEvent( string $level, string $message, $coupon = null, array $context = array() ): void {
        if ( ! function_exists( 'kiriof_log' ) ) {
            return;
        }

        if ( $coupon instanceof \WC_Coupon ) {
            $context['coupon_code'] = (string) $coupon->get_code();
            $context['discount_type'] = (string) $coupon->get_discount_type();
            $context['coupon_amount'] = (float) $coupon->get_amount();
        }

        if ( ! isset( $context['applied_coupons'] ) ) {
            $context['applied_coupons'] = $this->getAppliedCouponCodesForLog();
        }

        if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
            $context['chosen_shipping_methods'] = (array) WC()->session->get( 'chosen_shipping_methods', array() );
            $context['kiriof_chosen_shipping_methods'] = (array) WC()->session->get( 'kiriof_chosen_shipping_methods', array() );
            $context['destination_id'] = (int) ( WC()->session->get( 'shipping_destination_id' ) ?: WC()->session->get( 'destination_id' ) ?: 0 );
            $context['payment_method'] = (string) ( WC()->session->get( 'chosen_payment_method' ) ?: WC()->session->get( 'kiriof_payment_method' ) ?: '' );
        }

        kiriof_log( $level, $message, $context, 'shipping_discount_coupon' );
    }

    private function getAppliedCouponCodesForLog(): array {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart || ! method_exists( WC()->cart, 'get_applied_coupons' ) ) {
            return array();
        }

        return array_values( array_map( 'strval', (array) WC()->cart->get_applied_coupons() ) );
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

    public function getAppliedCouponScopesAjax() {
        if ( isset( $_POST['nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'message' => __( 'Security check failed.', 'kiriminaja-official' ) ), 403 );
            }
        }

        $requestedCode = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
        $codes = array();

        if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_applied_coupons' ) ) {
            $codes = array_values( array_map( 'strval', (array) WC()->cart->get_applied_coupons() ) );
        }

        if ( '' !== $requestedCode && ! in_array( $requestedCode, $codes, true ) ) {
            $codes[] = $requestedCode;
        }

        $service = new ShippingDiscountCouponService();
        $scopes  = $service->splitCouponCodesByScope( $codes );

        wp_send_json_success( array(
            'requested' => $requestedCode,
            'is_shipping' => '' !== $requestedCode && in_array( strtoupper( $requestedCode ), $scopes['shipping'], true ),
            'shipping' => $scopes['shipping'],
            'native' => $scopes['item'],
        ) );
    }

    public function registerCouponListColumns( $columns ) {
        $inserted = array();
        foreach ( $columns as $key => $label ) {
            $inserted[ $key ] = $label;
            if ( 'coupon_amount' === $key ) {
                $inserted['kiriof_coupon_combinations'] = __( 'Combinations', 'kiriminaja-official' );
                $inserted['kiriof_coupon_extension']    = __( 'Extension', 'kiriminaja-official' );
            }
        }

        if ( ! isset( $inserted['kiriof_coupon_combinations'] ) ) {
            $inserted['kiriof_coupon_combinations'] = __( 'Combinations', 'kiriminaja-official' );
            $inserted['kiriof_coupon_extension']    = __( 'Extension', 'kiriminaja-official' );
        }

        return $inserted;
    }

    public function renderCouponListColumn( $column, $post_id ) {
        if ( 'kiriof_coupon_extension' === $column ) {
            $coupon    = new \WC_Coupon( $post_id );
            $isKiriof  = $this->isShippingDiscountType( $coupon->get_discount_type() );
            if ( $isKiriof ) {
                echo '<mark class="order-status status-on-hold tips"><span>KiriminAja</span></mark>';
            } else {
                echo '<mark class="order-status status-processing tips"><span>' . esc_html__( 'Default', 'kiriminaja-official' ) . '</span></mark>';
            }
            return;
        }

        if ( 'kiriof_coupon_combinations' !== $column ) {
            return;
        }

        $coupon = new \WC_Coupon( $post_id );
        if ( ! $this->isShippingDiscountType( $coupon->get_discount_type() ) ) {
            if ( 'yes' === $coupon->get_individual_use() ) {
                echo '<span class="kiriof-combination-badge kiriof-individual-use-badge" title="' . esc_attr__( 'Individual use only — cannot combine with other coupons', 'kiriminaja-official' ) . '">'
                    . esc_html__( 'Individual use', 'kiriminaja-official' ) . '</span>';
            } else {
                echo '&mdash;';
            }
            return;
        }

        $enabledTypes = $this->getSavedCombinations( $post_id );
        $types = $this->getCombinationTypes();

        echo '<div class="kiriof-combination-column">';
        foreach ( $types as $type => $config ) {
            $isEnabled = in_array( $type, $enabledTypes, true );
            if ( $isEnabled ) {
                // translators: %s is the coupon type label (e.g. "Fixed cart discount").
                $title = sprintf( __( 'Can combine with %s', 'kiriminaja-official' ), $config['label'] );
            } else {
                // translators: %s is the coupon type label (e.g. "Fixed cart discount").
                $title = sprintf( __( 'Cannot combine with %s', 'kiriminaja-official' ), $config['label'] );
            }

            echo '<span class="kiriof-combination-badge ' . esc_attr( $isEnabled ? 'is-enabled' : 'is-disabled' ) . '" title="' . esc_attr( $title ) . '" style="opacity:' . ( $isEnabled ? '1' : '0.4' ) . '"><span class="dashicons ' . esc_attr( $config['icon'] ) . '" aria-hidden="true"></span></span>';
        }
        echo '</div>';
    }

    public function renderCouponExtensionFilter( $post_type ) {
        if ( 'shop_coupon' !== $post_type ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter on list table
        $current = isset( $_GET['kiriof_extension'] ) ? sanitize_key( wp_unslash( $_GET['kiriof_extension'] ) ) : '';
        ?>
        <select name="kiriof_extension">
            <option value=""><?php esc_html_e( 'All Extensions', 'kiriminaja-official' ); ?></option>
            <option value="kiriminaja" <?php selected( $current, 'kiriminaja' ); ?>><?php esc_html_e( 'KiriminAja', 'kiriminaja-official' ); ?></option>
            <option value="default" <?php selected( $current, 'default' ); ?>><?php esc_html_e( 'Default', 'kiriminaja-official' ); ?></option>
        </select>
        <?php
    }

    public function filterCouponsByExtension( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'edit-shop_coupon' !== $screen->id ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter
        $extension = isset( $_GET['kiriof_extension'] ) ? sanitize_key( wp_unslash( $_GET['kiriof_extension'] ) ) : '';
        if ( '' === $extension ) {
            return;
        }

        $shippingTypes = $this->getShippingCouponTypes();

        if ( 'kiriminaja' === $extension ) {
            $query->set( 'meta_query', array(
                array(
                    'key'     => 'discount_type',
                    'value'   => $shippingTypes,
                    'compare' => 'IN',
                ),
            ) );
        } elseif ( 'default' === $extension ) {
            $query->set( 'meta_query', array(
                array(
                    'key'     => 'discount_type',
                    'value'   => $shippingTypes,
                    'compare' => 'NOT IN',
                ),
            ) );
        }
    }

    public function renderCouponDataPanels() {
        // All panels moved to dedicated metaboxes.
    }

    public function registerAreaRestrictionsMetabox() {
        add_meta_box(
            'kiriof_area_restrictions_metabox',
            __( 'Area Restrictions', 'kiriminaja-official' ),
            array( $this, 'renderAreaRestrictionsMetabox' ),
            'shop_coupon',
            'normal',
            'default'
        );
        add_meta_box(
            'kiriof_courier_restrictions_metabox',
            __( 'Courier Restrictions', 'kiriminaja-official' ),
            array( $this, 'renderCourierRestrictionsMetabox' ),
            'shop_coupon',
            'normal',
            'default'
        );
        add_meta_box(
            'kiriof_usage_combinations_metabox',
            __( 'Usage Combinations', 'kiriminaja-official' ),
            array( $this, 'renderUsageCombinationsMetabox' ),
            'shop_coupon',
            'normal',
            'default'
        );
    }

    public function renderAreaRestrictionsMetabox( $post ) {
        $this->renderAreaRestrictionFields( (int) $post->ID );
    }

    public function renderCourierRestrictionsMetabox( $post ) {
        $this->renderCourierRestrictionFields( (int) $post->ID );
    }

    public function renderUsageCombinationsMetabox( $post ) {
        $this->renderUsageCombinationFields( (int) $post->ID );
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

        echo '<div class="kiriof-area-restrictions-metabox">';

        echo '<div class="kiriof-metabox-header">';
        echo '<p class="description">' . esc_html__( 'Leave empty (All Indonesian Regions) to allow all shipping destinations. Only applies to shipping discount coupon types.', 'kiriminaja-official' ) . '</p>';
        echo '</div>';

        echo '<input type="hidden" id="kiriof_coupon_regions" name="' . esc_attr( self::META_REGIONS ) . '" value="' . esc_attr( wp_json_encode( $savedRegions ) ) . '" />';

        echo '<div class="kiriof-region-picker">';
        echo '<div class="kiriof-region-picker-mode" style="display:flex;gap:16px">';
        echo '<label class="kiriof-region-toggle"><input type="radio" name="kiriof_coupon_region_scope" value="all" /> ' . esc_html__( 'All Indonesian Regions', 'kiriminaja-official' ) . '</label>';
        echo '<label class="kiriof-region-toggle"><input type="radio" name="kiriof_coupon_region_scope" value="selected" /> ' . esc_html__( 'Selected Regions', 'kiriminaja-official' ) . '</label>';
        echo '</div>';

        echo '<div class="kiriof-region-picker-toolbar" style="display:flex;gap:16px;justify-content:space-between;margin-bottom:16px">';
        echo '<label class="screen-reader-text" for="kiriof_coupon_region_search">' . esc_html__( 'Search region, province, or city', 'kiriminaja-official' ) . '</label>';
        echo '<input type="search" id="kiriof_coupon_region_search" class="regular-text" placeholder="' . esc_attr__( 'Search region, province, or city', 'kiriminaja-official' ) . '" />';
        echo '<div class="kiriof-region-picker-stats" style="display:flex;gap:10px">';
        echo '<span class="kiriof-region-stat" data-kind="islands"></span>';
        echo '<span class="kiriof-region-stat" data-kind="provinces"></span>';
        echo '<span class="kiriof-region-stat" data-kind="cities"></span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="kiriof-region-picker-tree"></div>';
        echo '</div>';

        if ( $isCachePending && empty( $provinces ) ) {
            echo '<p class="description">' . esc_html__( 'Coverage data is being prepared in the background. Please wait a moment, then reload this tab.', 'kiriminaja-official' ) . '</p>';
        } elseif ( $isCacheStale ) {
            echo '<p class="description">' . esc_html__( 'Region data is older than 24 hours. It will be refreshed automatically in the background.', 'kiriminaja-official' ) . '</p>';
        }

        if ( 'error' === ( $cacheStatus['state'] ?? '' ) && ! empty( $cacheStatus['last_error'] ) ) {
            echo '<p class="description kiriof-error-text">' . esc_html( $cacheStatus['last_error'] ) . '</p>';
        }

        echo '</div>';
    }

    private function renderCourierRestrictionFields( int $coupon_id ): void {
        $savedCouriers = $this->getSavedCouriers( $coupon_id );
        $couriers      = $this->getCourierOptions();
        $scope         = empty( $savedCouriers ) ? 'all' : 'selected';

        echo '<div class="kiriof-courier-restrictions">';

        echo '<p class="description">' . esc_html__( 'Restrict this coupon to specific couriers. Leave as "All Couriers" to apply to every courier.', 'kiriminaja-official' ) . '</p>';

        echo '<div class="kiriof-region-picker-mode" style="display:flex;gap:16px">';
        echo '<label class="kiriof-region-toggle"><input type="radio" name="kiriof_coupon_courier_scope" value="all" ' . checked( $scope, 'all', false ) . ' /> ' . esc_html__( 'All Couriers', 'kiriminaja-official' ) . '</label>';
        echo '<label class="kiriof-region-toggle"><input type="radio" name="kiriof_coupon_courier_scope" value="selected" ' . checked( $scope, 'selected', false ) . ' /> ' . esc_html__( 'Selected Couriers', 'kiriminaja-official' ) . '</label>';
        echo '</div>';

        $list_style = 'selected' === $scope ? 'margin-top:16px' : 'display:none';
        echo '<div class="kiriof-courier-list" style="' . esc_attr( $list_style ) . '">';
        echo '<input type="hidden" name="' . esc_attr( self::META_COURIERS ) . '_scope" value="' . esc_attr( $scope ) . '" />';

        if ( empty( $couriers ) ) {
            echo '<p class="description">' . esc_html__( 'No couriers available. Please check your KiriminAja account settings.', 'kiriminaja-official' ) . '</p>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 14px;padding:12px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7">';
            foreach ( $couriers as $courier ) {
                $checked = checked( in_array( $courier['id'], $savedCouriers, true ), true, false );
                echo '<label style="display:flex;align-items:center;gap:6px;font-size:13px">';
                echo '<input type="checkbox" name="' . esc_attr( self::META_COURIERS ) . '[]" value="' . esc_attr( $courier['id'] ) . '" ' . $checked . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns a safe HTML attribute string
                echo esc_html( $courier['text'] );
                echo '</label>';
            }
            echo '</div>';
        }

        echo '</div>'; // .kiriof-courier-list
        echo '</div>'; // .kiriof-courier-restrictions
    }

    private function renderUsageCombinationFields( int $coupon_id ): void {
        $savedCombinations = $this->getSavedCombinations( $coupon_id );
        $isIndividualUse = 'yes' === get_post_meta( $coupon_id, 'individual_use', true );

        echo '<div class="options_group kiriof-shipping-discount-options">';
        echo '<div id="kiriof-individual-use-active-notice" class="kiriof-individual-use-notice" ' . ( $isIndividualUse ? '' : 'style="display:none"' ) . '>';
        echo '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span> ';
        echo esc_html__( 'Allow Combinations is not available because "Individual use only" is enabled. Uncheck it above to configure combinations.', 'kiriminaja-official' );
        echo '</div>';
        echo '<p class="form-field" style="display: flex;flex-direction: column;">';
        echo '<label>' . esc_html__( 'Allow Combinations', 'kiriminaja-official' ) . '</label>';
        echo '<span class="wrap kiriof-combination-options" style="display: flex;flex-direction: column;' . ( $isIndividualUse ? 'opacity:0.4' : '' ) . '">';
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

    public function normalizeCouponAmount( $post_id, $coupon ) {
        unset( $post_id );

        if ( ! $coupon instanceof \WC_Coupon ) {
            return;
        }

        $amount = $this->normalizeCouponAmountValue( $coupon->get_amount() );

        if ( ShippingDiscountCouponService::PERCENTAGE_COUPON_TYPE === $coupon->get_discount_type() && (float) $amount > 100 ) {
            $amount = '100';
        }

        if ( '' !== $amount && (string) $coupon->get_amount() !== $amount ) {
            $coupon->set_amount( $amount );
            $coupon->save();
        }
    }

    private function normalizeCouponAmountValue( $amount ): string {
        $raw = trim( (string) $amount );
        if ( '' === $raw ) {
            return '';
        }

        $normalized = preg_replace( '/\s+/', '', $raw );
        $normalized = str_replace( ',', '.', (string) $normalized );
        if ( ! is_numeric( $normalized ) ) {
            return $raw;
        }

        $negative = 0 === strpos( $normalized, '-' );
        if ( $negative ) {
            $normalized = substr( $normalized, 1 );
        }

        $parts = explode( '.', $normalized, 2 );
        $integer = ltrim( (string) ( $parts[0] ?? '' ), '0' );
        if ( '' === $integer ) {
            $integer = '0';
        }

        if ( $negative && '0' !== $integer ) {
            $integer = '-' . $integer;
        }

        if ( array_key_exists( 1, $parts ) ) {
            return $integer . '.' . $parts[1];
        }

        return $integer;
    }

    public function saveCouponOptions( $post_id, $coupon ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // WooCommerce verifies the nonce before firing woocommerce_process_shop_coupon_meta.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $regions = isset( $_POST[ self::META_REGIONS ] )
            ? sanitize_text_field( wp_unslash( $_POST[ self::META_REGIONS ] ) )
            : '[]';
        $courier_scope = isset( $_POST[ self::META_COURIERS . '_scope' ] )
            ? sanitize_key( wp_unslash( $_POST[ self::META_COURIERS . '_scope' ] ) )
            : 'all';
        $couriers = ( 'selected' === $courier_scope && isset( $_POST[ self::META_COURIERS ] ) )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ self::META_COURIERS ] ) )
            : array();
        $combinations = isset( $_POST[ self::META_COMBINATIONS ] )
            ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ self::META_COMBINATIONS ] ) )
            : array();
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $discountType = $coupon instanceof \WC_Coupon ? $coupon->get_discount_type() : '';

        update_post_meta( $post_id, self::META_REGIONS, wp_json_encode( $this->normalizeRegions( $regions ) ) );
        update_post_meta( $post_id, self::META_COURIERS, array_values( array_unique( array_filter( $couriers ) ) ) );

        if ( $this->isShippingDiscountType( $discountType ) ) {
            $allowedTypes = array_keys( $this->getCombinationTypes() );
            // If "Individual use only" is checked, combinations are unavailable — clear them.
            $isIndividualUse = isset( $_POST['individual_use'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['individual_use'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce before firing this hook
            $resolvedCombinations = $isIndividualUse
                ? array()
                : array_values( array_intersect( $allowedTypes, $combinations ) );
            update_post_meta( $post_id, self::META_COMBINATIONS, $resolvedCombinations );
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
        if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only coupon type detection for JS config
            $coupon = new \WC_Coupon( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
                    // translators: %s is the province name (e.g. "Jawa Barat").
                    'allCitiesLabel' => __( 'All cities in %s', 'kiriminaja-official' ),
                    // translators: %1$s is the city name, %2$s is the province name.
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
                    'percentageExceeds100' => __( 'Discount amount cannot exceed 100% for a percentage shipping discount.', 'kiriminaja-official' ),
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

    public function flushCouriersCache() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'kiriminaja-official' ) ), 403 );
        }

        $service       = new KiriminajaApiService();
        $service->invalidateCouriersCache();
        $fresh = $service->get_couriers();

        if ( 200 !== $fresh->status ) {
            wp_send_json_error( array( 'message' => __( 'Flushed, but could not re-fetch from API. Will retry on next page load.', 'kiriminaja-official' ) ) );
        }

        $count = is_array( $fresh->data ) ? count( $fresh->data ) : 0;
        wp_send_json_success( array( 'count' => $count ) );
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

        if ( ! metadata_exists( 'post', $couponId, self::META_COMBINATIONS ) ) {
            return $allowedTypes;
        }

        $raw = get_post_meta( $couponId, self::META_COMBINATIONS, true );

        if ( is_array( $raw ) ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', $raw ) ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
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

    private function getCombinationTypes(): array {
        return array(
            'fixed_cart' => array(
                'label' => __( 'Fixed cart discount', 'kiriminaja-official' ),
                'icon'  => 'dashicons-cart',
            ),
            'percent' => array(
                'label' => __( 'Percentage discount', 'kiriminaja-official' ),
                'icon'  => 'dashicons-tag',
            ),
            'fixed_product' => array(
                'label' => __( 'Fixed product discount', 'kiriminaja-official' ),
                'icon'  => 'dashicons-products',
            ),
        );
    }

    private function formatRegionLabel( array $region ): string {
        if ( 'all_province' === $region['type'] ) {
            return __( 'All provinces in Indonesia', 'kiriminaja-official' );
        }

        if ( 'all_city_in_province' === $region['type'] ) {
            // translators: %s is the province name (e.g. "Jawa Barat").
            return sprintf( __( 'All cities in %s', 'kiriminaja-official' ), $region['province_name'] );
        }

        // translators: %1$s is the city name, %2$s is the province name.
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
