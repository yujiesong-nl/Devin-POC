<?php
/**
 * Customer sign-up and sign-in using core WordPress users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress limits user_login to 60 characters; longer addresses get a unique internal login.
 * Customers always sign in with their e-mail address, so the login name is never shown.
 */
function c4u_login_name_for( $email ) {
	if ( strlen( $email ) <= 60 ) {
		return $email;
	}
	$base  = substr( sanitize_user( strstr( $email, '@', true ), true ), 0, 40 );
	$login = $base;
	while ( '' === $login || username_exists( $login ) ) {
		$login = $base . '-' . strtolower( wp_generate_password( 6, false ) );
	}
	return $login;
}

/**
 * Validation shared by sign-up and profile editing; $user_id is the account allowed to keep the e-mail.
 */
function c4u_customer_field_errors( $name, $phone, $email, $user_id = 0 ) {
	$errors = array();
	if ( strlen( $name ) < 2 ) {
		$errors['name'] = __( 'Enter your full name.', 'coffee4u' );
	}
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
		$errors['phone'] = __( 'Enter a valid phone number.', 'coffee4u' );
	}
	$owner = $email ? ( email_exists( $email ) ?: username_exists( $email ) ) : false;
	if ( ! is_email( $email ) || strlen( $email ) > 100 ) {
		$errors['email'] = __( 'Enter a valid e-mail address.', 'coffee4u' );
	} elseif ( $owner && (int) $owner !== (int) $user_id ) {
		$errors['email'] = $user_id
			? __( 'Another account already uses this e-mail.', 'coffee4u' )
			: __( 'An account with this e-mail already exists. Please sign in.', 'coffee4u' );
	}
	return $errors;
}

function c4u_register_customer( $input ) {
	$name     = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	$phone    = preg_replace( '/[^\d+\-\s()]/', '', (string) ( $input['phone'] ?? '' ) );
	$email    = sanitize_email( (string) ( $input['email'] ?? '' ) );
	$password = (string) ( $input['password'] ?? '' );
	$errors   = c4u_customer_field_errors( $name, $phone, $email );
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
			'user_login'   => c4u_login_name_for( $email ),
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
		if ( 'c4u_login_locked' === $user->get_error_code() ) {
			return new WP_Error( 'c4u_login_locked', $user->get_error_message(), array( 'status' => 429 ) );
		}
		return new WP_Error( 'c4u_login_failed', __( 'The e-mail or password is incorrect.', 'coffee4u' ), array( 'status' => 401 ) );
	}
	return $user;
}

/*
 * Sign-in throttling for the storefront and wp-login.php: too many failed attempts for one
 * account or from one IP address lock further attempts for C4U_LOGIN_WINDOW seconds.
 */
const C4U_LOGIN_WINDOW      = 15 * MINUTE_IN_SECONDS;
const C4U_LOGIN_MAX_ACCOUNT = 5;
const C4U_LOGIN_MAX_IP      = 20;

function c4u_login_throttle_keys( $login ) {
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$login   = strtolower( trim( (string) $login ) );
	$user    = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
	$account = $user ? 'user:' . $user->ID : $login;
	return array(
		'c4u_login_fail_a_' . md5( $account ) => C4U_LOGIN_MAX_ACCOUNT,
		'c4u_login_fail_ip_' . md5( $ip )     => C4U_LOGIN_MAX_IP,
	);
}

function c4u_login_locked( $login ) {
	foreach ( c4u_login_throttle_keys( $login ) as $key => $max ) {
		$record = get_transient( $key );
		if ( is_array( $record ) && $record['count'] >= $max && $record['since'] + C4U_LOGIN_WINDOW > time() ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'authenticate',
	function ( $user, $login ) {
		if ( '' !== (string) $login && c4u_login_locked( $login ) ) {
			return new WP_Error( 'c4u_login_locked', __( 'Too many failed sign-in attempts. Please try again in 15 minutes.', 'coffee4u' ) );
		}
		return $user;
	},
	99,
	2
);

add_action(
	'wp_login_failed',
	function ( $login, $error = null ) {
		if ( $error instanceof WP_Error && 'c4u_login_locked' === $error->get_error_code() ) {
			return;
		}
		foreach ( array_keys( c4u_login_throttle_keys( $login ) ) as $key ) {
			// Locked read-modify-write so concurrent failures are all counted.
			c4u_with_db_lock(
				$key,
				function () use ( $key ) {
					wp_cache_delete( 'notoptions', 'options' );
					wp_cache_delete( '_transient_' . $key, 'options' );
					wp_cache_delete( '_transient_timeout_' . $key, 'options' );
					$record = get_transient( $key );
					if ( ! is_array( $record ) || $record['since'] + C4U_LOGIN_WINDOW <= time() ) {
						$record = array(
							'count' => 0,
							'since' => time(),
						);
					}
					++$record['count'];
					set_transient( $key, $record, $record['since'] + C4U_LOGIN_WINDOW - time() );
				}
			);
		}
	},
	10,
	2
);

add_action(
	'wp_login',
	function ( $login, $user ) {
		$keys = array_keys( c4u_login_throttle_keys( $user->user_login ) );
		delete_transient( $keys[0] );
	},
	10,
	2
);

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return $show && current_user_can( 'edit_posts' );
	}
);
