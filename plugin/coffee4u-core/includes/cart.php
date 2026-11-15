<?php
/**
 * Cart storage: user meta for customers, a transient keyed by an HttpOnly cookie for guests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const C4U_CART_COOKIE = 'c4u_cart';

function c4u_empty_cart() {
	return array(
		'location_id' => 0,
		'items'       => array(),
		'coupon'      => '',
	);
}

function c4u_guest_cart_token( $create = false ) {
	static $token = null;
	if ( null === $token && isset( $_COOKIE[ C4U_CART_COOKIE ] ) ) {
		$candidate = sanitize_key( wp_unslash( $_COOKIE[ C4U_CART_COOKIE ] ) );
		$token     = 32 === strlen( $candidate ) ? $candidate : null;
	}
	if ( null === $token && $create ) {
		$token = strtolower( wp_generate_password( 32, false ) );
		if ( ! headers_sent() ) {
			setcookie(
				C4U_CART_COOKIE,
				$token,
				array(
					'expires'  => time() + 2 * DAY_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}
	return $token;
}

function c4u_normalize_cart( $cart ) {
	$cart  = wp_parse_args( is_array( $cart ) ? $cart : array(), c4u_empty_cart() );
	$items = array();
	foreach ( (array) $cart['items'] as $product_id => $quantity ) {
		$quantity = (int) $quantity;
		if ( $quantity > 0 && c4u_get_product( (int) $product_id ) ) {
			$items[ (int) $product_id ] = min( 99, $quantity );
		}
	}
	$cart['items']       = $items;
	$cart['location_id'] = $items ? (int) $cart['location_id'] : 0;
	$cart['coupon']      = $items ? (string) $cart['coupon'] : '';
	return $cart;
}

function c4u_get_cart( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id ) {
		$cart = get_user_meta( $user_id, '_c4u_cart', true );
	} else {
		$token = c4u_guest_cart_token();
		$cart  = $token ? get_transient( 'c4u_cart_' . $token ) : array();
	}
	return c4u_normalize_cart( $cart );
}

function c4u_save_cart( $cart, $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	$cart    = c4u_normalize_cart( $cart );
	if ( $user_id ) {
		update_user_meta( $user_id, '_c4u_cart', $cart );
	} else {
		set_transient( 'c4u_cart_' . c4u_guest_cart_token( true ), $cart, 2 * DAY_IN_SECONDS );
	}
	return $cart;
}

/**
 * Moves a guest cart into the customer's cart after sign-in or sign-up.
 */
function c4u_merge_guest_cart( $user_id ) {
	$token = c4u_guest_cart_token();
	if ( ! $token ) {
		return;
	}
	$guest = c4u_normalize_cart( get_transient( 'c4u_cart_' . $token ) );
	delete_transient( 'c4u_cart_' . $token );
	if ( ! $guest['items'] ) {
		return;
	}
	$cart = c4u_get_cart( $user_id );
	if ( $cart['items'] && $cart['location_id'] === $guest['location_id'] ) {
		foreach ( $guest['items'] as $product_id => $quantity ) {
			$cart['items'][ $product_id ] = ( $cart['items'][ $product_id ] ?? 0 ) + $quantity;
		}
		$cart['coupon'] = $guest['coupon'] ? $guest['coupon'] : $cart['coupon'];
	} else {
		$cart = $guest;
	}
	c4u_save_cart( $cart, $user_id );
}

add_action(
	'wp_login',
	function ( $login, $user ) {
		c4u_merge_guest_cart( $user->ID );
	},
	10,
	2
);
