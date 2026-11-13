<?php
/**
 * Simulated card gateway. Card data is validated and discarded; only brand and last four digits are stored.
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

function c4u_process_payment( $order, $input ) {
	if ( 'pending_payment' !== $order['status'] ) {
		return new WP_Error( 'c4u_order_paid', __( 'This order has already been paid.', 'coffee4u' ), array( 'status' => 409 ) );
	}

	$number = preg_replace( '/\D/', '', (string) ( $input['card_number'] ?? '' ) );
	$expiry = preg_replace( '/\s/', '', (string) ( $input['expiry'] ?? '' ) );
	$cvv    = preg_replace( '/\D/', '', (string) ( $input['cvv'] ?? '' ) );
	$name   = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	$phone  = preg_replace( '/[^\d+]/', '', (string) ( $input['phone'] ?? '' ) );
	$brand  = c4u_card_brand( $number );
	$errors = array();

	if ( strlen( $number ) < 13 || strlen( $number ) > 19 || ! c4u_luhn_valid( $number ) || ! $brand ) {
		$errors['card_number'] = __( 'Enter a valid Visa, Mastercard or American Express card number.', 'coffee4u' );
	}
	if ( ! preg_match( '#^(0[1-9]|1[0-2])/(\d{2})$#', $expiry, $m ) || gmmktime( 0, 0, 0, (int) $m[1] + 1, 1, 2000 + (int) $m[2] ) <= time() ) {
		$errors['expiry'] = __( 'Enter a future expiry date as MM/YY.', 'coffee4u' );
	}
	if ( strlen( $cvv ) !== ( 'American Express' === $brand ? 4 : 3 ) ) {
		$errors['cvv'] = __( 'Enter the security code on your card.', 'coffee4u' );
	}
	if ( strlen( $name ) < 2 ) {
		$errors['name'] = __( 'Enter the name shown on the card.', 'coffee4u' );
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
	if ( '4000000000000002' === $number ) {
		return new WP_Error( 'c4u_payment_declined', __( 'Your card was declined. Please use a different card.', 'coffee4u' ), array( 'status' => 402 ) );
	}

	update_post_meta(
		$order['id'],
		'_c4u_payment',
		array(
			'brand'     => $brand,
			'last4'     => substr( $number, -4 ),
			'reference' => 'SIM-' . strtoupper( wp_generate_password( 10, false ) ),
			'name'      => $name,
			'phone'     => $phone,
			'paid_at'   => time(),
		)
	);
	update_post_meta( $order['id'], '_c4u_status', 'paid' );
	c4u_save_cart( c4u_empty_cart(), $order['user_id'] );

	do_action( 'c4u_order_paid', $order['id'] );
	return c4u_get_order( $order['id'] );
}
