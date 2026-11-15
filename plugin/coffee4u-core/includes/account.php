<?php
/**
 * Customer profile: view and edit name, phone, e-mail and password.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_profile_view( $user ) {
	return array(
		'name'  => $user->display_name,
		'phone' => (string) get_user_meta( $user->ID, 'c4u_phone', true ),
		'email' => $user->user_email,
	);
}

/**
 * Updates the signed-in customer's details. Changing the e-mail or password needs the current password.
 */
function c4u_update_profile( $user_id, $input ) {
	$user     = get_userdata( $user_id );
	$name     = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	$phone    = preg_replace( '/[^\d+\-\s()]/', '', (string) ( $input['phone'] ?? '' ) );
	$email    = sanitize_email( (string) ( $input['email'] ?? '' ) );
	$password = (string) ( $input['password'] ?? '' );
	$current  = (string) ( $input['current_password'] ?? '' );

	$errors = c4u_customer_field_errors( $name, $phone, $email, $user_id );
	if ( '' !== $password && strlen( $password ) < 8 ) {
		$errors['password'] = __( 'Use at least 8 characters.', 'coffee4u' );
	}
	$email_changed = strtolower( $email ) !== strtolower( $user->user_email );
	$sensitive     = $email_changed || '' !== $password;
	if ( $sensitive && '' === $current ) {
		$errors['current_password'] = __( 'Enter your current password to change your e-mail or password.', 'coffee4u' );
	}
	if ( $errors ) {
		return new WP_Error(
			'c4u_profile_invalid',
			__( 'Please check the highlighted fields.', 'coffee4u' ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}

	if ( $sensitive ) {
		if ( c4u_login_locked( $user->user_login ) ) {
			return new WP_Error( 'c4u_login_locked', __( 'Too many failed sign-in attempts. Please try again in 15 minutes.', 'coffee4u' ), array( 'status' => 429 ) );
		}
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			// Counts toward the sign-in lockout, so the form cannot be used to guess the password.
			do_action( 'wp_login_failed', $user->user_login, new WP_Error( 'incorrect_password' ) );
			return new WP_Error(
				'c4u_profile_password',
				__( 'Your current password is incorrect.', 'coffee4u' ),
				array(
					'status' => 403,
					'fields' => array( 'current_password' => __( 'Your current password is incorrect.', 'coffee4u' ) ),
				)
			);
		}
	}

	$token = wp_get_session_token();
	$parts = explode( ' ', $name, 2 );
	$data  = array(
		'ID'           => $user->ID,
		'display_name' => $name,
		'first_name'   => $parts[0],
		'last_name'    => $parts[1] ?? '',
		'user_email'   => $email,
	);
	if ( '' !== $password ) {
		$data['user_pass'] = $password;
	}
	$result = wp_update_user( $data );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	update_user_meta( $user->ID, 'c4u_phone', $phone );

	if ( $email_changed && strtolower( $user->user_login ) === strtolower( $user->user_email ) ) {
		// The old address would otherwise still sign in as the login name.
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'user_login' => c4u_login_name_for( $email ) ), array( 'ID' => $user->ID ) );
		clean_user_cache( $user );
	}
	if ( $sensitive ) {
		// Sign out every other browser and re-issue this browser's cookie for the new login and password.
		WP_Session_Tokens::get_instance( $user->ID )->destroy_others( $token );
		wp_set_auth_cookie( $user->ID, true, '', $token );
	}
	return c4u_profile_view( get_userdata( $user->ID ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'coffee4u/v1',
			'/account/profile',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => fn() => c4u_profile_view( wp_get_current_user() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => function ( WP_REST_Request $request ) {
						$profile = c4u_update_profile( get_current_user_id(), $request->get_json_params() );
						return is_wp_error( $profile ) ? c4u_rest_error_response( $profile ) : $profile;
					},
				),
			)
		);
	}
);
