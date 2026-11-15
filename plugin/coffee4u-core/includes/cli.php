<?php
/**
 * WP-CLI: `wp coffee4u seed` creates the demo locations, menu, promo code and pages. Safe to re-run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

WP_CLI::add_command(
	'coffee4u seed',
	function () {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_option(
			'c4u_settings',
			array(
				'tax_rate'    => 5,
				'service_fee' => 0.50,
			)
		);

		$hours     = 'Mon-Fri 8:00am - 7:00pm | Sat-Sun: 9:00am - 5:00pm';
		$locations = array(
			'downtown'   => array( 'Downtown', '101 9 Ave SW, Calgary, AB T2P 1J9', '403-123-4567', 'open', 5 ),
			'kensington' => array( 'Kensington', '1120 Kensington Rd NW, Calgary, AB T2N 3P3', '403-123-4568', 'opening_soon', 0 ),
			'crowfoot'   => array( 'Crowfoot', '600 Crowfoot Crescent NW, Calgary, AB T3G 0B4', '403-123-4569', 'opening_soon', 0 ),
		);
		$order = 0;
		foreach ( $locations as $slug => list( $name, $address, $phone, $status, $rating ) ) {
			$id = c4u_seed_post( 'c4u_location', $slug, array( 'post_title' => $name, 'menu_order' => $order++ ) );
			$meta = array( 'address' => $address, 'phone' => $phone, 'hours' => $hours, 'status' => $status, 'rating' => $rating, 'prep_minutes' => 20 );
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, '_c4u_' . $key, $value );
			}
		}

		$sections = array(
			'most-popular'   => array( 'Most Popular', 1, 0 ),
			'hot-beverages'  => array( 'Hot Beverages', 2, 1 ),
			'cold-beverages' => array( 'Cold Beverages', 3, 1 ),
			'snacks'         => array( 'Snacks', 4, 0 ),
		);
		foreach ( $sections as $slug => list( $name, $position, $beverage ) ) {
			$term = term_exists( $slug, 'c4u_menu_section' );
			$term = $term ? $term : wp_insert_term( $name, 'c4u_menu_section', array( 'slug' => $slug ) );
			update_term_meta( (int) $term['term_id'], 'c4u_order', $position );
			update_term_meta( (int) $term['term_id'], 'c4u_is_beverage', $beverage );
		}

		$products = array(
			array( 'Specialty Tea', 1.50, 'Refreshing tea infused with aromatic edible flowers and light spices.', array( 'most-popular', 'hot-beverages' ) ),
			array( 'Caramel Latte', 4.75, 'Double espresso with steamed milk and house-made caramel, finished with latte art.', array( 'most-popular', 'hot-beverages' ) ),
			array( 'Hot Chocolate', 3.95, 'Rich Belgian chocolate melted into steamed milk and topped with marshmallows.', array( 'most-popular', 'hot-beverages' ) ),
			array( 'Cappuccino', 4.25, 'Espresso with equal parts steamed milk and velvety foam, dusted with cocoa.', array( 'hot-beverages' ) ),
			array( 'Americano', 3.25, 'Two shots of our signature espresso topped with hot water.', array( 'hot-beverages' ) ),
			array( 'Espresso', 2.75, 'A bold double shot pulled from freshly ground single-origin beans.', array( 'hot-beverages' ) ),
			array( 'Vanilla Cream Brew', 5.25, 'Slow-steeped cold brew over ice with sweet vanilla cream.', array( 'cold-beverages' ) ),
			array( 'Cold Coffee', 4.95, 'Chilled coffee blended with milk and chocolate, topped with cookie crumbs.', array( 'cold-beverages' ) ),
			array( 'Chocolate Milkshake', 5.75, 'Thick chocolate milkshake made with real ice cream.', array( 'cold-beverages' ) ),
			array( 'Lemonade', 3.50, 'Freshly squeezed lemons, a hint of mint and sparkling water.', array( 'cold-beverages' ) ),
			array( 'BLT on Brioche', 8.95, 'Crispy bacon, lettuce and tomato with garlic aioli on a toasted brioche bun.', array( 'snacks' ) ),
			array( 'Quinoa Breakfast', 9.50, 'Warm quinoa bowl with roasted vegetables, chickpeas and a lemon-tahini dressing.', array( 'snacks' ) ),
			array( 'Yogurt Parfait', 5.95, 'Greek yogurt layered with berry compote and crunchy granola.', array( 'snacks' ) ),
			array( 'Sausage Wrap', 7.95, 'Grilled sausage, peppers and cheddar in a toasted flour tortilla.', array( 'snacks' ) ),
			array( 'Chicken Sandwich', 9.25, 'Grilled chicken breast, lettuce, tomato and chipotle mayo on a sesame bun.', array( 'snacks' ) ),
		);
		foreach ( $products as $index => list( $name, $price, $description, $terms ) ) {
			$slug = sanitize_title( $name );
			$id   = c4u_seed_post( 'c4u_product', $slug, array( 'post_title' => $name, 'post_excerpt' => $description, 'menu_order' => $index ) );
			update_post_meta( $id, '_c4u_price', number_format( $price, 2, '.', '' ) );
			wp_set_object_terms( $id, $terms, 'c4u_menu_section' );
			$image = C4U_DIR . 'seed/images/' . $slug . '.jpg';
			if ( ! has_post_thumbnail( $id ) && file_exists( $image ) ) {
				$tmp = wp_tempnam( $slug . '.jpg' );
				copy( $image, $tmp );
				$attachment = media_handle_sideload( array( 'name' => $slug . '.jpg', 'tmp_name' => $tmp ), $id, $name );
				if ( ! is_wp_error( $attachment ) ) {
					set_post_thumbnail( $id, $attachment );
				}
			}
		}

		$coupon = c4u_seed_post( 'c4u_coupon', 'newsignup', array( 'post_title' => 'NEWSIGNUP' ) );
		update_post_meta( $coupon, '_c4u_type', 'free_beverage' );
		update_post_meta( $coupon, '_c4u_amount', 0 );
		update_post_meta( $coupon, '_c4u_first_order_only', '1' );

		$pages = array(
			'checkout'        => array( 'Checkout', 'checkout' ),
			'payment'         => array( 'Secure Payment', 'payment' ),
			'order-confirmed' => array( 'Order Confirmed', 'order-confirmation' ),
			'my-orders'       => array( 'My Orders', 'my-orders' ),
		);
		foreach ( $pages as $slug => list( $title, $block ) ) {
			$id = c4u_seed_post(
				'page',
				$slug,
				array(
					'post_title'   => $title,
					'post_content' => '<!-- wp:coffee4u/' . $block . ' /-->',
				)
			);
			update_post_meta( $id, '_wp_page_template', 'flow' );
		}

		flush_rewrite_rules();
		WP_CLI::success( 'Coffee4U demo content is ready.' );
	}
);

function c4u_seed_post( $type, $slug, $fields ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	$postarr  = array_merge(
		array(
			'post_type'   => $type,
			'post_name'   => $slug,
			'post_status' => 'publish',
		),
		$fields
	);
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		return wp_update_post( $postarr );
	}
	return wp_insert_post( $postarr );
}
