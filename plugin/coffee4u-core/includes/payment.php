<?php
/**
 * Simulated card gateway. The full card number and CVV are validated and discarded; orders keep the brand and
 * last four digits, and saved cards (cards.php) also keep the expiry and name on card.
 * Test cards: any Luhn-valid number succeeds (e.g. 4242 4242 4242 4242); 4000 0000 0000 0002 is declined.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_luhn_valid( $number ) {
	$sum    = 0;
	$double = false;
	for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {
		$digit = (int) $number[ $i ];
		if ( $double ) {
			$digit *= 2;
			if ( $digit > 9 ) {
				$digit -= 9;
			}
		}
		$sum   += $digit;
		$double = ! $double;
	}
	return 0 === $sum % 10;
}

function c4u_card_brand( $number ) {
	if ( preg_match( '/^3[47]/', $number ) ) {
		return 'American Express';
	}
	if ( preg_match( '/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $number ) ) {
		return 'Mastercard';
	}
	if ( preg_match( '/^4/', $number ) ) {
		return 'Visa';
	}
	return '';
}

const C4U_DECLINED_CARD = '4000000000000002';

function c4u_card_expired( $month, $year ) {
	return gmmktime( 0, 0, 0, (int) $month + 1, 1, (int) $year ) <= time();
}

function c4u_cvv_valid( $cvv, $brand ) {
	return strlen( $cvv ) === ( 'American Express' === $brand ? 4 : 3 );
}

/**
 * Validates a new card from card_number, expiry, cvv and name. Returns array( $card, $errors ).
 */
function c4u_parse_card( $input ) {
	$number = preg_replace( '/\D/', '', (string) ( $input['card_number'] ?? '' ) );
	$expiry = preg_replace( '/\s/', '', (string) ( $input['expiry'] ?? '' ) );
	$cvv    = preg_replace( '/\D/', '', (string) ( $input['cvv'] ?? '' ) );
	$name   = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	$brand  = c4u_card_brand( $number );
	$errors = array();

	if ( strlen( $number ) < 13 || strlen( $number ) > 19 || ! c4u_luhn_valid( $number ) || ! $brand ) {
		$errors['card_number'] = __( 'Enter a valid Visa, Mastercard or American Express card number.', 'coffee4u' );
	}
	if ( ! preg_match( '#^(0[1-9]|1[0-2])/(\d{2})$#', $expiry, $m ) || c4u_card_expired( $m[1], 2000 + (int) $m[2] ) ) {
		$errors['expiry'] = __( 'Enter a future expiry date as MM/YY.', 'coffee4u' );
	}
	if ( ! c4u_cvv_valid( $cvv, $brand ) ) {
		$errors['cvv'] = __( 'Enter the security code on your card.', 'coffee4u' );
	}
	if ( strlen( $name ) < 2 ) {
		$errors['name'] = __( 'Enter the name shown on the card.', 'coffee4u' );
	}
	if ( $errors ) {
		return array( null, $errors );
	}
	$card = array(
		'brand'     => $brand,
		'last4'     => substr( $number, -4 ),
		'exp_month' => (int) $m[1],
		'exp_year'  => 2000 + (int) $m[2],
		'name'      => $name,
		'declines'  => C4U_DECLINED_CARD === $number,
	);
	return array( $card, array() );
}

/**
 * Runs $callback while holding a MySQL named lock, or returns null if the lock is not acquired.
 */
function c4u_with_db_lock( $name, callable $callback ) {
	global $wpdb;
	$lock = 'c4u_' . get_current_blog_id() . '_' . $name;
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
		return null;
	}
	try {
		return $callback();
	} finally {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

function c4u_process_payment( $order, $input ) {
	// Serialised per customer so concurrent requests cannot pay the same order twice
	// or redeem a first-order promo code on two orders.
	$charge = function () use ( $order, $input ) {
		// Re-read inside the lock: a concurrent request may have just paid this or another order.
		wp_cache_delete( $order['id'], 'post_meta' );
		return c4u_charge_order( c4u_get_order( $order['id'] ), $input );
	};
	$result = c4u_with_db_lock(
		'pay_' . (int) $order['user_id'],
		// A group order is also locked against the organizer re-opening the group mid-payment.
		fn() => $order['group'] ? c4u_with_group_lock( $order['group']['id'], $charge ) : $charge()
	);
	return $result ?? new WP_Error( 'c4u_payment_busy', __( 'Another payment is in progress. Please try again.', 'coffee4u' ), array( 'status' => 409 ) );
}

function c4u_charge_order( $order, $input ) {
	$payable = c4u_order_payable( $order );
	if ( is_wp_error( $payable ) ) {
		return $payable;
	}

	$phone   = preg_replace( '/[^\d+]/', '', (string) ( $input['phone'] ?? '' ) );
	$card_id = sanitize_key( (string) ( $input['card_id'] ?? '' ) );
	if ( '' !== $card_id ) {
		$card   = c4u_find_card( $order['user_id'], $card_id );
		$cvv    = preg_replace( '/\D/', '', (string) ( $input['card_cvv'] ?? '' ) );
		$errors = array();
		if ( ! $card ) {
			$errors['card_id'] = __( 'Choose one of your saved cards or use a new card.', 'coffee4u' );
		} elseif ( c4u_card_expired( $card['exp_month'], $card['exp_year'] ) ) {
			$errors['card_id'] = __( 'This card has expired. Choose another card.', 'coffee4u' );
		} elseif ( ! c4u_cvv_valid( $cvv, $card['brand'] ) ) {
			$errors['card_cvv'] = __( 'Enter the security code on your card.', 'coffee4u' );
		}
	} else {
		list( $card, $errors ) = c4u_parse_card( $input );
	}
	if ( strlen( ltrim( $phone, '+' ) ) < 7 ) {
		$errors['phone'] = __( 'Enter a contact number.', 'coffee4u' );
	}
	if ( $errors ) {
		return new WP_Error(
			'c4u_payment_invalid',
			__( 'Please check your payment details.', 'coffee4u' ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}
	if ( $card['declines'] ) {
		return new WP_Error( 'c4u_payment_declined', __( 'Your card was declined. Please use a different card.', 'coffee4u' ), array( 'status' => 402 ) );
	}

	update_post_meta(
		$order['id'],
		'_c4u_payment',
		array(
			'brand'     => $card['brand'],
			'last4'     => $card['last4'],
			'reference' => 'SIM-' . strtoupper( wp_generate_password( 10, false ) ),
			'name'      => $card['name'],
			'phone'     => $phone,
			'paid_at'   => time(),
		)
	);
	update_post_meta( $order['id'], '_c4u_status', 'paid' );
	if ( '' === $card_id && ! empty( $input['save_card'] ) ) {
		// Best effort: a card that is already saved, or a full card list, does not fail the payment.
		c4u_store_card( $order['user_id'], $card );
	}

	// Only clear the cart this order was created from; a cart changed since then is kept.
	$cart = c4u_get_cart( $order['user_id'] );
	if ( $cart['location_id'] === $order['cart']['location_id'] && $cart['items'] == $order['cart']['items'] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- key order may differ.
		c4u_save_cart( c4u_empty_cart(), $order['user_id'] );
	}

	do_action( 'c4u_order_paid', $order['id'] );
	return c4u_get_order( $order['id'] );
}
