<?php
/**
 * Plugin Name: Coffee4U Core
 * Description: Menu, cart, promo codes, checkout, simulated payment and customer accounts for the Coffee4U ordering POC. No third-party dependencies.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Coffee4U POC
 * License: GPL-2.0-or-later
 * Text Domain: coffee4u
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'C4U_VERSION', '1.0.0' );
define( 'C4U_FILE', __FILE__ );
define( 'C4U_DIR', plugin_dir_path( __FILE__ ) );
define( 'C4U_URL', plugin_dir_url( __FILE__ ) );

require_once C4U_DIR . 'includes/post-types.php';
require_once C4U_DIR . 'includes/admin.php';
require_once C4U_DIR . 'includes/settings.php';
require_once C4U_DIR . 'includes/cart.php';
require_once C4U_DIR . 'includes/pricing.php';
require_once C4U_DIR . 'includes/orders.php';
require_once C4U_DIR . 'includes/payment.php';
require_once C4U_DIR . 'includes/auth.php';
require_once C4U_DIR . 'includes/rest.php';
require_once C4U_DIR . 'includes/blocks.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once C4U_DIR . 'includes/cli.php';
}

register_activation_hook(
	__FILE__,
	function () {
		c4u_register_content_types();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
