<?php
/**
 * Saved payment cards for the simulated gateway. A saved card is a token: brand, last four digits,
 * expiry and name on card. The full number and CVV are never stored; the CVV is asked again at payment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const C4U_MAX_CARDS = 5;

function c4u_get_cards( $user_id ) {
	$cards = get_user_meta( $user_id, 'c4u_cards', true );
	return is_array( $cards ) ? array_values( $cards ) : array();
}

function c4u_find_card( $user_id, $card_id ) {
	foreach ( c4u_get_cards( $user_id ) as $card ) {
		if ( hash_equals( $card['id'], (string) $card_id ) ) {
			return $card;
		}
	}
	return null;
}

function c4u_card_view( $card, $is_default = false ) {
	return array(
		'id'      => $card['id'],
		'brand'   => $card['brand'],
		'last4'   => $card['last4'],
		'expiry'  => sprintf( '%02d/%02d', $card['exp_month'], $card['exp_year'] % 100 ),
		'name'    => $card['name'],
		'expired' => c4u_card_expired( $card['exp_month'], $card['exp_year'] ),
		'default' => $is_default,
	);
}

/** The first saved card is the default. */
function c4u_cards_view( $user_id ) {
	$views = array();
	foreach ( c4u_get_cards( $user_id ) as $i => $card ) {
		$views[] = c4u_card_view( $card, 0 === $i );
	}
	return array(
		'cards'   => $views,
		'can_add' => count( $views ) < C4U_MAX_CARDS,
	);
}

/**
 * Runs $change( $cards ) under a per-customer lock and saves the array it returns, or passes on its WP_Error.
 */
function c4u_change_cards( $user_id, callable $change ) {
	$result = c4u_with_db_lock(
		'cards_' . (int) $user_id,
		function () use ( $user_id, $change ) {
			wp_cache_delete( $user_id, 'user_meta' );
			$cards = $change( c4u_get_cards( $user_id ) );
			if ( is_wp_error( $cards ) ) {
				return $cards;
			}
			update_user_meta( $user_id, 'c4u_cards', array_values( $cards ) );
			return c4u_cards_view( $user_id );
		}
	);
	return $result ?? new WP_Error( 'c4u_cards_busy', __( 'Your cards are being updated. Please try again.', 'coffee4u' ), array( 'status' => 409 ) );
}

/** Stores a card parsed by c4u_parse_card(). */
function c4u_store_card( $user_id, $card ) {
	return c4u_change_cards(
		$user_id,
		function ( $cards ) use ( $card ) {
			foreach ( $cards as $saved ) {
				if ( $saved['brand'] === $card['brand'] && $saved['last4'] === $card['last4'] && $saved['exp_month'] === $card['exp_month'] && $saved['exp_year'] === $card['exp_year'] ) {
					return new WP_Error(
						'c4u_card_duplicate',
						__( 'This card is already saved.', 'coffee4u' ),
						array(
							'status' => 400,
							'fields' => array( 'card_number' => __( 'This card is already saved.', 'coffee4u' ) ),
						)
					);
				}
			}
			if ( count( $cards ) >= C4U_MAX_CARDS ) {
				/* translators: %d: maximum number of saved cards. */
				return new WP_Error( 'c4u_card_limit', sprintf( __( 'You can save up to %d cards. Remove one first.', 'coffee4u' ), C4U_MAX_CARDS ), array( 'status' => 400 ) );
			}
			$cards[] = array(
				'id'        => strtolower( wp_generate_password( 16, false ) ),
				'brand'     => $card['brand'],
				'last4'     => $card['last4'],
				'exp_month' => $card['exp_month'],
				'exp_year'  => $card['exp_year'],
				'name'      => $card['name'],
				'declines'  => $card['declines'],
				'created'   => time(),
			);
			return $cards;
		}
	);
}

function c4u_save_card( $user_id, $input ) {
	list( $card, $errors ) = c4u_parse_card( $input );
	if ( $errors ) {
		return new WP_Error(
			'c4u_card_invalid',
			__( 'Please check your card details.', 'coffee4u' ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}
	return c4u_store_card( $user_id, $card );
}

/** Removes a card, or with $make_default moves it to the front. */
function c4u_update_card( $user_id, $card_id, $make_default = false ) {
	return c4u_change_cards(
		$user_id,
		function ( $cards ) use ( $card_id, $make_default ) {
			foreach ( $cards as $i => $card ) {
				if ( hash_equals( $card['id'], (string) $card_id ) ) {
					unset( $cards[ $i ] );
					return $make_default ? array_merge( array( $card ), $cards ) : $cards;
				}
			}
			return new WP_Error( 'c4u_card_not_found', __( 'Card not found.', 'coffee4u' ), array( 'status' => 404 ) );
		}
	);
}

add_action(
	'rest_api_init',
	function () {
		$respond = fn( $result ) => is_wp_error( $result ) ? c4u_rest_error_response( $result ) : $result;
		register_rest_route(
			'coffee4u/v1',
			'/account/cards',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => fn() => c4u_cards_view( get_current_user_id() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => fn( WP_REST_Request $request ) => $respond( c4u_save_card( get_current_user_id(), $request->get_json_params() ) ),
				),
			)
		);
		register_rest_route(
			'coffee4u/v1',
			'/account/cards/(?P<id>[a-z0-9]+)',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => 'is_user_logged_in',
				'callback'            => fn( WP_REST_Request $request ) => $respond( c4u_update_card( get_current_user_id(), $request['id'] ) ),
			)
		);
		register_rest_route(
			'coffee4u/v1',
			'/account/cards/(?P<id>[a-z0-9]+)/default',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'is_user_logged_in',
				'callback'            => fn( WP_REST_Request $request ) => $respond( c4u_update_card( get_current_user_id(), $request['id'], true ) ),
			)
		);
	}
);
