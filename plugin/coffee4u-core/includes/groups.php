<?php
/**
 * Group ordering: an organizer shares an invite link, friends add their picks without signing in,
 * and the organizer merges every response into one cart and pays through the normal checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const C4U_GROUP_MIN_SIZE    = 2;
const C4U_GROUP_MAX_SIZE    = 50;
const C4U_GROUP_MIN_MINUTES = 5;
const C4U_GROUP_MAX_MINUTES = 120;

function c4u_get_group( $group_id ) {
	$post = get_post( $group_id );
	if ( ! $post || 'c4u_group' !== $post->post_type ) {
		return null;
	}
	$token = (string) get_post_meta( $post->ID, '_c4u_token', true );
	return array(
		'id'         => $post->ID,
		'user_id'    => (int) get_post_meta( $post->ID, '_c4u_user_id', true ),
		'location'   => c4u_get_location( (int) get_post_meta( $post->ID, '_c4u_location_id', true ) ),
		'size'       => (int) get_post_meta( $post->ID, '_c4u_size', true ),
		'limit'      => (int) get_post_meta( $post->ID, '_c4u_limit', true ),
		'deadline'   => (int) get_post_meta( $post->ID, '_c4u_deadline', true ),
		'status'     => (string) get_post_meta( $post->ID, '_c4u_status', true ),
		'responses'  => array_values( (array) get_post_meta( $post->ID, '_c4u_responses', true ) ),
		'order_id'   => (int) get_post_meta( $post->ID, '_c4u_order_id', true ),
		'token'      => $token,
		'created'    => get_post_time( 'U', true, $post ),
		'join_url'   => c4u_page_url( 'group-join', array( 't' => $token ) ),
		'status_url' => c4u_page_url( 'group-order', array( 'group' => $post->ID ) ),
	);
}

/**
 * Returns the group when the signed-in customer organised it.
 */
function c4u_get_owned_group( $group_id ) {
	$group = c4u_get_group( $group_id );
	if ( ! $group || ! is_user_logged_in() || $group['user_id'] !== get_current_user_id() ) {
		return null;
	}
	return $group;
}

function c4u_find_group_by_token( $token ) {
	$token = sanitize_key( (string) $token );
	if ( 32 !== strlen( $token ) ) {
		return null;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'c4u_group',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_c4u_token',
			'meta_value'     => $token,
		)
	);
	return $ids ? c4u_get_group( $ids[0] ) : null;
}

/**
 * open: collecting responses; full / expired: collection stopped; confirmed: merged into the
 * organizer's cart; ordered: the order was paid.
 */
function c4u_group_state( $group ) {
	if ( 'open' !== $group['status'] ) {
		return $group['status'];
	}
	if ( time() >= $group['deadline'] ) {
		return 'expired';
	}
	return count( $group['responses'] ) >= $group['size'] ? 'full' : 'open';
}

function c4u_group_lines_summary( $lines ) {
	return implode( ', ', array_map( fn( $line ) => $line['quantity'] . ' x ' . $line['name'], $lines ) );
}

/**
 * Runs $callback( $group ) with the group freshly loaded under a per-group lock.
 */
function c4u_with_group_lock( $group_id, callable $callback ) {
	$result = c4u_with_db_lock(
		'group_' . (int) $group_id,
		function () use ( $group_id, $callback ) {
			wp_cache_delete( $group_id, 'post_meta' );
			return $callback( c4u_get_group( $group_id ) );
		}
	);
	return $result ?? new WP_Error( 'c4u_group_busy', __( 'The group order is busy. Please try again.', 'coffee4u' ), array( 'status' => 409 ) );
}

function c4u_create_group( $user_id, $input ) {
	$location = c4u_get_location( (int) ( $input['location_id'] ?? 0 ) );
	if ( ! $location || ! $location['is_open'] ) {
		return new WP_Error( 'c4u_location_closed', __( 'This location is not accepting orders yet.', 'coffee4u' ), array( 'status' => 400 ) );
	}
	$size    = (int) ( $input['size'] ?? 0 );
	$minutes = (int) ( $input['minutes'] ?? 0 );
	$limit   = empty( $input['has_limit'] ) ? 0 : c4u_price_to_cents( (float) ( $input['limit'] ?? 0 ) );
	$errors  = array();
	if ( $size < C4U_GROUP_MIN_SIZE || $size > C4U_GROUP_MAX_SIZE ) {
		/* translators: 1: minimum, 2: maximum */
		$errors['size'] = sprintf( __( 'Choose a group size from %1$d to %2$d.', 'coffee4u' ), C4U_GROUP_MIN_SIZE, C4U_GROUP_MAX_SIZE );
	}
	if ( $minutes < C4U_GROUP_MIN_MINUTES || $minutes > C4U_GROUP_MAX_MINUTES ) {
		/* translators: 1: minimum, 2: maximum */
		$errors['minutes'] = sprintf( __( 'Choose a timer from %1$d to %2$d minutes.', 'coffee4u' ), C4U_GROUP_MIN_MINUTES, C4U_GROUP_MAX_MINUTES );
	}
	if ( ! empty( $input['has_limit'] ) && ( $limit < 100 || $limit > 50000 ) ) {
		$errors['limit'] = __( 'Enter an amount limit from $1.00 to $500.00.', 'coffee4u' );
	}
	if ( $errors ) {
		return new WP_Error(
			'c4u_group_invalid',
			__( 'Please check the group order settings.', 'coffee4u' ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}

	$group_id = wp_insert_post(
		array(
			'post_type'   => 'c4u_group',
			'post_status' => 'publish',
			/* translators: %s: location name */
			'post_title'  => sprintf( __( 'Group order at %s', 'coffee4u' ), $location['name'] ),
			'post_author' => $user_id,
		),
		true
	);
	if ( is_wp_error( $group_id ) ) {
		return $group_id;
	}
	update_post_meta( $group_id, '_c4u_user_id', $user_id );
	update_post_meta( $group_id, '_c4u_location_id', $location['id'] );
	update_post_meta( $group_id, '_c4u_size', $size );
	update_post_meta( $group_id, '_c4u_limit', $limit );
	update_post_meta( $group_id, '_c4u_deadline', time() + $minutes * MINUTE_IN_SECONDS );
	update_post_meta( $group_id, '_c4u_status', 'open' );
	update_post_meta( $group_id, '_c4u_responses', array() );
	update_post_meta( $group_id, '_c4u_token', strtolower( wp_generate_password( 32, false ) ) );
	return c4u_get_group( $group_id );
}

/**
 * Adds a participant's picks, or replaces them when $input['key'] matches their earlier response.
 */
function c4u_submit_group_response( $group_id, $input ) {
	return c4u_with_group_lock(
		$group_id,
		function ( $group ) use ( $input ) {
			$key   = sanitize_key( (string) ( $input['key'] ?? '' ) );
			$index = null;
			foreach ( $group['responses'] as $i => $response ) {
				if ( '' !== $key && hash_equals( $response['key'], $key ) ) {
					$index = $i;
				}
			}
			$state = c4u_group_state( $group );
			// Someone who already responded may still change their picks when the group is full.
			if ( 'open' !== $state && ! ( 'full' === $state && null !== $index ) ) {
				return new WP_Error( 'c4u_group_closed', __( 'This group order is no longer taking orders.', 'coffee4u' ), array( 'status' => 409 ) );
			}

			$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );
			$cart = c4u_normalize_cart(
				array(
					'location_id' => $group['location']['id'],
					'items'       => (array) ( $input['items'] ?? array() ),
				)
			);
			if ( '' === $name || mb_strlen( $name ) > 40 ) {
				return new WP_Error(
					'c4u_group_invalid',
					__( 'Please check your order.', 'coffee4u' ),
					array(
						'status' => 400,
						'fields' => array( 'name' => __( 'Enter your name (up to 40 characters).', 'coffee4u' ) ),
					)
				);
			}
			if ( ! $cart['items'] ) {
				return new WP_Error( 'c4u_group_empty', __( 'Add at least one item to your order.', 'coffee4u' ), array( 'status' => 400 ) );
			}
			$totals = c4u_calculate_totals( $cart, 0 );
			if ( $group['limit'] && $totals['subtotal'] > $group['limit'] ) {
				return new WP_Error(
					'c4u_group_limit',
					/* translators: 1: limit, 2: order amount */
					sprintf( __( 'The limit is %1$s per person. Your order comes to %2$s.', 'coffee4u' ), c4u_format_money( $group['limit'] ), c4u_format_money( $totals['subtotal'] ) ),
					array( 'status' => 400 )
				);
			}

			$response = array(
				'id'        => null === $index ? wp_generate_password( 12, false ) : $group['responses'][ $index ]['id'],
				'key'       => null === $index ? strtolower( wp_generate_password( 32, false ) ) : $group['responses'][ $index ]['key'],
				'name'      => $name,
				'items'     => $cart['items'],
				'lines'     => array_map( fn( $line ) => wp_array_slice_assoc( $line, array( 'product_id', 'name', 'quantity', 'total' ) ), $totals['lines'] ),
				'subtotal'  => $totals['subtotal'],
				'submitted' => time(),
			);
			$responses = $group['responses'];
			if ( null === $index ) {
				$responses[] = $response;
			} else {
				$responses[ $index ] = $response;
			}
			update_post_meta( $group['id'], '_c4u_responses', $responses );
			return $response;
		}
	);
}

function c4u_remove_group_response( $group_id, $response_id ) {
	return c4u_with_group_lock(
		$group_id,
		function ( $group ) use ( $response_id ) {
			if ( 'ordered' === $group['status'] ) {
				return new WP_Error( 'c4u_group_ordered', __( 'This group order has already been paid.', 'coffee4u' ), array( 'status' => 409 ) );
			}
			$responses = array_values( array_filter( $group['responses'], fn( $response ) => $response['id'] !== $response_id ) );
			update_post_meta( $group['id'], '_c4u_responses', $responses );
			return c4u_get_group( $group['id'] );
		}
	);
}

function c4u_extend_group( $group_id, $minutes ) {
	return c4u_with_group_lock(
		$group_id,
		function ( $group ) use ( $minutes ) {
			if ( ! in_array( $group['status'], array( 'open', 'confirmed' ), true ) ) {
				return new WP_Error( 'c4u_group_ordered', __( 'This group order has already been paid.', 'coffee4u' ), array( 'status' => 409 ) );
			}
			update_post_meta( $group['id'], '_c4u_deadline', max( $group['deadline'], time() ) + $minutes * MINUTE_IN_SECONDS );
			// Extending the timer re-opens a group that was already merged into the cart.
			update_post_meta( $group['id'], '_c4u_status', 'open' );
			return c4u_get_group( $group['id'] );
		}
	);
}

/**
 * Stops collecting and puts every response into the organizer's cart for checkout.
 */
function c4u_confirm_group( $group_id, $replace_cart ) {
	return c4u_with_group_lock(
		$group_id,
		function ( $group ) use ( $replace_cart ) {
			if ( 'ordered' === $group['status'] ) {
				return new WP_Error( 'c4u_group_ordered', __( 'This group order has already been paid.', 'coffee4u' ), array( 'status' => 409 ) );
			}
			if ( ! $group['responses'] ) {
				return new WP_Error( 'c4u_group_empty', __( 'Nobody has added an order yet.', 'coffee4u' ), array( 'status' => 400 ) );
			}
			if ( ! $group['location'] || ! $group['location']['is_open'] ) {
				return new WP_Error( 'c4u_location_closed', __( 'This location is not accepting orders yet.', 'coffee4u' ), array( 'status' => 400 ) );
			}
			$current = c4u_get_cart( $group['user_id'] );
			if ( $current['items'] && $current['group_id'] !== $group['id'] && ! $replace_cart ) {
				return new WP_Error( 'c4u_cart_not_empty', __( 'Your cart already has items. Replace them with the group order?', 'coffee4u' ), array( 'status' => 409 ) );
			}
			$cart = array_merge(
				c4u_empty_cart(),
				array(
					'location_id' => $group['location']['id'],
					'group_id'    => $group['id'],
				)
			);
			foreach ( $group['responses'] as $response ) {
				foreach ( $response['items'] as $product_id => $quantity ) {
					$cart['items'][ $product_id ] = ( $cart['items'][ $product_id ] ?? 0 ) + $quantity;
				}
			}
			c4u_save_cart( $cart, $group['user_id'] );
			update_post_meta( $group['id'], '_c4u_status', 'confirmed' );
			return c4u_get_group( $group['id'] );
		}
	);
}

/**
 * What the group's organizer sees on the status page.
 */
function c4u_group_owner_view( $group ) {
	$responses = array_map(
		fn( $response ) => array(
			'id'       => $response['id'],
			'name'     => $response['name'],
			'summary'  => c4u_group_lines_summary( $response['lines'] ),
			'subtotal' => c4u_format_money( $response['subtotal'] ),
		),
		$group['responses']
	);
	return array(
		'id'           => $group['id'],
		'state'        => c4u_group_state( $group ),
		'seconds_left' => max( 0, $group['deadline'] - time() ),
		'size'         => $group['size'],
		'count'        => count( $group['responses'] ),
		'responses'    => $responses,
		'total'        => c4u_format_money( array_sum( wp_list_pluck( $group['responses'], 'subtotal' ) ) ),
		'limit'        => $group['limit'] ? c4u_format_money( $group['limit'] ) : null,
		'join_url'     => $group['join_url'],
		'status_url'   => $group['status_url'],
		'checkout_url' => c4u_page_url( 'checkout' ),
	);
}

/**
 * What an invited participant sees; their own picks are included only with their response key.
 */
function c4u_group_public_view( $group, $key = '' ) {
	$mine = null;
	foreach ( $group['responses'] as $response ) {
		if ( '' !== $key && hash_equals( $response['key'], $key ) ) {
			$mine = array(
				'name'  => $response['name'],
				'items' => (object) $response['items'],
			);
		}
	}
	$organizer = get_userdata( $group['user_id'] );
	return array(
		'state'        => c4u_group_state( $group ),
		'seconds_left' => max( 0, $group['deadline'] - time() ),
		'spots_left'   => max( 0, $group['size'] - count( $group['responses'] ) ),
		'limit'        => $group['limit'],
		'organizer'    => $organizer ? strtok( $organizer->display_name, ' ' ) : '',
		'mine'         => $mine,
	);
}

function c4u_get_user_groups( $user_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'c4u_group',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_c4u_user_id',
					'value' => $user_id,
				),
				array(
					'key'     => '_c4u_status',
					'value'   => array( 'open', 'confirmed' ),
					'compare' => 'IN',
				),
			),
		)
	);
	return array_filter( array_map( 'c4u_get_group', $ids ) );
}

/**
 * Snapshot of who ordered what, saved on an order created from a group cart.
 */
function c4u_group_order_snapshot( $cart, $user_id ) {
	$group = $cart['group_id'] ? c4u_get_group( $cart['group_id'] ) : null;
	if ( ! $group || $group['user_id'] !== $user_id ) {
		return null;
	}
	return array(
		'id'        => $group['id'],
		'size'      => $group['size'],
		'responses' => array_map( fn( $response ) => wp_array_slice_assoc( $response, array( 'name', 'lines', 'subtotal' ) ), $group['responses'] ),
	);
}

add_action(
	'c4u_order_paid',
	function ( $order_id ) {
		$order = c4u_get_order( $order_id );
		if ( $order && $order['group'] ) {
			update_post_meta( $order['group']['id'], '_c4u_status', 'ordered' );
			update_post_meta( $order['group']['id'], '_c4u_order_id', $order_id );
		}
	}
);

add_action(
	'rest_api_init',
	function () {
		$ns     = 'coffee4u/v1';
		$owned  = function ( WP_REST_Request $request ) {
			return (bool) c4u_get_owned_group( (int) $request['id'] );
		};
		$result = function ( $group ) {
			return is_wp_error( $group ) ? c4u_rest_error_response( $group ) : c4u_group_owner_view( $group );
		};

		register_rest_route(
			$ns,
			'/groups',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'is_user_logged_in',
				'callback'            => fn( WP_REST_Request $request ) => $result( c4u_create_group( get_current_user_id(), $request->get_json_params() ) ),
			)
		);

		register_rest_route(
			$ns,
			'/groups/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $owned,
				'callback'            => fn( WP_REST_Request $request ) => $result( c4u_get_group( (int) $request['id'] ) ),
			)
		);

		register_rest_route(
			$ns,
			'/groups/(?P<id>\d+)/extend',
			array(
				'methods'             => 'POST',
				'permission_callback' => $owned,
				'args'                => array(
					'minutes' => array(
						'type'    => 'integer',
						'default' => 5,
						'minimum' => 1,
						'maximum' => 60,
					),
				),
				'callback'            => fn( WP_REST_Request $request ) => $result( c4u_extend_group( (int) $request['id'], $request['minutes'] ) ),
			)
		);

		register_rest_route(
			$ns,
			'/groups/(?P<id>\d+)/responses/(?P<response>[A-Za-z0-9]+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => $owned,
				'callback'            => fn( WP_REST_Request $request ) => $result( c4u_remove_group_response( (int) $request['id'], $request['response'] ) ),
			)
		);

		register_rest_route(
			$ns,
			'/groups/(?P<id>\d+)/confirm',
			array(
				'methods'             => 'POST',
				'permission_callback' => $owned,
				'args'                => array(
					'replace' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => fn( WP_REST_Request $request ) => $result( c4u_confirm_group( (int) $request['id'], $request['replace'] ) ),
			)
		);

		register_rest_route(
			$ns,
			'/group-join/(?P<token>[a-z0-9]{32})',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => function ( WP_REST_Request $request ) {
						$group = c4u_find_group_by_token( $request['token'] );
						if ( ! $group ) {
							return c4u_rest_error_response( new WP_Error( 'c4u_not_found', __( 'This group order link is not valid.', 'coffee4u' ), array( 'status' => 404 ) ) );
						}
						return c4u_group_public_view( $group, sanitize_key( (string) $request['key'] ) );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => function ( WP_REST_Request $request ) {
						$group = c4u_find_group_by_token( $request['token'] );
						if ( ! $group ) {
							return c4u_rest_error_response( new WP_Error( 'c4u_not_found', __( 'This group order link is not valid.', 'coffee4u' ), array( 'status' => 404 ) ) );
						}
						$response = c4u_submit_group_response( $group['id'], (array) $request->get_json_params() );
						if ( is_wp_error( $response ) ) {
							return c4u_rest_error_response( $response );
						}
						return array( 'key' => $response['key'] ) + c4u_group_public_view( c4u_get_group( $group['id'] ), $response['key'] );
					},
				),
			)
		);
	}
);
