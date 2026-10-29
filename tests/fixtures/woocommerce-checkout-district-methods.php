<?php
/** WooCommerce 9.9.5 WC_Checkout get_posted_data / validate_posted_data, unmodified methods.
 * Source: https://github.com/woocommerce/woocommerce/blob/9.9.5/plugins/woocommerce/includes/class-wc-checkout.php
 * GPL-3.0-or-later. Only these methods run; no order, payment or network operations.
 */
trait WooCommerceDistrictCheckoutMethods {
	public function get_posted_data() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$data = array(
			'terms'                              => (int) isset( $_POST['terms'] ),
			'terms-field'                        => (int) isset( $_POST['terms-field'] ),
			'createaccount'                      => (int) ( $this->is_registration_enabled() ? ! empty( $_POST['createaccount'] ) : false ),
			'payment_method'                     => isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '',
			'shipping_method'                    => isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : '',
			'ship_to_different_address'          => ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only(),
			'woocommerce_checkout_update_totals' => isset( $_POST['woocommerce_checkout_update_totals'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$skipped        = array();
		$form_was_shown = isset( $_POST['woocommerce-process-checkout-nonce'] ); // phpcs:disable WordPress.Security.NonceVerification.Missing

		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
				$skipped[] = $fieldset_key;
				continue;
			}

			foreach ( $fieldset as $key => $field ) {
				$type = sanitize_title( isset( $field['type'] ) ? $field['type'] : 'text' );

				if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) { // phpcs:disable WordPress.Security.NonceVerification.Missing
					$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				} elseif ( isset( $field['default'] ) && 'checkbox' !== $type && ! $form_was_shown ) {
					$value = $field['default'];
				} else {
					$value = '';
				}

				if ( '' !== $value ) {
					switch ( $type ) {
						case 'checkbox':
							$value = 1;
							break;
						case 'multiselect':
							$value = implode( ', ', wc_clean( $value ) );
							break;
						case 'textarea':
							$value = wc_sanitize_textarea( $value );
							break;
						case 'password':
							if ( $data['createaccount'] && 'account_password' === $key ) {
								$value = wp_slash( $value ); // Passwords are encrypted with slashes on account creation, so we need to slash here too.
							}
							break;
						default:
							$value = wc_clean( $value );
							break;
					}
				}

				$data[ $key ] = apply_filters( 'woocommerce_process_checkout_' . $type . '_field', apply_filters( 'woocommerce_process_checkout_field_' . $key, $value ) );
			}
		}

		if ( in_array( 'shipping', $skipped, true ) && ( WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only() ) ) {
			foreach ( $this->get_checkout_fields( 'shipping' ) as $key => $field ) {
				$data[ $key ] = isset( $data[ 'billing_' . substr( $key, 9 ) ] ) ? $data[ 'billing_' . substr( $key, 9 ) ] : '';
			}
		}

		// BW compatibility.
		$this->legacy_posted_data = $data;

		return apply_filters( 'woocommerce_checkout_posted_data', $data );
	}

	/**
	 * Validates the posted checkout data based on field properties.
	 *
	 * @since  3.0.0
	 * @param  array    $data   An array of posted data.
	 * @param  WP_Error $errors Validation error.
	 */
	protected function validate_posted_data( &$data, &$errors ) {
		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			$validate_fieldset = true;
			if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
				$validate_fieldset = false;
			}

			foreach ( $fieldset as $key => $field ) {
				if ( ! isset( $data[ $key ] ) ) {
					continue;
				}
				$required    = ! empty( $field['required'] );
				$format      = array_filter( isset( $field['validate'] ) ? (array) $field['validate'] : array() );
				$field_label = isset( $field['label'] ) ? $field['label'] : '';

				if ( $validate_fieldset &&
					( isset( $field['type'] ) && 'country' === $field['type'] && '' !== $data[ $key ] ) &&
					! WC()->countries->country_exists( $data[ $key ] ) ) {
						/* translators: ISO 3166-1 alpha-2 country code */
						$errors->add( $key . '_validation', sprintf( __( "'%s' is not a valid country code.", 'woocommerce' ), $data[ $key ] ) );
				}

				switch ( $fieldset_key ) {
					case 'shipping':
						/* translators: %s: field name */
						$field_label = sprintf( _x( 'Shipping %s', 'checkout-validation', 'woocommerce' ), $field_label );
						break;
					case 'billing':
						/* translators: %s: field name */
						$field_label = sprintf( _x( 'Billing %s', 'checkout-validation', 'woocommerce' ), $field_label );
						break;
				}

				if ( in_array( 'postcode', $format, true ) ) {
					$country      = isset( $data[ $fieldset_key . '_country' ] ) ? $data[ $fieldset_key . '_country' ] : WC()->customer->{"get_{$fieldset_key}_country"}();
					$data[ $key ] = wc_format_postcode( $data[ $key ], $country );

					if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_postcode( $data[ $key ], $country ) ) {
						switch ( $country ) {
							case 'IE':
								/* translators: %1$s: field name, %2$s finder.eircode.ie URL */
								$postcode_validation_notice = sprintf( __( '%1$s is not valid. You can look up the correct Eircode <a target="_blank" href="%2$s">here</a>.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', 'https://finder.eircode.ie' );
								break;
							default:
								/* translators: %s: field name */
								$postcode_validation_notice = sprintf( __( '%s is not a valid postcode / ZIP.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
						}
						$errors->add( $key . '_validation', apply_filters( 'woocommerce_checkout_postcode_validation_notice', $postcode_validation_notice, $country, $data[ $key ] ), array( 'id' => $key ) );
					}
				}

				if ( in_array( 'phone', $format, true ) ) {
					// This is a safe sanitize to prevent copy-paste issues with invisible chars. Won't ensure validation.
					$data[ $key ] = wc_remove_non_displayable_chars( $data[ $key ] );

					if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_phone( $data[ $key ] ) ) {
						/* translators: %s: phone number */
						$errors->add( $key . '_validation', sprintf( __( '%s is not a valid phone number.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), array( 'id' => $key ) );
					}
				}

				if ( in_array( 'email', $format, true ) && '' !== $data[ $key ] ) {
					$email_is_valid = is_email( $data[ $key ] );
					$data[ $key ]   = sanitize_email( $data[ $key ] );

					if ( $validate_fieldset && ! $email_is_valid ) {
						/* translators: %s: email address */
						$errors->add( $key . '_validation', sprintf( __( '%s is not a valid email address.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), array( 'id' => $key ) );
						continue;
					}
				}

				if ( '' !== $data[ $key ] && in_array( 'state', $format, true ) ) {
					$country      = isset( $data[ $fieldset_key . '_country' ] ) ? $data[ $fieldset_key . '_country' ] : WC()->customer->{"get_{$fieldset_key}_country"}();
					$valid_states = WC()->countries->get_states( $country );

					if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
						$valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
						$data[ $key ]       = wc_strtoupper( $data[ $key ] );

						if ( isset( $valid_state_values[ $data[ $key ] ] ) ) {
							// With this part we consider state value to be valid as well, convert it to the state key for the valid_states check below.
							$data[ $key ] = $valid_state_values[ $data[ $key ] ];
						}

						if ( $validate_fieldset && ! in_array( $data[ $key ], $valid_state_values, true ) ) {
							/* translators: 1: state field 2: valid states */
							$errors->add( $key . '_validation', sprintf( __( '%1$s is not valid. Please enter one of the following: %2$s', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', implode( ', ', $valid_states ) ), array( 'id' => $key ) );
						}
					}
				}

				if ( $validate_fieldset && $required && '' === $data[ $key ] ) {
					/* translators: %s: field name */
					$errors->add( $key . '_required', apply_filters( 'woocommerce_checkout_required_field_notice', sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), $field_label, $key ), array( 'id' => $key ) );
				}
			}
		}
	}

}
