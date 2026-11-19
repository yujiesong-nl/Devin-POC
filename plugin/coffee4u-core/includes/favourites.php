<?php
/**
 * Favourite location: one open cafe location per customer, kept in user meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Published locations in display order. */
function c4u_get_locations() {
	$posts = get_posts(
		array(
			'post_type'      => 'c4u_location',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);
	return array_values( array_filter( array_map( 'c4u_get_location', $posts ) ) );
}

/** The customer's favourite, or null when unset or the location is no longer published and open. */
function c4u_get_favourite_location( $user_id ) {
	$id       = (int) get_user_meta( $user_id, 'c4u_favourite_location', true );
	$location = $id ? c4u_get_location( $id ) : null;
	return $location && $location['is_open'] ? $location : null;
}

function c4u_favourite_view( $user_id ) {
	$favourite = $user_id ? c4u_get_favourite_location( $user_id ) : null;
	$locations = array();
	foreach ( c4u_get_locations() as $location ) {
		$locations[] = array(
			'id'        => $location['id'],
			'name'      => $location['name'],
			'is_open'   => $location['is_open'],
			'favourite' => $favourite && $favourite['id'] === $location['id'],
		);
	}
	return array(
		'location'  => $favourite ? array(
			'id'       => $favourite['id'],
			'name'     => $favourite['name'],
			'url'      => $favourite['url'],
			'menu_url' => $favourite['menu_url'],
		) : null,
		'locations' => $locations,
	);
}

/** Sets the favourite, or clears it when $location_id is 0. */
function c4u_set_favourite_location( $user_id, $location_id ) {
	$location_id = (int) $location_id;
	if ( ! $location_id ) {
		delete_user_meta( $user_id, 'c4u_favourite_location' );
		return c4u_favourite_view( $user_id );
	}
	$location = c4u_get_location( $location_id );
	if ( ! $location ) {
		return new WP_Error( 'c4u_location_not_found', __( 'Location not found.', 'coffee4u' ), array( 'status' => 404 ) );
	}
	if ( ! $location['is_open'] ) {
		return new WP_Error( 'c4u_location_closed', __( 'This location is opening shortly and cannot be your favourite yet.', 'coffee4u' ), array( 'status' => 400 ) );
	}
	update_user_meta( $user_id, 'c4u_favourite_location', $location_id );
	return c4u_favourite_view( $user_id );
}

add_action(
	'rest_api_init',
	function () {
		$respond = fn( $result ) => is_wp_error( $result ) ? c4u_rest_error_response( $result ) : $result;
		register_rest_route(
			'coffee4u/v1',
			'/account/favourite-location',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => fn() => c4u_favourite_view( get_current_user_id() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'location_id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
					'callback'            => fn( WP_REST_Request $request ) => $respond( c4u_set_favourite_location( get_current_user_id(), $request['location_id'] ) ),
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'location_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					// With location_id, only clear if that location is still the favourite (a stale tab must not erase a newer choice).
					'callback'            => function ( WP_REST_Request $request ) {
						$user_id   = get_current_user_id();
						$favourite = c4u_get_favourite_location( $user_id );
						if ( $request['location_id'] && ( ! $favourite || $favourite['id'] !== (int) $request['location_id'] ) ) {
							return c4u_favourite_view( $user_id );
						}
						return c4u_set_favourite_location( $user_id, 0 );
					},
				),
			)
		);
	}
);
