<?php
/**
 * Store settings: tax rate and service fee.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_setting( $key ) {
	$defaults = array(
		'tax_rate'    => 5,
		'service_fee' => 0.50,
	);
	$options  = wp_parse_args( (array) get_option( 'c4u_settings', array() ), $defaults );
	return (float) $options[ $key ];
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'c4u_settings',
			'c4u_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					return array(
						'tax_rate'    => min( 100, max( 0, (float) ( $value['tax_rate'] ?? 0 ) ) ),
						'service_fee' => max( 0, (float) ( $value['service_fee'] ?? 0 ) ),
					);
				},
			)
		);
		add_settings_section( 'c4u_checkout', __( 'Checkout', 'coffee4u' ), '__return_false', 'c4u_settings' );
		foreach ( array(
			'tax_rate'    => __( 'Tax rate (%)', 'coffee4u' ),
			'service_fee' => __( 'Service fee per order (CAD)', 'coffee4u' ),
		) as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				function () use ( $key ) {
					printf( '<input type="number" step="0.01" min="0" name="c4u_settings[%1$s]" value="%2$s">', esc_attr( $key ), esc_attr( c4u_setting( $key ) ) );
				},
				'c4u_settings',
				'c4u_checkout'
			);
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'coffee4u',
			__( 'Settings', 'coffee4u' ),
			__( 'Settings', 'coffee4u' ),
			'manage_options',
			'c4u-settings',
			function () {
				echo '<div class="wrap"><h1>' . esc_html__( 'Coffee4U Settings', 'coffee4u' ) . '</h1><form method="post" action="options.php">';
				settings_fields( 'c4u_settings' );
				do_settings_sections( 'c4u_settings' );
				submit_button();
				echo '</form></div>';
			},
			99
		);
	}
);
