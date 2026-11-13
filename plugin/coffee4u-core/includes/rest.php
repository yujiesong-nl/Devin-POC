<?php
/**
 * REST API (namespace coffee4u/v1) used by the storefront scripts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_cart_response( $cart = null ) {
	$cart     = null === $cart ? c4u_get_cart() : $cart;
	$totals   = c4u_calculate_totals( $cart );
	$location = c4u_get_location( $cart['location_id'] );
	$lines    = array_map(
		function ( $line ) {
			$line['unit_price_formatted'] = c4u_format_money( $line['unit_price'] );
			$line['total_formatted']      = c4u_format_money( $line['total'] );
			return $line;
		},
		$totals['lines']
	);
	return array(
		'location'        => $location ? array(
			'id'       => $location['id'],
			'name'     => $location['name'],
			'menu_url' => $location['menu_url'],
		) : null,
		'items'           => $lines,
		'count'           => $totals['count'],
		'coupon'          => $totals['coupon'],
		'coupon_code'     => $cart['coupon'],
		'coupon_error'    => $totals['coupon_error'],
		'rows'            => c4u_totals_rows( $totals ),
		'subtotal'        => c4u_format_money( $totals['subtotal'] ),
		'total'           => c4u_format_money( $totals['total'] ),
		'checkout_url'    => c4u_page_url( 'checkout' ),
	);
}

function c4u_rest_error_response( WP_Error $error ) {
	$data = (array) $error->get_error_data();
	return new WP_REST_Response(
		array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'fields'  => $data['fields'] ?? (object) array(),
		),
		$data['status'] ?? 400
	);
}

add_action(
	'rest_api_init',
	function () {
		$ns     = 'coffee4u/v1';
		$public = '__return_true';
		$signed = 'is_user_logged_in';

		register_rest_route(
			$ns,
			'/cart',
			array(
				'methods'             => 'GET',
				'permission_callback' => $public,
				'callback'            => fn() => c4u_cart_response(),
			)
		);

		register_rest_route(
			$ns,
			'/cart/items',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'args'                => array(
					'product_id'  => array(
						'type'     => 'integer',
						'required' => true,
					),
					'location_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'quantity'    => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
						'maximum' => 99,
					),
					'replace'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					$product  = c4u_get_product( $request['product_id'] );
					$location = c4u_get_location( $request['location_id'] );
					if ( ! $product || ! $location ) {
						return c4u_rest_error_response( new WP_Error( 'c4u_not_found', __( 'This item is no longer available.', 'coffee4u' ), array( 'status' => 404 ) ) );
					}
					if ( ! $location['is_open'] ) {
						return c4u_rest_error_response( new WP_Error( 'c4u_location_closed', __( 'This location is not accepting orders yet.', 'coffee4u' ), array( 'status' => 400 ) ) );
					}
					$cart = c4u_get_cart();
					if ( $cart['items'] && $cart['location_id'] !== $location['id'] ) {
						if ( ! $request['replace'] ) {
							$current = c4u_get_location( $cart['location_id'] );
							return c4u_rest_error_response(
								new WP_Error(
									'c4u_location_mismatch',
									/* translators: %s: location name */
									sprintf( __( 'Your cart has items from %s. Start a new cart for this location?', 'coffee4u' ), $current ? $current['name'] : '' ),
									array( 'status' => 409 )
								)
							);
						}
						$cart = c4u_empty_cart();
					}
					$cart['location_id']                 = $location['id'];
					$cart['items'][ $product['id'] ] = ( $cart['items'][ $product['id'] ] ?? 0 ) + $request['quantity'];
					return c4u_cart_response( c4u_save_cart( $cart ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/cart/items/(?P<product_id>\d+)',
			array(
				'methods'             => 'PUT',
				'permission_callback' => $public,
				'args'                => array(
					'quantity' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 0,
						'maximum'  => 99,
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					$cart = c4u_get_cart();
					$id   = (int) $request['product_id'];
					if ( $request['quantity'] > 0 ) {
						$cart['items'][ $id ] = $request['quantity'];
					} else {
						unset( $cart['items'][ $id ] );
					}
					return c4u_cart_response( c4u_save_cart( $cart ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/cart/coupon',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => $public,
					'args'                => array(
						'code' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
					'callback'            => function ( WP_REST_Request $request ) {
						$cart   = c4u_get_cart();
						$totals = c4u_calculate_totals( $cart );
						$coupon = c4u_find_coupon( $request['code'] );
						$result = c4u_coupon_discount( $coupon, $totals['lines'], $totals['subtotal'], get_current_user_id() );
						if ( is_wp_error( $result ) ) {
							return c4u_rest_error_response( $result );
						}
						$cart['coupon'] = $coupon['code'];
						return c4u_cart_response( c4u_save_cart( $cart ) );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $public,
					'callback'            => function () {
						$cart           = c4u_get_cart();
						$cart['coupon'] = '';
						return c4u_cart_response( c4u_save_cart( $cart ) );
					},
				),
			)
		);

		register_rest_route(
			$ns,
			'/checkout',
			array(
				'methods'             => 'POST',
				'permission_callback' => $signed,
				'callback'            => function () {
					$order = c4u_create_order_from_cart( get_current_user_id() );
					if ( is_wp_error( $order ) ) {
						return c4u_rest_error_response( $order );
					}
					return array(
						'order_id'    => $order['id'],
						'number'      => $order['number'],
						'payment_url' => $order['payment_url'],
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/pay',
			array(
				'methods'             => 'POST',
				'permission_callback' => $signed,
				'callback'            => function ( WP_REST_Request $request ) {
					$order = c4u_get_customer_order( (int) $request['id'] );
					if ( ! $order ) {
						return c4u_rest_error_response( new WP_Error( 'c4u_not_found', __( 'Order not found.', 'coffee4u' ), array( 'status' => 404 ) ) );
					}
					$paid = c4u_process_payment( $order, $request->get_json_params() );
					if ( is_wp_error( $paid ) ) {
						return c4u_rest_error_response( $paid );
					}
					return array(
						'number'           => $paid['number'],
						'confirmation_url' => $paid['confirmation_url'],
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/auth/register',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => ! is_user_logged_in(),
				'callback'            => function ( WP_REST_Request $request ) {
					$user = c4u_register_customer( $request->get_json_params() );
					return is_wp_error( $user ) ? c4u_rest_error_response( $user ) : array( 'name' => $user->display_name );
				},
			)
		);

		register_rest_route(
			$ns,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => ! is_user_logged_in(),
				'callback'            => function ( WP_REST_Request $request ) {
					$user = c4u_sign_in_customer( $request->get_json_params() );
					return is_wp_error( $user ) ? c4u_rest_error_response( $user ) : array( 'name' => $user->display_name );
				},
			)
		);
	}
);
