<?php
/**
 * Admin screens: Coffee4U menu, meta boxes, order list columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( __( 'Coffee4U', 'coffee4u' ), __( 'Coffee4U', 'coffee4u' ), 'edit_posts', 'coffee4u', '', 'dashicons-coffee', 26 );
		add_submenu_page( 'coffee4u', __( 'Menu Sections', 'coffee4u' ), __( 'Menu Sections', 'coffee4u' ), 'manage_categories', 'edit-tags.php?taxonomy=c4u_menu_section&post_type=c4u_product' );
	}
);

add_filter(
	'parent_file',
	function ( $parent ) {
		$screen = get_current_screen();
		return ( $screen && 'c4u_menu_section' === $screen->taxonomy ) ? 'coffee4u' : $parent;
	}
);

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'c4u_location_details', __( 'Location Details', 'coffee4u' ), 'c4u_location_meta_box', 'c4u_location', 'normal', 'high' );
		add_meta_box( 'c4u_product_details', __( 'Pricing', 'coffee4u' ), 'c4u_product_meta_box', 'c4u_product', 'side', 'high' );
		add_meta_box( 'c4u_coupon_details', __( 'Promo Code Rules', 'coffee4u' ), 'c4u_coupon_meta_box', 'c4u_coupon', 'normal', 'high' );
		add_meta_box( 'c4u_order_details', __( 'Order', 'coffee4u' ), 'c4u_order_meta_box', 'c4u_order', 'normal', 'high' );
		remove_meta_box( 'submitdiv', 'c4u_order', 'side' );
	}
);

function c4u_location_meta_box( $post ) {
	wp_nonce_field( 'c4u_save_meta', 'c4u_meta_nonce' );
	echo '<table class="form-table">';
	foreach ( c4u_location_fields() as $key => $label ) {
		$value = get_post_meta( $post->ID, '_c4u_' . $key, true );
		echo '<tr><th><label for="c4u_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'status' === $key ) {
			echo '<select id="c4u_status" name="c4u[status]">';
			foreach ( array(
				'open'         => __( 'Open', 'coffee4u' ),
				'opening_soon' => __( 'Opening Shortly', 'coffee4u' ),
			) as $option => $text ) {
				echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		} else {
			$type = in_array( $key, array( 'rating', 'prep_minutes' ), true ) ? 'number' : 'text';
			echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="c4u_' . esc_attr( $key ) . '" name="c4u[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

function c4u_product_meta_box( $post ) {
	wp_nonce_field( 'c4u_save_meta', 'c4u_meta_nonce' );
	$price = get_post_meta( $post->ID, '_c4u_price', true );
	echo '<p><label for="c4u_price">' . esc_html__( 'Price (CAD)', 'coffee4u' ) . '</label><br>';
	echo '<input type="number" min="0" step="0.01" id="c4u_price" name="c4u[price]" value="' . esc_attr( $price ) . '"></p>';
	echo '<p class="description">' . esc_html__( 'The excerpt is used as the menu description.', 'coffee4u' ) . '</p>';
}

function c4u_coupon_meta_box( $post ) {
	wp_nonce_field( 'c4u_save_meta', 'c4u_meta_nonce' );
	$type = get_post_meta( $post->ID, '_c4u_type', true );
	echo '<p class="description">' . esc_html__( 'The title is the code customers enter (case-insensitive).', 'coffee4u' ) . '</p><table class="form-table">';
	echo '<tr><th>' . esc_html__( 'Discount type', 'coffee4u' ) . '</th><td><select name="c4u[type]">';
	foreach ( c4u_coupon_types() as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>' . esc_html__( 'Amount', 'coffee4u' ) . '</th><td><input type="number" step="0.01" min="0" name="c4u[amount]" value="' . esc_attr( get_post_meta( $post->ID, '_c4u_amount', true ) ) . '"> <span class="description">' . esc_html__( 'Percent or CAD. Ignored for "free beverage".', 'coffee4u' ) . '</span></td></tr>';
	echo '<tr><th>' . esc_html__( 'Restrictions', 'coffee4u' ) . '</th><td><label><input type="checkbox" name="c4u[first_order_only]" value="1"' . checked( get_post_meta( $post->ID, '_c4u_first_order_only', true ), '1', false ) . '> ' . esc_html__( 'First order of a registered customer only', 'coffee4u' ) . '</label></td></tr>';
	echo '</table>';
}

function c4u_order_meta_box( $post ) {
	$order = c4u_get_order( $post->ID );
	if ( ! $order ) {
		return;
	}
	$user = get_userdata( $order['user_id'] );
	echo '<p><strong>' . esc_html__( 'Status:', 'coffee4u' ) . '</strong> ' . esc_html( c4u_order_status_label( $order['status'] ) ) . '<br>';
	echo '<strong>' . esc_html__( 'Location:', 'coffee4u' ) . '</strong> ' . esc_html( $order['location']['name'] ?? '' ) . '<br>';
	echo '<strong>' . esc_html__( 'Customer:', 'coffee4u' ) . '</strong> ' . esc_html( $user ? $user->display_name . ' <' . $user->user_email . '>' : '' ) . '<br>';
	if ( $order['payment'] ) {
		echo '<strong>' . esc_html__( 'Payment:', 'coffee4u' ) . '</strong> ' . esc_html( $order['payment']['brand'] . ' **** ' . $order['payment']['last4'] . ' (' . $order['payment']['reference'] . ')' ) . '<br>';
	}
	echo '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Item', 'coffee4u' ) . '</th><th>' . esc_html__( 'Qty', 'coffee4u' ) . '</th><th>' . esc_html__( 'Total', 'coffee4u' ) . '</th></tr></thead><tbody>';
	foreach ( $order['totals']['lines'] as $line ) {
		echo '<tr><td>' . esc_html( $line['name'] ) . '</td><td>' . (int) $line['quantity'] . '</td><td>' . esc_html( c4u_format_money( $line['total'] ) ) . '</td></tr>';
	}
	echo '</tbody></table><p>';
	foreach ( c4u_totals_rows( $order['totals'] ) as $row ) {
		echo esc_html( $row['label'] . ': ' . $row['value'] ) . '<br>';
	}
	echo '</p>';
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( ! isset( $_POST['c4u_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['c4u_meta_nonce'] ), 'c4u_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['c4u'] ) ? wp_unslash( (array) $_POST['c4u'] ) : array();
		switch ( $post->post_type ) {
			case 'c4u_location':
				foreach ( array_keys( c4u_location_fields() ) as $key ) {
					update_post_meta( $post_id, '_c4u_' . $key, sanitize_text_field( $input[ $key ] ?? '' ) );
				}
				break;
			case 'c4u_product':
				update_post_meta( $post_id, '_c4u_price', number_format( max( 0, (float) ( $input['price'] ?? 0 ) ), 2, '.', '' ) );
				break;
			case 'c4u_coupon':
				$type = sanitize_key( $input['type'] ?? '' );
				update_post_meta( $post_id, '_c4u_type', array_key_exists( $type, c4u_coupon_types() ) ? $type : 'percent' );
				update_post_meta( $post_id, '_c4u_amount', max( 0, (float) ( $input['amount'] ?? 0 ) ) );
				update_post_meta( $post_id, '_c4u_first_order_only', empty( $input['first_order_only'] ) ? '0' : '1' );
				break;
		}
	},
	10,
	2
);

// Menu section ordering and beverage flag.
add_action(
	'c4u_menu_section_add_form_fields',
	function () {
		echo '<div class="form-field"><label for="c4u_order">' . esc_html__( 'Display order', 'coffee4u' ) . '</label><input type="number" name="c4u_order" id="c4u_order" value="0"></div>';
		echo '<div class="form-field"><label><input type="checkbox" name="c4u_is_beverage" value="1"> ' . esc_html__( 'Beverage section (eligible for free-beverage promo codes)', 'coffee4u' ) . '</label></div>';
	}
);
add_action(
	'c4u_menu_section_edit_form_fields',
	function ( $term ) {
		echo '<tr class="form-field"><th><label for="c4u_order">' . esc_html__( 'Display order', 'coffee4u' ) . '</label></th><td><input type="number" name="c4u_order" id="c4u_order" value="' . esc_attr( (int) get_term_meta( $term->term_id, 'c4u_order', true ) ) . '"></td></tr>';
		echo '<tr class="form-field"><th>' . esc_html__( 'Beverage section', 'coffee4u' ) . '</th><td><label><input type="checkbox" name="c4u_is_beverage" value="1"' . checked( (bool) get_term_meta( $term->term_id, 'c4u_is_beverage', true ), true, false ) . '> ' . esc_html__( 'Eligible for free-beverage promo codes', 'coffee4u' ) . '</label></td></tr>';
	}
);
$c4u_save_term = function ( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['c4u_order'] ) ) {
		return;
	}
	check_admin_referer( isset( $_POST['action'] ) && 'add-tag' === $_POST['action'] ? 'add-tag' : 'update-tag_' . $term_id, isset( $_POST['action'] ) && 'add-tag' === $_POST['action'] ? '_wpnonce_add-tag' : '_wpnonce' );
	update_term_meta( $term_id, 'c4u_order', (int) $_POST['c4u_order'] );
	update_term_meta( $term_id, 'c4u_is_beverage', empty( $_POST['c4u_is_beverage'] ) ? 0 : 1 );
};
add_action( 'created_c4u_menu_section', $c4u_save_term );
add_action( 'edited_c4u_menu_section', $c4u_save_term );

// Order list columns.
add_filter(
	'manage_c4u_order_posts_columns',
	function () {
		return array(
			'title'        => __( 'Order', 'coffee4u' ),
			'c4u_customer' => __( 'Customer', 'coffee4u' ),
			'c4u_location' => __( 'Location', 'coffee4u' ),
			'c4u_total'    => __( 'Total', 'coffee4u' ),
			'c4u_status'   => __( 'Status', 'coffee4u' ),
			'date'         => __( 'Date', 'coffee4u' ),
		);
	}
);
add_action(
	'manage_c4u_order_posts_custom_column',
	function ( $column, $post_id ) {
		$order = c4u_get_order( $post_id );
		if ( ! $order ) {
			return;
		}
		switch ( $column ) {
			case 'c4u_customer':
				$user = get_userdata( $order['user_id'] );
				echo esc_html( $user ? $user->display_name : '-' );
				break;
			case 'c4u_location':
				echo esc_html( $order['location']['name'] ?? '-' );
				break;
			case 'c4u_total':
				echo esc_html( c4u_format_money( $order['totals']['total'] ) );
				break;
			case 'c4u_status':
				echo esc_html( c4u_order_status_label( $order['status'] ) );
				break;
		}
	},
	10,
	2
);

add_filter(
	'manage_c4u_product_posts_columns',
	function ( $columns ) {
		return array_slice( $columns, 0, 2, true ) + array( 'c4u_price' => __( 'Price', 'coffee4u' ) ) + $columns;
	}
);
add_action(
	'manage_c4u_product_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'c4u_price' === $column ) {
			echo esc_html( c4u_format_money( c4u_price_to_cents( get_post_meta( $post_id, '_c4u_price', true ) ) ) );
		}
	},
	10,
	2
);
