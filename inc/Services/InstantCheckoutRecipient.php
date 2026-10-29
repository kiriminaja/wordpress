<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared recipient context for quotes, native rate caching and final checkout. */
final class InstantCheckoutRecipient {
	public const FIELDS = array( 'first_name', 'last_name', 'phone' );
	private static $classic_customer = null;
	private static ?string $classic_phone = null;
	private static ?array $classic_contact = null;

	public static function classicContact( $customer, ?array $contact ): void {
		self::$classic_customer = $customer;
		self::$classic_contact = $contact;
	}

	/** Retain explicit phone clears for this verified review only, never in session history. */
	public static function classicPhone( $customer, ?string $phone ): void {
		self::$classic_customer = $customer;
		self::$classic_phone = $phone;
		self::$classic_contact = null;
	}

	/** Present package values (including empty/malformed values) are authoritative. */
	public static function resolve( array $package, $customer ): array {
		$address = is_array( $package['destination'] ?? null ) ? $package['destination'] : array();
		foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $address ) ) {
				$address[ $field ] = self::get( $customer, 'shipping', $field );
			}
		}
		$billing = array();
		foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
			$billing[ $field ] = self::get( $customer, 'billing', $field );
		}
		$same = false;
		try {
			$same = BuyerDestination::address( $address ) === BuyerDestination::address( $billing );
		} catch ( \InvalidArgumentException $error ) {
			// A malformed address must never authorize billing identity inheritance.
		}
		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $address ) ) {
				continue;
			}
			if ( $customer === self::$classic_customer && null !== self::$classic_contact ) {
				$address[ $field ] = self::$classic_contact[ $field ];
				continue;
			}
			if ( 'phone' === $field && $customer === self::$classic_customer && null !== self::$classic_phone ) {
				$address[ $field ] = self::$classic_phone;
				continue;
			}
			$value = self::get( $customer, 'shipping', $field );
			// Classic has no native shipping-phone input: billing is the contact.
			// Billing names, unlike contact phone, require the exact effective address.
			if ( '' === $value && ( 'phone' === $field || $same ) ) {
				$value = self::get( $customer, 'billing', $field );
			}
			$address[ $field ] = $value;
		}
		return $address;
	}

	/** Persist Classic's inherited contact so durable replay uses the same identity. */
	public static function inheritClassicOrderPhone( $order ): void {
		$phone = self::get( $order, 'shipping', 'phone' );
		if ( '' === $phone && is_callable( array( $order, 'set_shipping_phone' ) ) ) {
			$billing = self::get( $order, 'billing', 'phone' );
			if ( is_string( $billing ) ) {
				$order->set_shipping_phone( $billing );
			}
		}
	}

	private static function get( $customer, string $type, string $field ) {
		$getter = 'get_' . $type . '_' . $field;
		return is_callable( array( $customer, $getter ) ) ? $customer->$getter() : '';
	}
}
