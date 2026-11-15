<?php
/**
 * Totals and promo code rules. All amounts are integer cents.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_coupon_types() {
	return array(
		'percent'       => __( 'Percentage off the order', 'coffee4u' ),
		'fixed'         => __( 'Fixed amount off the order', 'coffee4u' ),
		'free_beverage' => __( 'One free beverage (highest-priced)', 'coffee4u' ),
	);
}

function c4u_find_coupon( $code ) {
	$code = strtoupper( trim( (string) $code ) );
	if ( '' === $code ) {
		return null;
	}
	$posts = get_posts(
		array(
			'post_type'      => 'c4u_coupon',
			'post_status'    => 'publish',
			'title'          => $code,
			'posts_per_page' => 1,
		)
	);
	if ( ! $posts ) {
		return null;
	}
	return array(
		'id'               => $posts[0]->ID,
		'code'             => strtoupper( $posts[0]->post_title ),
		'type'             => get_post_meta( $posts[0]->ID, '_c4u_type', true ),
		'amount'           => (float) get_post_meta( $posts[0]->ID, '_c4u_amount', true ),
		'first_order_only' => '1' === get_post_meta( $posts[0]->ID, '_c4u_first_order_only', true ),
	);
}

/**
 * Returns the discount in cents, or a WP_Error explaining why the code does not apply.
 */
function c4u_coupon_discount( $coupon, $lines, $subtotal, $user_id ) {
	if ( ! $coupon ) {
		return new WP_Error( 'c4u_coupon_invalid', __( 'This promo code is not valid.', 'coffee4u' ) );
	}
	if ( $coupon['first_order_only'] ) {
		if ( ! $user_id ) {
			return new WP_Error( 'c4u_coupon_login', __( 'Sign in or sign up to use this promo code.', 'coffee4u' ) );
		}
		if ( c4u_user_has_paid_orders( $user_id ) ) {
			return new WP_Error( 'c4u_coupon_used', __( 'This promo code is only valid on your first order.', 'coffee4u' ) );
		}
	}
	switch ( $coupon['type'] ) {
		case 'free_beverage':
			$best = 0;
			foreach ( $lines as $line ) {
				if ( $line['is_beverage'] ) {
					$best = max( $best, $line['unit_price'] );
				}
			}
			if ( ! $best ) {
				return new WP_Error( 'c4u_coupon_beverage', __( 'Add a beverage to your order to use this promo code.', 'coffee4u' ) );
			}
			$discount = $best;
			break;
		case 'fixed':
			$discount = c4u_price_to_cents( $coupon['amount'] );
			break;
		default:
			$discount = (int) round( $subtotal * min( 100, $coupon['amount'] ) / 100 );
	}
	return min( $subtotal, $discount );
}

function c4u_calculate_totals( $cart, $user_id = null ) {
	$user_id  = null === $user_id ? get_current_user_id() : $user_id;
	$lines    = array();
	$subtotal = 0;
	foreach ( $cart['items'] as $product_id => $quantity ) {
		$product = c4u_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}
		$total    = $product['price'] * $quantity;
		$subtotal += $total;
		$lines[]  = array(
			'product_id'  => $product['id'],
			'name'        => $product['name'],
			'image'       => $product['image'],
			'unit_price'  => $product['price'],
			'quantity'    => $quantity,
			'total'       => $total,
			'is_beverage' => $product['is_beverage'],
		);
	}

	$discount     = 0;
	$coupon       = null;
	$coupon_error = null;
	if ( $cart['coupon'] ) {
		$found  = c4u_find_coupon( $cart['coupon'] );
		$result = c4u_coupon_discount( $found, $lines, $subtotal, $user_id );
		if ( is_wp_error( $result ) ) {
			$coupon_error = $result->get_error_message();
		} else {
			$discount = $result;
			$coupon   = $found['code'];
		}
	}

	$taxable     = $subtotal - $discount;
	$tax         = (int) round( $taxable * c4u_setting( 'tax_rate' ) / 100 );
	$service_fee = $lines ? c4u_price_to_cents( c4u_setting( 'service_fee' ) ) : 0;

	return array(
		'lines'        => $lines,
		'count'        => array_sum( wp_list_pluck( $lines, 'quantity' ) ),
		'subtotal'     => $subtotal,
		'discount'     => $discount,
		'coupon'       => $coupon,
		'coupon_error' => $coupon_error,
		'tax'          => $tax,
		'service_fee'  => $service_fee,
		'total'        => $taxable + $tax + $service_fee,
	);
}

/**
 * Summary rows as labelled on the "Proceed to Payment" screen.
 */
function c4u_totals_rows( $totals ) {
	return array(
		array(
			'label' => __( 'Order Total', 'coffee4u' ),
			'value' => c4u_format_money( $totals['subtotal'] ),
		),
		array(
			'label' => __( 'Discounts', 'coffee4u' ),
			'value' => $totals['discount'] ? '(' . c4u_format_money( $totals['discount'] ) . ')' : c4u_format_money( 0 ),
		),
		array(
			'label' => sprintf( /* translators: %s: tax rate */ __( 'Taxes (GST %s%%)', 'coffee4u' ), rtrim( rtrim( number_format( c4u_setting( 'tax_rate' ), 2 ), '0' ), '.' ) ),
			'value' => c4u_format_money( $totals['tax'] ),
		),
		array(
			'label' => __( 'Service Fees', 'coffee4u' ),
			'value' => c4u_format_money( $totals['service_fee'] ),
		),
	);
}
