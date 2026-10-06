<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Server-formatted, display-only prices for Classic shipping choices. */
final class RateChoicePresentation {
	/**
	 * Strip price/filter markup before placing it in a plain text attribute.
	 *
	 * @param string $html Formatted display value.
	 * @return string
	 */
	private static function plain( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	/**
	 * Mirror WooCommerce's cart tax display, without parsing a formatted label.
	 *
	 * @param float $cost Rate cost before tax.
	 * @param float $tax Shipping taxes.
	 * @return string
	 */
	private static function price( float $cost, float $tax ): string {
		$cart = function_exists( 'WC' ) && WC() ? ( WC()->cart ?? null ) : null;
		$including_tax = $cart && is_callable( array( $cart, 'display_prices_including_tax' ) ) && $cart->display_prices_including_tax();
		$html = wc_price( $cost + ( $including_tax ? $tax : 0.0 ) );
		if ( $tax > 0 && function_exists( 'wc_prices_include_tax' ) && isset( WC()->countries ) ) {
			if ( $including_tax && ! wc_prices_include_tax() ) {
				$html .= ' ' . WC()->countries->inc_tax_or_vat();
			} elseif ( ! $including_tax && wc_prices_include_tax() ) {
				$html .= ' ' . WC()->countries->ex_tax_or_vat();
			}
		}
		return self::plain( $html );
	}

	/**
	 * Only actual session discounts may add comparison prices. No rate IDs or
	 * selection state are altered, and no monetary calculation is left to JS.
	 *
	 * @param object $rate WooCommerce shipping rate or compatible fixture.
	 * @param array  $session_meta Session pricing metadata keyed by rate ID.
	 * @return array<string, string>
	 */
	public static function forRate( $rate, array $session_meta ): array {
		$cost = (float) ( is_callable( array( $rate, 'get_cost' ) ) ? $rate->get_cost() : ( $rate->cost ?? 0.0 ) );
		$taxes = is_callable( array( $rate, 'get_taxes' ) ) ? (array) $rate->get_taxes() : array();
		$tax = array_sum( array_map( 'floatval', $taxes ) );
		$tax = is_finite( $tax ) ? $tax : 0.0;
		$id = is_callable( array( $rate, 'get_id' ) ) ? $rate->get_id() : ( $rate->id ?? '' );
		$meta = isset( $session_meta[ $id ] ) && is_array( $session_meta[ $id ] ) ? $session_meta[ $id ] : array();
		$result = array(
			'label' => (string) ( is_callable( array( $rate, 'get_label' ) ) ? $rate->get_label() : ( $rate->label ?? '' ) ),
			'price' => is_finite( $cost ) && $cost >= 0 ? self::price( $cost, $tax ) : '',
		);
		$original = (float) ( $meta['original_cost'] ?? 0.0 );
		$discount = (float) ( $meta['discount_amount'] ?? 0.0 );
		if ( is_finite( $cost ) && $cost >= 0 && is_finite( $original ) && is_finite( $discount ) && $discount > 0 && $original > $cost ) {
			// Session stores pre-tax original cost only; keep the actual rate's tax
			// component on both prices rather than inventing an original tax rate.
			$result['original-price'] = self::price( $original, $tax );
			/* translators: %s: formatted shipping discount amount. */
			$result['savings'] = self::plain( sprintf( __( 'Save %s', 'kiriminaja-official' ), self::plain( wc_price( $discount ) ) ) );
			if ( ! empty( $meta['badge'] ) ) {
				$result['note'] = self::plain( (string) $meta['badge'] );
			} elseif ( ! empty( $meta['notice'] ) ) {
				$result['note'] = self::plain( (string) $meta['notice'] );
			}
		} elseif ( ! empty( $meta['notice'] ) ) {
			$result['note'] = self::plain( (string) $meta['notice'] );
		}
		return $result;
	}
}
