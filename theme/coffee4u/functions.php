<?php
/**
 * Coffee4U theme setup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/theme.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'coffee4u', get_theme_file_uri( 'assets/css/theme.css' ), array(), $version );
		wp_enqueue_script( 'coffee4u-carousel', get_theme_file_uri( 'assets/js/carousel.js' ), array(), $version, array( 'strategy' => 'defer' ) );
	}
);

add_action(
	'init',
	function () {
		register_block_pattern_category( 'coffee4u', array( 'label' => __( 'Coffee4U', 'coffee4u' ) ) );
	}
);
