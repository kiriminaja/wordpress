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

    public function getShippingCouponTypes(): array {
        return array(
            self::FIXED_COUPON_TYPE,
            self::PERCENTAGE_COUPON_TYPE,
        );
    }

    public function isShippingCoupon( $coupon ): bool {
        if ( is_string( $coupon ) || is_numeric( $coupon ) ) {
            $coupon = new \WC_Coupon( $coupon );
        }

        return $coupon instanceof \WC_Coupon && in_array( $coupon->get_discount_type(), $this->getShippingCouponTypes(), true );
    }

    public function validateCouponForCart( $coupon ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array(
                'valid' => true,
                'message' => '',
            );
        }

        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return $this->invalid( __( 'Add items to your cart first.', 'kiriminaja-official' ) );
        }

        if ( empty( WC()->cart->get_cart() ) ) {
            return $this->invalid( __( 'Add items to your cart first.', 'kiriminaja-official' ) );
        }

        if ( ! WC()->cart->needs_shipping() ) {
            return $this->invalid( __( 'This coupon requires a physical product with shipping.', 'kiriminaja-official' ) );
        }

        $destination = $this->getDestinationContext();
        if ( $destination['id'] < 1 && '' === $destination['name'] ) {
            return $this->invalid( __( 'Please enter your shipping address to check coupon eligibility.', 'kiriminaja-official' ) );
        }

        if ( ! $this->couponMatchesDestination( $coupon, $destination ) ) {
            return $this->invalid( __( 'This coupon is not valid for your shipping destination.', 'kiriminaja-official' ) );
        }

        return array(
            'valid' => true,
            'message' => '',
        );
    }

    public function getAdjustedRatePricing( $option, float $baseCost ): array {
        $result = array(
            'original_cost' => $baseCost,
            'cost' => $baseCost,
            'discount_amount' => 0.0,
            'eligible' => false,
            'notice' => '',
            'badge' => '',
        );

        if ( $this->hasActiveFreeShippingCoupon() ) {
            return $result;
        }

        $coupons = $this->getShippingCoupons();
        if ( empty( $coupons ) ) {
            return $result;
        }

        $courierCode = sanitize_key( (string) ( $option->service ?? '' ) );
        $remainingCost = max( 0, $baseCost );
        $matchedDestination = false;
        $matchedCourier = false;
        $discountTotal = 0.0;
        $appliedCouponTypes = array();

        foreach ( $coupons as $coupon ) {
            $validation = $this->validateCouponForCart( $coupon );
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
            $result['cost'] = max( 0, $baseCost - $discountTotal );
            $result['discount_amount'] = $discountTotal;
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
            $matchedRate = false;

            foreach ( $rates as $rateKey => $rate ) {
                $rateId = is_object( $rate ) && isset( $rate->id ) ? (string) $rate->id : (string) $rateKey;
                if ( $chosenMethodId !== $rateId ) {
                    continue;
                }

                $discountTotal += $this->getRateDiscountAmount( $rate );
                if ( '' === $primaryRateLabel ) {
                    $primaryRateLabel = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
                    $primaryCurrentCost = isset( $rate->cost ) ? (float) $rate->cost : 0.0;
                    $primaryOriginalCost = method_exists( $rate, 'get_meta' )
                        ? (float) $rate->get_meta( 'kiriof_shipping_coupon_original_cost', true )
                        : $primaryCurrentCost;
                }
                $matchedRate = true;
                break;
            }

            if ( ! $matchedRate && isset( $sessionRateMeta[ $chosenMethodId ]['discount_amount'] ) ) {
                $discountTotal += max( 0, (float) $sessionRateMeta[ $chosenMethodId ]['discount_amount'] );
                if ( '' === $primaryRateLabel ) {
                    $primaryRateLabel = (string) ( $sessionRateMeta[ $chosenMethodId ]['label'] ?? '' );
                    $primaryCurrentCost = (float) ( $sessionRateMeta[ $chosenMethodId ]['cost'] ?? 0 );
                    $primaryOriginalCost = (float) ( $sessionRateMeta[ $chosenMethodId ]['original_cost'] ?? $primaryCurrentCost );
                }
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

        return in_array( sanitize_key( $courierCode ), $couriers, true );
    }

    private function getCouponCouriers( $coupon ): array {
        if ( ! $this->isShippingCoupon( $coupon ) ) {
            return array();
        }

        $raw = get_post_meta( $coupon->get_id(), self::META_COURIERS, true );
        if ( is_array( $raw ) ) {
            return array_values( array_filter( array_map( 'sanitize_key', $raw ) ) );
        }

        if ( is_string( $raw ) && '' !== $raw ) {
            return array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
        }

        return array();
    }

    private function hasActiveFreeShippingCoupon(): bool {
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

    private function invalid( string $message ): array {
        return array(
            'valid' => false,
            'message' => $message,
        );
    }
}
