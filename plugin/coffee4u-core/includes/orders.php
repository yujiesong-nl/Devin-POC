<?php
/**
 * Orders: creation from the cart, lookup and access control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_order_status_label( $status ) {
	$labels = array(
		'pending_payment' => __( 'Awaiting payment', 'coffee4u' ),
		'paid'            => __( 'Paid - preparing', 'coffee4u' ),
	);
	return $labels[ $status ] ?? $status;
}

function c4u_page_url( $slug, $args = array() ) {
	$page = get_page_by_path( $slug );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

function c4u_next_order_number() {
	global $wpdb;
	if ( false === get_option( 'c4u_next_order_number' ) ) {
		add_option( 'c4u_next_order_number', 12345, '', false );
	}
	// Atomic increment so concurrent checkouts never share a number.
	$wpdb->query( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = 'c4u_next_order_number'" );
	wp_cache_delete( 'c4u_next_order_number', 'options' );
	return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ) - 1;
}

function c4u_create_order_from_cart( $user_id ) {
	$cart     = c4u_get_cart( $user_id );
	$location = c4u_get_location( $cart['location_id'] );
	if ( ! $cart['items'] ) {
		return new WP_Error( 'c4u_cart_empty', __( 'Your cart is empty.', 'coffee4u' ), array( 'status' => 400 ) );
	}
	if ( ! $location || ! $location['is_open'] ) {
		return new WP_Error( 'c4u_location_closed', __( 'This location is not accepting orders yet.', 'coffee4u' ), array( 'status' => 400 ) );
	}
	$totals = c4u_calculate_totals( $cart, $user_id );
	if ( $totals['coupon_error'] ) {
		return new WP_Error( 'c4u_coupon_invalid', $totals['coupon_error'], array( 'status' => 400 ) );
	}

	$number   = c4u_next_order_number();
	$order_id = wp_insert_post(
		array(
			'post_type'   => 'c4u_order',
			'post_status' => 'publish',
			/* translators: %d: order number */
			'post_title'  => sprintf( __( 'Order #%d', 'coffee4u' ), $number ),
			'post_author' => $user_id,
		),
		true
	);
	if ( is_wp_error( $order_id ) ) {
		return $order_id;
	}
	update_post_meta( $order_id, '_c4u_number', $number );
	update_post_meta( $order_id, '_c4u_user_id', $user_id );
	update_post_meta( $order_id, '_c4u_status', 'pending_payment' );
	update_post_meta( $order_id, '_c4u_location_id', $location['id'] );
	update_post_meta( $order_id, '_c4u_location', $location );
	update_post_meta( $order_id, '_c4u_totals', $totals );
	return c4u_get_order( $order_id );
}

function c4u_get_order( $order_id ) {
	$post = get_post( $order_id );
	if ( ! $post || 'c4u_order' !== $post->post_type ) {
		return null;
	}
	$location = (array) get_post_meta( $post->ID, '_c4u_location', true );
	return array(
		'id'               => $post->ID,
		'number'           => (int) get_post_meta( $post->ID, '_c4u_number', true ),
		'user_id'          => (int) get_post_meta( $post->ID, '_c4u_user_id', true ),
		'status'           => (string) get_post_meta( $post->ID, '_c4u_status', true ),
		'location'         => $location,
		'totals'           => (array) get_post_meta( $post->ID, '_c4u_totals', true ),
		'payment'          => get_post_meta( $post->ID, '_c4u_payment', true ),
		'created'          => get_post_time( 'U', true, $post ),
		'ready_minutes'    => (int) ( $location['prep_minutes'] ?? 20 ),
		'payment_url'      => c4u_page_url( 'payment', array( 'order' => $post->ID ) ),
		'confirmation_url' => c4u_page_url( 'order-confirmed', array( 'order' => $post->ID ) ),
	);
}

/**
 * Returns the order when the signed-in customer owns it.
 */
function c4u_get_customer_order( $order_id ) {
	$order = c4u_get_order( $order_id );
	if ( ! $order || ! is_user_logged_in() || $order['user_id'] !== get_current_user_id() ) {
		return null;
	}
	return $order;
}

function c4u_user_has_paid_orders( $user_id ) {
	return (bool) get_posts(
		array(
			'post_type'      => 'c4u_order',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_c4u_user_id',
					'value' => $user_id,
				),
				array(
					'key'   => '_c4u_status',
					'value' => 'paid',
				),
			),
		)
	);
}

function c4u_get_customer_orders( $user_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'c4u_order',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'meta_key'       => '_c4u_user_id',
			'meta_value'     => $user_id,
		)
	);
	return array_filter( array_map( 'c4u_get_order', $ids ) );
}
