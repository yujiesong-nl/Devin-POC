<?php
/**
 * POC Theme functions.
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
		wp_enqueue_style( 'poc-theme', get_theme_file_uri( 'assets/css/theme.css' ), array(), $version );
	}
);
