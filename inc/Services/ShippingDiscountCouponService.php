<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingDiscountCouponService {
    public const FIXED_COUPON_TYPE = 'kiriof_fixed_shipping_discount';
    public const PERCENTAGE_COUPON_TYPE = 'kiriof_percent_shipping_discount';
    public const META_REGIONS = '_kiriof_coupon_regions';
    public const META_COURIERS = '_kiriof_coupon_couriers';
    public const META_COMBINATIONS = '_kiriof_coupon_combinations';

    public function getShippingCouponTypes(): array {
        return array(
            self::FIXED_COUPON_TYPE,
            self::PERCENTAGE_COUPON_TYPE,
        );
    }

    public function splitCouponCodesByScope( array $couponCodes ): array {
        $shippingCodes = array();
        $itemCodes     = array();

        foreach ( $couponCodes as $couponCode ) {
            $couponCode = sanitize_text_field( (string) $couponCode );
            if ( '' === $couponCode ) {
                continue;
            }

            $coupon = new \WC_Coupon( $couponCode );
            if ( $this->isShippingCoupon( $coupon ) ) {
                $shippingCodes[] = strtoupper( $couponCode );
                continue;
            }

            $itemCodes[] = strtoupper( $couponCode );
        }

        return array(
            'shipping' => array_values( array_unique( $shippingCodes ) ),
            'item'     => array_values( array_unique( $itemCodes ) ),
        );
    }

    public function isShippingCoupon( $coupon ): bool {
        if ( is_string( $coupon ) || is_numeric( $coupon ) ) {
            $coupon = new \WC_Coupon( $coupon );
        }

        return $coupon instanceof \WC_Coupon && in_array( $coupon->get_discount_type(), $this->getShippingCouponTypes(), true );
    }

    public function validateCouponForCart( $coupon, bool $requireSelectedShipping = true, bool $requireSelectedCourier = true ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array(
                'valid' => true,
                'message' => '',
            );
        }

        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return $this->invalidForCoupon( $coupon, __( 'Add items to your cart first.', 'kiriminaja-official' ) );
        }

        if ( empty( WC()->cart->get_cart() ) ) {
            return $this->invalidForCoupon( $coupon, __( 'Add items to your cart first.', 'kiriminaja-official' ) );
        }

        if ( ! $this->cartHasShippableProduct() ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon requires a physical product with shipping.', 'kiriminaja-official' ) );
        }

        $amount = (float) $coupon->get_amount();
        if ( ! is_finite( $amount ) || $amount <= 0 ) {
            return $this->invalidForCoupon( $coupon, __( 'Invalid coupon amount.', 'kiriminaja-official' ) );
        }

        // Free shipping and a shipping discount coupon cannot both reduce the same shipping cost.
        if ( $this->hasActiveFreeShippingCoupon() ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon cannot be combined with a free shipping coupon.', 'kiriminaja-official' ) );
        }

        if ( $this->hasOtherActiveShippingCoupon( $coupon ) ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon cannot be combined with another shipping discount coupon.', 'kiriminaja-official' ) );
        }

        if ( ! $this->couponAllowsActiveNativeCoupons( $coupon ) ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon cannot be combined with one or more active coupons.', 'kiriminaja-official' ) );
        }

        if ( $this->couponHasRegionRestrictions( $coupon ) ) {
            $destination = $this->getDestinationContext();
            if ( $destination['id'] < 1 && '' === $destination['name'] ) {
                return $this->invalidForCoupon( $coupon, __( 'Please enter your shipping address to check coupon eligibility.', 'kiriminaja-official' ) );
            }

            if ( ! $this->couponMatchesDestination( $coupon, $destination ) ) {
                return $this->invalidForCoupon( $coupon, __( 'This coupon is not valid for your shipping destination.', 'kiriminaja-official' ) );
            }
        }

        if ( $requireSelectedShipping && ! $this->isKiriminAjaShippingSelected() ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon is only valid when KiriminAja shipping is selected.', 'kiriminaja-official' ) );
        }

        if ( $requireSelectedCourier && ! $this->couponAllowsSelectedCourier( $coupon ) ) {
            return $this->invalidForCoupon( $coupon, __( 'This coupon is not valid for the selected courier.', 'kiriminaja-official' ) );
        }

        return array(
            'valid' => true,
            'message' => '',
        );
    }

    public function clearValidationNotices(): void {
        if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_set_notices' ) ) {
            return;
        }

        $notices = wc_get_notices();
        if ( empty( $notices['error'] ) || ! is_array( $notices['error'] ) ) {
            return;
        }

        $validationMessages = $this->getValidationMessages();
        $notices['error'] = array_values(
            array_filter(
                $notices['error'],
                static function ( $notice ) use ( $validationMessages ) {
                    $message = isset( $notice['notice'] ) ? wp_strip_all_tags( (string) $notice['notice'] ) : '';
                    if ( in_array( $message, $validationMessages, true ) ) {
                        return false;
                    }

                    foreach ( $validationMessages as $validationMessage ) {
                        if ( '' !== $validationMessage && str_contains( $message, $validationMessage ) ) {
                            return false;
                        }
                    }

                    return true;
                }
            )
        );

        wc_set_notices( $notices );
    }

    public function hasActiveShippingCouponInCart(): bool {
        return ! empty( $this->getShippingCoupons() );
    }

    private function isKiriminAjaShippingSelected(): bool {
        $chosen = $this->getChosenShippingMethods();
        // A real selection is authoritative: available KiriminAja rates must not
        // make an explicitly selected third-party method eligible.
        $methods = ! empty( $chosen ) ? $chosen : $this->getAvailableShippingRateIds();
        foreach ( $methods as $method ) {
            if ( '' !== $this->extractCourierCodeFromMethodId( (string) $method ) ) {
                return true;
            }
        }

        return false;
    }

    private function getAvailableShippingRateIds(): array {
        $packages = array();
        if ( function_exists( 'WC' ) && WC() && isset( WC()->shipping ) && WC()->shipping() && method_exists( WC()->shipping(), 'get_packages' ) ) {
            $packages = (array) WC()->shipping()->get_packages();
        } elseif ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_shipping_packages' ) ) {
            $packages = (array) WC()->cart->get_shipping_packages();
        }

        $rateIds = array();
        foreach ( $packages as $package ) {
            foreach ( (array) ( $package['rates'] ?? array() ) as $rateKey => $rate ) {
                if ( is_object( $rate ) && isset( $rate->id ) ) {
                    $rateIds[] = (string) $rate->id;
                    continue;
                }
                if ( is_object( $rate ) && method_exists( $rate, 'get_id' ) ) {
                    $rateIds[] = (string) $rate->get_id();
                    continue;
                }
                $rateIds[] = (string) $rateKey;
            }
        }

        return array_values( array_filter( array_unique( $rateIds ) ) );
    }

    private function getChosenShippingMethods(): array {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->session ) || ! WC()->session ) {
            return array();
        }

        $chosen = (array) WC()->session->get( 'chosen_shipping_methods', array() );
        if ( ! empty( $chosen ) ) {
            return $chosen;
        }

        $kiriofChosen = (array) WC()->session->get( 'kiriof_chosen_shipping_methods', array() );
        if ( ! empty( $kiriofChosen ) ) {
            return $kiriofChosen;
        }

        // During coupon-apply AJAX, WooCommerce can momentarily clear the core
        // chosen_shipping_methods session key before our checkout mirror is restored.
        // Preserve a just-posted shipping method so shipping coupon validation does
        // not falsely reject stacked coupons that otherwise remain eligible.
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Checkout/coupon request state is read-only here; WooCommerce verifies the action nonce before coupon processing.
        if ( isset( $_POST['shipping_method'] ) ) {
            $postedMethods = array_map(
                'sanitize_text_field',
                (array) wp_unslash( $_POST['shipping_method'] )
            );
            $postedMethods = array_values( array_filter( $postedMethods, 'strlen' ) );
            if ( ! empty( $postedMethods ) ) {
                // phpcs:enable WordPress.Security.NonceVerification.Missing
                return $postedMethods;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return array();
    }

    public function getAdjustedRatePricing( $option, float $baseCost ): array {
        $baseCost = max( 0.0, $baseCost );
        $result = array(
            'original_cost' => $baseCost,
            'cost' => $baseCost,
            'discount_amount' => 0.0,
            'eligible' => false,
            'notice' => '',
            'badge' => '',
        );

        // Carrier/service identities must be known, even for unrestricted or
        // free-shipping coupons. This helper never changes the carrier quote.
        $courierCode = $this->getOptionCourierCode( $option );
        if ( '' === $courierCode ) {
            return $result;
        }

        if ( $this->hasActiveFreeShippingCoupon() ) {
            // Waive buyer delivery only; do not mutate the carrier quote used for
            // insurance, COD fees, validation, or transaction submission.
            $result['cost'] = 0.0;
            $result['discount_amount'] = max( 0.0, $baseCost );
            $result['eligible'] = true;
            $result['notice'] = __( 'Free shipping', 'kiriminaja-official' );
            $result['badge'] = __( 'Free shipping', 'kiriminaja-official' );
            return $result;
        }

        $coupons = $this->getShippingCoupons();
        if ( empty( $coupons ) ) {
            return $result;
        }

        $remainingCost = max( 0, $baseCost );
        $matchedDestination = false;
        $matchedCourier = false;
        $discountTotal = 0.0;
        $appliedCouponTypes = array();

        foreach ( $coupons as $coupon ) {
            $validation = $this->validateCouponForCart( $coupon, false, false );
            if ( ! $validation['valid'] ) {
                continue;
            }

            $matchedDestination = true;
            if ( ! $this->couponAllowsCourier( $coupon, $courierCode ) ) {
                continue;
            }

            $matchedCourier = true;
            $couponAmount = max( 0, (float) $coupon->get_amount() );
            if ( $couponAmount <= 0 ) {
                continue;
            }

            $applied = $this->calculateCouponDiscount( $coupon, $remainingCost, $couponAmount );
            if ( $applied <= 0 ) {
                continue;
            }

            $discountTotal += $applied;
            $remainingCost -= $applied;
            $appliedCouponTypes[] = $coupon->get_discount_type();
        }

        if ( $discountTotal > 0 ) {
            $buyer_cost = max( 0.0, $baseCost - $discountTotal );
            if ( function_exists( 'wc_format_decimal' ) && function_exists( 'wc_get_price_decimals' ) ) {
                $buyer_cost = min( $baseCost, max( 0.0, (float) wc_format_decimal( $buyer_cost, wc_get_price_decimals() ) ) );
            }
            $result['cost'] = $buyer_cost;
            $result['discount_amount'] = $baseCost - $buyer_cost;
            $result['eligible'] = true;
            $result['badge'] = $this->getAppliedDiscountBadge( $appliedCouponTypes );
            return $result;
        }

        if ( $matchedDestination && ! $matchedCourier ) {
            $result['notice'] = __( 'Coupon not applicable', 'kiriminaja-official' );
        }

        return $result;
    }

    public function getDestinationContext(): array {
        $destinationId = 0;
        $destinationName = '';

        if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
            $destinationId = (int) ( WC()->session->get( 'shipping_destination_id' ) ?: WC()->session->get( 'destination_id' ) ?: 0 );
            $destinationName = (string) ( WC()->session->get( 'shipping_destination_name' ) ?: WC()->session->get( 'destination_name' ) ?: '' );
        }

        if ( $destinationId < 1 ) {
            $destinationId = $this->getPostedDestinationId();
        }

        if ( '' === $destinationName ) {
            $destinationName = $this->getPostedDestinationName();
        }

        return array(
            'id' => $destinationId,
            'name' => sanitize_text_field( $destinationName ),
            'normalized_name' => $this->normalizeText( $destinationName ),
        );
    }

    public function getCurrentShippingDiscountTotal(): float {
        $summary = $this->getCurrentShippingDiscountSummary();
        return (float) $summary['amount'];
    }

    public function getCurrentShippingDiscountSummary(): array {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->session ) || ! WC()->session ) {
            return array(
                'amount' => 0.0,
                'label'  => __( 'Shipping Discount', 'kiriminaja-official' ),
                'codes'  => array(),
                'rate_label' => '',
                'current_cost' => 0.0,
                'original_cost' => 0.0,
            );
        }

        $chosenMethods = WC()->session->get( 'chosen_shipping_methods', array() );
        if ( empty( $chosenMethods ) || ! is_array( $chosenMethods ) ) {
            return array(
                'amount' => 0.0,
                'label'  => __( 'Shipping Discount', 'kiriminaja-official' ),
                'codes'  => $this->getCurrentShippingCouponCodes(),
                'rate_label' => '',
                'current_cost' => 0.0,
                'original_cost' => 0.0,
            );
        }

        $packages = array();
        if ( isset( WC()->shipping ) && WC()->shipping() && method_exists( WC()->shipping(), 'get_packages' ) ) {
            $packages = (array) WC()->shipping()->get_packages();
        } elseif ( isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_shipping_packages' ) ) {
            $packages = (array) WC()->cart->get_shipping_packages();
        }

        $discountTotal = 0.0;
        $sessionRateMeta = (array) WC()->session->get( 'kiriof_shipping_coupon_rate_meta', array() );
        $primaryRateLabel = '';
        $primaryCurrentCost = 0.0;
        $primaryOriginalCost = 0.0;

        foreach ( $chosenMethods as $packageIndex => $chosenMethodId ) {
            $chosenMethodId = (string) $chosenMethodId;
            $rates = (array) ( $packages[ $packageIndex ]['rates'] ?? array() );

            foreach ( $rates as $rateKey => $rate ) {
                $rateId = is_object( $rate ) && method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : ( is_object( $rate ) && isset( $rate->id ) ? (string) $rate->id : (string) $rateKey );
                if ( $chosenMethodId !== $rateId ) {
                    continue;
                }

                // Discount meta is intentionally NOT stored on WC_Shipping_Rate->meta_data
                // (to prevent block checkout rendering raw numbers as sub-lines).
                // Always read discount/cost from session rate meta map instead.
                $sessionMeta = $sessionRateMeta[ $packageIndex ][ $chosenMethodId ] ?? $sessionRateMeta[ $chosenMethodId ] ?? array();
                $rateCost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : (float) ( $rate->cost ?? 0 );
                if ( ( isset( $sessionMeta['cost'] ) && abs( (float) $sessionMeta['cost'] - $rateCost ) > 0.00001 ) || ( ! $this->hasActiveShippingCouponInCart() && ! $this->hasActiveFreeShippingCoupon() ) ) {
                    $sessionMeta = array();
                }
                $sessionDiscount = isset( $sessionMeta['discount_amount'] ) ? max( 0, (float) $sessionMeta['discount_amount'] ) : 0.0;
                $discountTotal += $sessionDiscount;

                if ( '' === $primaryRateLabel ) {
                    $primaryRateLabel = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
                    $primaryCurrentCost = isset( $sessionMeta['cost'] ) ? (float) $sessionMeta['cost'] : $rateCost;
                    $primaryOriginalCost = isset( $sessionMeta['original_cost'] ) ? (float) $sessionMeta['original_cost'] : $primaryCurrentCost;
                }
                break;
            }


        }

        return array(
            'amount' => max( 0, $discountTotal ),
            'label'  => $this->getCurrentShippingDiscountLabel(),
            'codes'  => $this->getCurrentShippingCouponCodes(),
            'rate_label' => sanitize_text_field( $primaryRateLabel ),
            'current_cost' => max( 0, $primaryCurrentCost ),
            'original_cost' => max( 0, $primaryOriginalCost ),
        );
    }

    private function getShippingCoupons(): array {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return array();
        }

        return array_values(
            array_filter(
                (array) WC()->cart->get_coupons(),
                array( $this, 'isShippingCoupon' )
            )
        );
    }

    private function couponMatchesDestination( $coupon, array $destination ): bool {
        $regions = $this->getCouponRegions( $coupon );
        if ( empty( $regions ) ) {
            return true;
        }

        foreach ( $regions as $region ) {
            if ( ! is_array( $region ) ) {
                continue;
            }

            if ( $this->destinationMatchesRegion( $destination, $region ) ) {
                return true;
            }
        }

        return false;
    }

    private function couponHasRegionRestrictions( $coupon ): bool {
        return ! empty( $this->getCouponRegions( $coupon ) );
    }

    private function getCouponRegions( $coupon ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array();
        }

        $raw = get_post_meta( $coupon->get_id(), self::META_REGIONS, true );
        $regions = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

        return is_array( $regions ) ? $regions : array();
    }

    private function getRateDiscountAmount( $rate ): float {
        if ( ! is_object( $rate ) ) {
            return 0.0;
        }

        if ( method_exists( $rate, 'get_meta' ) ) {
            return max( 0, (float) $rate->get_meta( 'kiriof_shipping_coupon_discount_amount', true ) );
        }

        if ( isset( $rate->meta_data['kiriof_shipping_coupon_discount_amount'] ) ) {
            return max( 0, (float) $rate->meta_data['kiriof_shipping_coupon_discount_amount'] );
        }

        return 0.0;
    }

    private function getCurrentShippingCouponCodes(): array {
        $codes = array();

        foreach ( $this->getShippingCoupons() as $coupon ) {
            if ( $coupon instanceof \WC_Coupon ) {
                $codes[] = sanitize_text_field( (string) $coupon->get_code() );
            }
        }

        return array_values( array_filter( array_unique( $codes ) ) );
    }

    private function getCurrentShippingDiscountLabel(): string {
        $codes = $this->getCurrentShippingCouponCodes();

        if ( 1 === count( $codes ) ) {
            return sprintf(
                /* translators: %s shipping coupon code. */
                __( 'Shipping Discount (%s)', 'kiriminaja-official' ),
                $codes[0]
            );
        }

        if ( count( $codes ) > 1 ) {
            return __( 'Shipping Discounts', 'kiriminaja-official' );
        }

        return __( 'Shipping Discount', 'kiriminaja-official' );
    }

    private function destinationMatchesRegion( array $destination, array $region ): bool {
        $type = sanitize_key( (string) ( $region['type'] ?? '' ) );
        if ( 'all_province' === $type ) {
            return true;
        }

        $haystack = $destination['normalized_name'] ?? '';
        if ( '' === $haystack ) {
            return false;
        }

        $province = $this->normalizeText( (string) ( $region['province_name'] ?? '' ) );
        $city = $this->normalizeText( (string) ( $region['city_name'] ?? '' ) );

        if ( 'all_city_in_province' === $type ) {
            return '' !== $province && str_contains( $haystack, $province );
        }

        if ( 'specific_city' !== $type || '' === $city ) {
            return false;
        }

        if ( ! str_contains( $haystack, $city ) ) {
            return false;
        }

        return '' === $province || str_contains( $haystack, $province );
    }

    private function couponAllowsCourier( $coupon, string $courierCode ): bool {
        $couriers = $this->getCouponCouriers( $coupon );
        if ( empty( $couriers ) ) {
            return true;
        }

        $courierCode = $this->normalizeCourierCode( $courierCode );
        if ( '' === $courierCode ) {
            return false;
        }

        return in_array( $courierCode, $couriers, true );
    }

    private function getCouponCouriers( $coupon ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array();
        }

        $raw = get_post_meta( $coupon->get_id(), self::META_COURIERS, true );
        if ( is_array( $raw ) ) {
            return array_values( array_map( array( $this, 'normalizeCourierCode' ), $raw ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            return array_values( array_map( array( $this, 'normalizeCourierCode' ), explode( ',', $raw ) ) );
        }

        return array();
    }

    private function getCouponCombinations( $coupon ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array();
        }

        $allowedTypes = array( 'fixed_cart', 'percent', 'fixed_product' );

        if ( ! metadata_exists( 'post', $coupon->get_id(), self::META_COMBINATIONS ) ) {
            return $allowedTypes;
        }

        $raw = get_post_meta( $coupon->get_id(), self::META_COMBINATIONS, true );
        if ( is_array( $raw ) ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', $raw ) ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            return array_values( array_intersect( $allowedTypes, array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
        }

        return array();
    }

    private function couponAllowsActiveNativeCoupons( $coupon ): bool {
        if ( ! $this->isShippingCoupon( $coupon ) || ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return true;
        }

        $allowedTypes = $this->getCouponCombinations( $coupon );

        foreach ( (array) WC()->cart->get_coupons() as $activeCoupon ) {
            if ( ! $activeCoupon instanceof \WC_Coupon || $this->isShippingCoupon( $activeCoupon ) ) {
                continue;
            }

            $activeTypes = $this->getNativeCouponCombinationAliases( $activeCoupon );
            if ( empty( array_intersect( $activeTypes, $allowedTypes ) ) && ! $this->allNativeCouponCombinationsAllowed( $allowedTypes ) ) {
                return false;
            }
        }

        return true;
    }

    private function getNativeCouponCombinationAliases( \WC_Coupon $coupon ): array {
        $type = sanitize_key( (string) $coupon->get_discount_type() );
        $aliases = array( $type );

        if ( str_contains( $type, 'percent' ) || str_contains( $type, 'percentage' ) ) {
            $aliases[] = 'percent';
        }

        if ( str_contains( $type, 'product' ) ) {
            $aliases[] = 'fixed_product';
        }

        if ( str_contains( $type, 'cart' ) ) {
            $aliases[] = 'fixed_cart';
        }

        return array_values( array_unique( array_filter( $aliases ) ) );
    }

    private function allNativeCouponCombinationsAllowed( array $allowedTypes ): bool {
        $nativeTypes = array( 'fixed_cart', 'percent', 'fixed_product' );
        return empty( array_diff( $nativeTypes, $allowedTypes ) );
    }

    private function cartHasShippableProduct(): bool {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart || ! method_exists( WC()->cart, 'get_cart' ) ) {
            return false;
        }

        foreach ( (array) WC()->cart->get_cart() as $cartItem ) {
            $product = is_array( $cartItem ) && isset( $cartItem['data'] ) ? $cartItem['data'] : null;
            if ( ! $product || ! is_object( $product ) ) {
                continue;
            }

            if ( method_exists( $product, 'needs_shipping' ) ) {
                if ( $product->needs_shipping() ) {
                    return true;
                }
                continue;
            }

            if ( method_exists( $product, 'is_virtual' ) && ! $product->is_virtual() ) {
                return true;
            }
        }

        return false;
    }

    private function hasOtherActiveShippingCoupon( $coupon ): bool {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return false;
        }

        foreach ( $this->getShippingCoupons() as $activeCoupon ) {
            if ( ! $activeCoupon instanceof \WC_Coupon ) {
                continue;
            }

            if ( (int) $activeCoupon->get_id() === (int) $coupon->get_id() ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function couponAllowsSelectedCourier( $coupon ): bool {
        $chosenCourier = $this->getChosenKiriminAjaCourierCode();
        if ( '' !== $chosenCourier ) {
            return $this->couponAllowsCourier( $coupon, $chosenCourier );
        }
        if ( ! empty( $this->getChosenShippingMethods() ) ) {
            return false;
        }
        foreach ( $this->getAvailableShippingRateIds() as $methodId ) {
            $courier = $this->extractCourierCodeFromMethodId( (string) $methodId );
            if ( '' !== $courier && $this->couponAllowsCourier( $coupon, $courier ) ) {
                return true;
            }
        }
        return false;
    }

    private function getChosenKiriminAjaCourierCode(): string {
        foreach ( $this->getChosenShippingMethods() as $methodId ) {
            $courierCode = $this->extractCourierCodeFromMethodId( (string) $methodId );
            if ( '' !== $courierCode ) {
                return $courierCode;
            }
        }
        return '';
    }

    private function getOptionCourierCode( $option ): string {
        if ( ! is_object( $option ) ) {
            return '';
        }
        // WC_Shipping_Rate has accessors and metadata, not a service property.
        if ( method_exists( $option, 'get_id' ) ) {
            $courier = $this->extractCourierCodeFromMethodId( (string) $option->get_id() );
            if ( '' !== $courier ) {
                return $courier;
            }
            $methodId = method_exists( $option, 'get_method_id' ) ? (string) $option->get_method_id() : '';
            if ( in_array( $methodId, array( 'kiriminaja-official', 'kiriminaja-instant' ), true ) && method_exists( $option, 'get_meta' ) ) {
                foreach ( array( 'kiriof_instant_courier', 'kiriof_rate_service', 'kiriof_rate_courier' ) as $key ) {
                    $courier = $this->normalizeCourierCode( (string) $option->get_meta( $key, true ) );
                    if ( '' !== $courier ) {
                        return $courier;
                    }
                }
            }
            // Do not let third-party rate metadata masquerade as our method.
            return '';
        }
        foreach ( array( 'courier', 'courier_code', 'code', 'service' ) as $field ) {
            if ( ! empty( $option->{$field} ) ) {
                $courier = $this->normalizeCourierCode( (string) $option->{$field} );
                if ( '' !== $courier ) {
                    return $courier;
                }
            }
        }
        return '';
    }

    private function extractCourierCodeFromMethodId( string $methodId ): string {
        if ( preg_match( '/^kiriminaja-instant:[0-9]+:([^:]+):[^:]+$/', $methodId, $matches ) ) {
            $courier = $this->normalizeCourierCode( $matches[1] );
            return in_array( $courier, CourierServiceCatalog::instantCodes(), true ) ? $courier : '';
        }
        if ( ! preg_match( '/^kiriminaja-official(?:_|:)(?:[0-9]+:)?(.+)$/', $methodId, $matches ) ) {
            return '';
        }
        // Match complete codes before the service delimiter (grab_express must
        // never become grab). Longer codes win over overlapping prefixes.
        $suffix = strtolower( $matches[1] );
        $codes = array_keys( CourierServiceCatalog::available() );
        usort( $codes, static function ( $left, $right ) { return strlen( $right ) <=> strlen( $left ); } );
        foreach ( $codes as $code ) {
            if ( $suffix === $code || str_starts_with( $suffix, $code . '_' ) || str_starts_with( $suffix, $code . ':' ) ) {
                return $this->normalizeCourierCode( $code );
            }
        }
        $parts = preg_split( '/[_:]/', $suffix );
        return $this->normalizeCourierCode( (string) ( $parts[0] ?? '' ) );
    }

    private function normalizeCourierCode( $value ): string {
        $normalized = strtolower( trim( (string) $value ) );
        // Exact aliases only. Never remove "express" from arbitrary codes.
        $aliases = array(
            'jt' => 'jnt', 'j&t' => 'jnt', 'jandt' => 'jnt',
            'jtexpress' => 'jnt', 'j&t express' => 'jnt', 'jnt express' => 'jnt',
            'jne express' => 'jne', 'sap express' => 'sap',
            'ncs courier' => 'ncs', 'rpx logistics' => 'rpx',
            'go-send' => 'gosend', 'go send' => 'gosend',
            'grabexpress' => 'grab_express', 'grab express' => 'grab_express',
            'grab-express' => 'grab_express',
        );
        $normalized = $aliases[ $normalized ] ?? $normalized;
        $known = array_merge( CourierServiceCatalog::available(), CourierServiceCatalog::instantServices() );
        return isset( $known[ $normalized ] ) ? $normalized : '';
    }

    private function getPostedDestinationId(): int {
        foreach ( $this->getRequestValuesByKeys(
            array(
                'kiriof_shipping_destination_area',
                'kiriof_destination_area',
                'shipping_destination_id',
                'destination_id',
                'destination_area_id',
                'kiriminaja-official/kiriof_destination_area',
            )
        ) as $value ) {
            if ( is_numeric( $value ) ) {
                return (int) $value;
            }
        }

        return 0;
    }

    private function getPostedDestinationName(): string {
        foreach ( $this->getRequestValuesByKeys(
            array(
                'kiriof_shipping_destination_area_name',
                'kiriof_destination_area_name',
                'shipping_destination_name',
                'destination_name',
            )
        ) as $value ) {
            $value = sanitize_text_field( (string) $value );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    private function getRequestValuesByKeys( array $keys ): array {
        $found = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only checkout request state; caller sanitizes resolved values.
        $stack = array( $_POST );

        while ( ! empty( $stack ) ) {
            $candidate = array_pop( $stack );
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            foreach ( $candidate as $key => $value ) {
                if ( is_array( $value ) ) {
                    $stack[] = $value;
                }

                if ( ! is_string( $key ) || ! in_array( $key, $keys, true ) || is_array( $value ) ) {
                    continue;
                }

                $found[] = wp_unslash( $value );
            }
        }

        return $found;
    }

    private function getValidationMessages(): array {
        return array(
            __( 'Invalid coupon amount.', 'kiriminaja-official' ),
            __( 'Add items to your cart first.', 'kiriminaja-official' ),
            __( 'This coupon requires a physical product with shipping.', 'kiriminaja-official' ),
            __( 'This coupon cannot be combined with a free shipping coupon.', 'kiriminaja-official' ),
            __( 'This coupon cannot be combined with another shipping discount coupon.', 'kiriminaja-official' ),
            __( 'This coupon cannot be combined with one or more active coupons.', 'kiriminaja-official' ),
            __( 'Please enter your shipping address to check coupon eligibility.', 'kiriminaja-official' ),
            __( 'This coupon is not valid for your shipping destination.', 'kiriminaja-official' ),
            __( 'This coupon is only valid when KiriminAja shipping is selected.', 'kiriminaja-official' ),
            __( 'This coupon is not valid for the selected courier.', 'kiriminaja-official' ),
        );
    }

    public function hasActiveFreeShippingCoupon(): bool {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_coupons() as $coupon ) {
            if ( $coupon && method_exists( $coupon, 'get_free_shipping' ) && $coupon->get_free_shipping() ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText( string $value ): string {
        $value = (string) $value;
        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }

        if ( function_exists( 'mb_strtolower' ) ) {
            $value = mb_strtolower( $value, 'UTF-8' );
        } else {
            $value = strtolower( $value );
        }

        $value = preg_replace( '/[^a-z0-9]+/i', ' ', $value ) ?: '';
        return trim( preg_replace( '/\s+/', ' ', $value ) ?: '' );
    }

    private function calculateCouponDiscount( \WC_Coupon $coupon, float $remainingCost, float $couponAmount ): float {
        $discountType = $coupon->get_discount_type();

        if ( self::PERCENTAGE_COUPON_TYPE === $discountType ) {
            $percentage = min( 100, $couponAmount );
            return min( $remainingCost, round( $remainingCost * ( $percentage / 100 ), wc_get_price_decimals() ) );
        }

        return min( $remainingCost, $couponAmount );
    }

    private function getAppliedDiscountBadge( array $appliedCouponTypes ): string {
        $appliedCouponTypes = array_values( array_unique( array_filter( array_map( 'strval', $appliedCouponTypes ) ) ) );

        if ( 1 === count( $appliedCouponTypes ) ) {
            if ( self::FIXED_COUPON_TYPE === $appliedCouponTypes[0] ) {
                return __( 'Fixed shipping discount applied', 'kiriminaja-official' );
            }

            if ( self::PERCENTAGE_COUPON_TYPE === $appliedCouponTypes[0] ) {
                return __( 'Percentage shipping discount applied', 'kiriminaja-official' );
            }
        }

        return __( 'Shipping discounts applied', 'kiriminaja-official' );
    }

    private function invalidForCoupon( $coupon, string $reason ): array {
        $code = $coupon instanceof \WC_Coupon ? $coupon->get_code() : '';
        if ( '' === $code ) {
            return $this->invalid( $reason );
        }

        return $this->invalid(
            sprintf(
                /* translators: 1: coupon code, 2: validation reason */
                __( 'Coupon "%1$s" cannot be applied. %2$s', 'kiriminaja-official' ),
                $code,
                $reason
            )
        );
    }

    private function invalid( string $message ): array {
        return array(
            'valid' => false,
            'message' => $message,
        );
    }
}
