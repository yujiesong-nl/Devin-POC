<?php
/**
 * Customer sign-up and sign-in using core WordPress users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_register_customer( $input ) {
	$name     = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	$phone    = preg_replace( '/[^\d+\-\s()]/', '', (string) ( $input['phone'] ?? '' ) );
	$email    = sanitize_email( (string) ( $input['email'] ?? '' ) );
	$password = (string) ( $input['password'] ?? '' );
	$errors   = array();

	if ( strlen( $name ) < 2 ) {
		$errors['name'] = __( 'Enter your full name.', 'coffee4u' );
	}
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
		$errors['phone'] = __( 'Enter a valid phone number.', 'coffee4u' );
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = __( 'Enter a valid e-mail address.', 'coffee4u' );
	} elseif ( email_exists( $email ) || username_exists( $email ) ) {
		$errors['email'] = __( 'An account with this e-mail already exists. Please sign in.', 'coffee4u' );
	}
	if ( strlen( $password ) < 8 ) {
		$errors['password'] = __( 'Use at least 8 characters.', 'coffee4u' );
	}
	if ( $errors ) {
		return new WP_Error(
			'c4u_register_invalid',
			__( 'Please check the highlighted fields.', 'coffee4u' ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}

	$parts   = explode( ' ', $name, 2 );
	$user_id = wp_insert_user(
		array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name,
			'first_name'   => $parts[0],
			'last_name'    => $parts[1] ?? '',
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}
	update_user_meta( $user_id, 'c4u_phone', $phone );
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	c4u_merge_guest_cart( $user_id );
	return get_userdata( $user_id );
}

function c4u_sign_in_customer( $input ) {
	$user = wp_signon(
		array(
			'user_login'    => sanitize_text_field( (string) ( $input['email'] ?? '' ) ),
			'user_password' => (string) ( $input['password'] ?? '' ),
			'remember'      => true,
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		return new WP_Error( 'c4u_login_failed', __( 'The e-mail or password is incorrect.', 'coffee4u' ), array( 'status' => 401 ) );
	}
	return $user;
}

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return $show && current_user_can( 'edit_posts' );
	}
);
