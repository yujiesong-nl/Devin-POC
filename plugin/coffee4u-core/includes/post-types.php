<?php
/**
 * Content model: locations, menu items, menu sections, promo codes and orders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'c4u_register_content_types' );

function c4u_register_content_types() {
	register_post_type(
		'c4u_location',
		array(
			'labels'       => array(
				'name'          => __( 'Locations', 'coffee4u' ),
				'singular_name' => __( 'Location', 'coffee4u' ),
				'add_new_item'  => __( 'Add New Location', 'coffee4u' ),
				'edit_item'     => __( 'Edit Location', 'coffee4u' ),
			),
			'public'       => true,
			'show_in_menu' => 'coffee4u',
			'show_in_rest' => true,
			'has_archive'  => false,
			'menu_icon'    => 'dashicons-location',
			'supports'     => array( 'title', 'page-attributes' ),
			'rewrite'      => array(
				'slug'    => 'locations',
				'ep_mask' => EP_PERMALINK,
			),
		)
	);

	register_post_type(
		'c4u_product',
		array(
			'labels'       => array(
				'name'          => __( 'Menu Items', 'coffee4u' ),
				'singular_name' => __( 'Menu Item', 'coffee4u' ),
				'add_new_item'  => __( 'Add New Menu Item', 'coffee4u' ),
				'edit_item'     => __( 'Edit Menu Item', 'coffee4u' ),
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'coffee4u',
			'show_in_rest'       => true,
			'supports'           => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
		)
	);

	register_taxonomy(
		'c4u_menu_section',
		'c4u_product',
		array(
			'labels'            => array(
				'name'          => __( 'Menu Sections', 'coffee4u' ),
				'singular_name' => __( 'Menu Section', 'coffee4u' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
		)
	);

	register_post_type(
		'c4u_coupon',
		array(
			'labels'       => array(
				'name'          => __( 'Promo Codes', 'coffee4u' ),
				'singular_name' => __( 'Promo Code', 'coffee4u' ),
				'add_new_item'  => __( 'Add New Promo Code', 'coffee4u' ),
				'edit_item'     => __( 'Edit Promo Code', 'coffee4u' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'coffee4u',
			'supports'     => array( 'title' ),
		)
	);

	register_post_type(
		'c4u_order',
		array(
			'labels'       => array(
				'name'          => __( 'Orders', 'coffee4u' ),
				'singular_name' => __( 'Order', 'coffee4u' ),
				'edit_item'     => __( 'Order Details', 'coffee4u' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'coffee4u',
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);

	register_term_meta(
		'c4u_menu_section',
		'c4u_order',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => true,
		)
	);
	register_term_meta(
		'c4u_menu_section',
		'c4u_is_beverage',
		array(
			'type'         => 'boolean',
			'single'       => true,
			'show_in_rest' => true,
		)
	);

	add_rewrite_endpoint( 'menu', EP_PERMALINK );
}

/**
 * Field definitions shared by the admin meta boxes and the REST layer.
 */
function c4u_location_fields() {
	return array(
		'address'      => __( 'Address', 'coffee4u' ),
		'phone'        => __( 'Phone', 'coffee4u' ),
		'hours'        => __( 'Opening hours', 'coffee4u' ),
		'rating'       => __( 'Rating (0-5)', 'coffee4u' ),
		'prep_minutes' => __( 'Pick-up preparation time (minutes)', 'coffee4u' ),
		'status'       => __( 'Status', 'coffee4u' ),
	);
}

function c4u_get_location( $location ) {
	$post = get_post( $location );
	if ( ! $post || 'c4u_location' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}
	$data = array(
		'id'   => $post->ID,
		'name' => get_the_title( $post ),
		'slug' => $post->post_name,
		'url'  => get_permalink( $post ),
	);
	foreach ( array_keys( c4u_location_fields() ) as $key ) {
		$data[ $key ] = (string) get_post_meta( $post->ID, '_c4u_' . $key, true );
	}
	$data['rating']       = (int) $data['rating'];
	$data['prep_minutes'] = max( 1, (int) ( $data['prep_minutes'] ? $data['prep_minutes'] : 20 ) );
	$data['is_open']      = 'open' === $data['status'];
	$data['menu_url']     = trailingslashit( $data['url'] ) . 'menu/';
	return $data;
}

function c4u_get_product( $product ) {
	$post = get_post( $product );
	if ( ! $post || 'c4u_product' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}
	$sections = wp_get_post_terms( $post->ID, 'c4u_menu_section', array( 'fields' => 'all' ) );
	$beverage = false;
	foreach ( (array) $sections as $term ) {
		if ( get_term_meta( $term->term_id, 'c4u_is_beverage', true ) ) {
			$beverage = true;
		}
	}
	return array(
		'id'          => $post->ID,
		'name'        => get_the_title( $post ),
		'description' => $post->post_excerpt,
		'price'       => c4u_price_to_cents( get_post_meta( $post->ID, '_c4u_price', true ) ),
		'image'       => (string) get_the_post_thumbnail_url( $post, 'medium_large' ),
		'is_beverage' => $beverage,
	);
}

/**
 * Menu sections in display order, each with its published menu items.
 */
function c4u_get_menu() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'c4u_menu_section',
			'hide_empty' => true,
			'meta_key'   => 'c4u_order',
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		)
	);
	$menu = array();
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$ids   = get_posts(
			array(
				'post_type'      => 'c4u_product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => array(
					array(
						'taxonomy' => 'c4u_menu_section',
						'terms'    => $term->term_id,
					),
				),
			)
		);
		$menu[] = array(
			'name'  => $term->name,
			'slug'  => $term->slug,
			'items' => array_values( array_filter( array_map( 'c4u_get_product', $ids ) ) ),
		);
	}
	return $menu;
}

function c4u_price_to_cents( $value ) {
	return (int) round( (float) $value * 100 );
}

function c4u_format_money( $cents ) {
	$prefix = $cents < 0 ? '-' : '';
	return $prefix . '$' . number_format( abs( $cents ) / 100, 2 );
}
