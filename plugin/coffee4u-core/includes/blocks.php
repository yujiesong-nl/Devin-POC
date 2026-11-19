<?php
/**
 * Dynamic blocks for the storefront, template routing and front-end assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function c4u_blocks() {
	return array(
		'site-nav'           => __( 'Coffee4U Navigation', 'coffee4u' ),
		'location-list'      => __( 'Cafe Location Buttons', 'coffee4u' ),
		'location-details'   => __( 'Location Details', 'coffee4u' ),
		'location-bar'       => __( 'Location Footer Bar', 'coffee4u' ),
		'menu'               => __( 'Take-Out Menu', 'coffee4u' ),
		'checkout'           => __( 'Checkout Summary', 'coffee4u' ),
		'payment'            => __( 'Secure Payment Form', 'coffee4u' ),
		'order-confirmation' => __( 'Order Confirmation', 'coffee4u' ),
		'my-orders'          => __( 'My Orders', 'coffee4u' ),
		'group-order'        => __( 'Group Order Setup and Responses', 'coffee4u' ),
		'group-join'         => __( 'Join Group Order', 'coffee4u' ),
	);
}

add_action(
	'init',
	function () {
		wp_register_script( 'c4u-editor', C4U_URL . 'assets/js/editor.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ), C4U_VERSION, true );
		wp_add_inline_script( 'c4u-editor', 'window.c4uBlocks = ' . wp_json_encode( c4u_blocks() ) . ';', 'before' );
		foreach ( c4u_blocks() as $slug => $title ) {
			register_block_type(
				'coffee4u/' . $slug,
				array(
					'api_version'     => 3,
					'title'           => $title,
					'category'        => 'widgets',
					'editor_script'   => 'c4u-editor',
					'render_callback' => 'c4u_render_' . str_replace( '-', '_', $slug ),
					'supports'        => array( 'html' => false ),
				)
			);
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script( 'c4u-storefront', C4U_URL . 'assets/js/storefront.js', array(), C4U_VERSION, array( 'strategy' => 'defer' ) );
		wp_localize_script(
			'c4u-storefront',
			'c4uConfig',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'coffee4u/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
			)
		);
	}
);

// /locations/{slug}/menu/ renders the take-out menu template.
add_filter(
	'single_template_hierarchy',
	function ( $templates ) {
		global $wp_query;
		if ( is_singular( 'c4u_location' ) && isset( $wp_query->query_vars['menu'] ) ) {
			array_unshift( $templates, 'single-c4u_location-menu' );
		}
		return $templates;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		global $wp_query;
		if ( is_singular( 'c4u_location' ) && isset( $wp_query->query_vars['menu'] ) ) {
			$parts['title'] = sprintf( /* translators: %s: location */ __( 'Take-Out Menu - %s', 'coffee4u' ), $parts['title'] );
		}
		return $parts;
	}
);

/**
 * Location in context: the viewed location, the order being paid/confirmed, or the cart.
 */
function c4u_context_location() {
	if ( is_singular( 'c4u_location' ) ) {
		return c4u_get_location( get_queried_object_id() );
	}
	if ( isset( $_GET['group'] ) ) {
		$group = c4u_get_owned_group( absint( $_GET['group'] ) );
		if ( $group ) {
			return $group['location'];
		}
	}
	if ( isset( $_GET['location'] ) ) {
		return c4u_get_location( absint( $_GET['location'] ) );
	}
	if ( isset( $_GET['t'] ) ) {
		$group = c4u_find_group_by_token( sanitize_key( wp_unslash( $_GET['t'] ) ) );
		if ( $group ) {
			return $group['location'];
		}
	}
	if ( isset( $_GET['order'] ) ) {
		$order = c4u_get_customer_order( absint( $_GET['order'] ) );
		if ( $order ) {
			return $order['location'];
		}
	}
	return c4u_get_location( c4u_get_cart()['location_id'] );
}

function c4u_back_link( $url ) {
	return '<a class="c4u-back" href="' . esc_url( $url ) . '">&lt; ' . esc_html__( 'Back', 'coffee4u' ) . '</a>';
}

function c4u_stars( $rating ) {
	$out = '<span class="c4u-stars" role="img" aria-label="' . esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d out of 5', 'coffee4u' ), $rating ) ) . '">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= '<svg viewBox="0 0 24 24" class="' . ( $i <= $rating ? 'is-filled' : '' ) . '" aria-hidden="true"><path d="M12 2.5l2.9 6.6 7.1.6-5.4 4.7 1.6 7-6.2-3.7-6.2 3.7 1.6-7L2 9.7l7.1-.6z"/></svg>';
	}
	return $out . '</span>';
}

function c4u_render_site_nav() {
	$home  = home_url( '/' );
	$links = '<a href="' . esc_url( $home . '#locations' ) . '">' . esc_html__( 'Locations', 'coffee4u' ) . '</a>'
		. '<a href="' . esc_url( $home . '#about' ) . '">' . esc_html__( 'About Us', 'coffee4u' ) . '</a>'
		. '<a href="' . esc_url( $home . '#contact' ) . '">' . esc_html__( 'Contact Us', 'coffee4u' ) . '</a>';

	if ( is_user_logged_in() ) {
		$user      = wp_get_current_user();
		$favourite = c4u_get_favourite_location( $user->ID );
		$fav_name  = $favourite ? $favourite['name'] : __( 'Not set', 'coffee4u' );
		$account   = '<div class="c4u-account">'
			. '<button type="button" class="c4u-nav-button" aria-haspopup="true" aria-expanded="false" data-c4u-toggle="account">' . esc_html__( 'My Profile', 'coffee4u' ) . '</button>'
			. '<div class="c4u-account__menu" hidden>'
			. '<p class="c4u-account__name" data-c4u-account-name>' . esc_html( $user->display_name ) . '</p>'
			. '<button type="button" class="c4u-account__item" data-c4u-profile><strong>' . esc_html__( 'Edit Profile', 'coffee4u' ) . '</strong><span>' . esc_html__( 'User Details', 'coffee4u' ) . '</span></button>'
			. '<button type="button" class="c4u-account__item" data-c4u-cards><strong>' . esc_html__( 'Payment Cards', 'coffee4u' ) . '</strong><span>' . esc_html__( 'Manage Cards', 'coffee4u' ) . '</span></button>'
			. '<div class="c4u-account__fav">'
			. '<button type="button" class="c4u-account__item" data-c4u-favourite><strong>' . esc_html__( 'Favourite Location', 'coffee4u' ) . '</strong><span class="c4u-account__fav-name" data-c4u-fav-name>' . esc_html( $fav_name ) . '</span></button>'
			. '<a class="c4u-account__flyout" data-c4u-fav-link' . ( $favourite ? ' href="' . esc_url( $favourite['menu_url'] ) . '"' : '' ) . '>' . esc_html( $fav_name ) . '</a>'
			. '</div>'
			. '<a class="c4u-account__item" href="' . esc_url( c4u_page_url( 'my-orders' ) ) . '"><strong>' . esc_html__( 'My Orders', 'coffee4u' ) . '</strong><span>' . esc_html__( 'View previous orders', 'coffee4u' ) . '</span></a>'
			. '<a class="c4u-account__item" href="' . esc_url( wp_logout_url( $home ) ) . '"><strong>' . esc_html__( 'Log Out', 'coffee4u' ) . '</strong></a>'
			. '</div></div>';
	} else {
		$account = '<button type="button" class="c4u-nav-button" data-c4u-auth="signin">' . esc_html__( 'Sign-in/Register', 'coffee4u' ) . '</button>';
	}

	return '<nav class="c4u-nav" aria-label="' . esc_attr__( 'Main', 'coffee4u' ) . '">'
		. '<button type="button" class="c4u-nav__toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Menu', 'coffee4u' ) . '" data-c4u-toggle="nav"><span></span><span></span><span></span></button>'
		. '<div class="c4u-nav__items">' . $account . $links . '</div></nav>';
}

/** Cafe location buttons; the signed-in customer's favourite goes first and is tagged. */
function c4u_render_location_list() {
	$favourite = is_user_logged_in() ? c4u_get_favourite_location( get_current_user_id() ) : null;
	$items     = array();
	foreach ( c4u_get_locations() as $order => $location ) {
		$is_favourite = $favourite && $favourite['id'] === $location['id'];
		$item         = '<li data-location-id="' . esc_attr( $location['id'] ) . '" data-order="' . esc_attr( $order ) . '">';
		if ( $location['is_open'] ) {
			$item .= '<a class="c4u-location-button" href="' . esc_url( $location['url'] ) . '">' . esc_html( $location['name'] ) . '</a>'
				. '<span class="c4u-fav-tag" data-c4u-fav-tag' . ( $is_favourite ? '' : ' hidden' ) . '>&#9733; ' . esc_html__( 'My Favourite', 'coffee4u' ) . '</span>';
		} else {
			$item .= '<span class="c4u-location-button is-disabled" aria-disabled="true">' . esc_html( $location['name'] ) . '</span><span class="c4u-location-soon">' . esc_html__( 'Opening Shortly', 'coffee4u' ) . '</span>';
		}
		$item .= '</li>';
		if ( $is_favourite ) {
			array_unshift( $items, $item );
		} else {
			$items[] = $item;
		}
	}
	return '<ul class="c4u-location-list" data-c4u-location-list>' . implode( '', $items ) . '</ul>';
}

function c4u_render_favourite_toggle( $location ) {
	if ( ! is_user_logged_in() ) {
		return '<button type="button" class="c4u-fav-toggle" data-c4u-auth="signin" aria-pressed="false">&#9734; ' . esc_html__( 'Set as Favourite', 'coffee4u' ) . '</button>';
	}
	$favourite = c4u_get_favourite_location( get_current_user_id() );
	$pressed   = $favourite && $favourite['id'] === $location['id'];
	return '<button type="button" class="c4u-fav-toggle" data-c4u-fav-toggle="' . esc_attr( $location['id'] ) . '" aria-pressed="' . ( $pressed ? 'true' : 'false' ) . '">'
		. ( $pressed ? '&#9733; ' . esc_html__( 'My Favourite', 'coffee4u' ) : '&#9734; ' . esc_html__( 'Set as Favourite', 'coffee4u' ) ) . '</button>';
}

function c4u_render_location_details() {
	$location = is_singular( 'c4u_location' ) ? c4u_get_location( get_queried_object_id() ) : null;
	if ( ! $location ) {
		return '';
	}
	$tel = preg_replace( '/[^\d+]/', '', $location['phone'] );
	$out = '<section class="c4u-location">'
		. '<p class="c4u-welcome">' . esc_html__( 'WELCOME TO COFFEE4U', 'coffee4u' ) . '</p>'
		. '<h1 class="c4u-location__name">' . esc_html( $location['name'] ) . '</h1>'
		. '<p class="c4u-location__address">' . esc_html( $location['address'] ) . '</p>'
		. ( $location['is_open'] ? c4u_render_favourite_toggle( $location ) : '' )
		. c4u_stars( $location['rating'] )
		. '<p class="c4u-location__hours">' . esc_html( $location['hours'] ) . '</p>';
	if ( $location['is_open'] ) {
		$out .= '<a class="c4u-order-online" href="' . esc_url( $location['menu_url'] ) . '">' . esc_html__( 'Order Online for Pick-up', 'coffee4u' ) . '</a>';
	} else {
		$out .= '<p class="c4u-order-online is-disabled">' . esc_html__( 'Opening Shortly', 'coffee4u' ) . '</p>';
	}
	if ( $location['phone'] ) {
		$out .= '<p class="c4u-location__or">' . esc_html__( 'or', 'coffee4u' ) . '</p>'
			. '<p class="c4u-location__reserve">' . esc_html__( 'Contact', 'coffee4u' ) . ' <a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $location['phone'] ) . '</a> ' . esc_html__( 'to Reserve a Table', 'coffee4u' ) . '</p>';
	}
	return $out . '</section>';
}

function c4u_render_location_bar() {
	$location = c4u_context_location();
	if ( ! $location ) {
		return '';
	}
	return '<div class="c4u-location-bar"><span>' . esc_html( $location['name'] ) . '</span><span>' . esc_html( $location['address'] ) . '</span><span>' . esc_html( $location['hours'] ) . '</span></div>';
}

function c4u_render_menu() {
	$location = is_singular( 'c4u_location' ) ? c4u_get_location( get_queried_object_id() ) : null;
	if ( ! $location ) {
		return '';
	}
	if ( ! $location['is_open'] ) {
		return '<section class="c4u-page">' . c4u_back_link( $location['url'] ) . '<h1 class="c4u-page__title">' . esc_html( $location['name'] ) . '</h1><p class="c4u-notice">' . esc_html__( 'This location is opening shortly. Online ordering is not available yet.', 'coffee4u' ) . '</p></section>';
	}

	$sections = c4u_render_menu_sections();

	ob_start();
	?>
	<section class="c4u-page c4u-menu" data-c4u-menu data-location-id="<?php echo esc_attr( $location['id'] ); ?>">
		<?php echo c4u_back_link( $location['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<h1 class="c4u-page__title"><?php echo esc_html( $location['name'] ); ?></h1>
		<p class="c4u-page__subtitle"><?php esc_html_e( 'Take-Out Menu', 'coffee4u' ); ?></p>
		<div class="c4u-group-banner">
			<div><strong><?php esc_html_e( 'Ordering for a Group?', 'coffee4u' ); ?></strong><span><?php esc_html_e( 'Try our new Group Ordering Feature', 'coffee4u' ); ?></span></div>
			<a class="c4u-button c4u-button--dark" href="<?php echo esc_url( c4u_page_url( 'group-order', array( 'location' => $location['id'] ) ) ); ?>"><?php esc_html_e( 'Group Order', 'coffee4u' ); ?></a>
		</div>
		<div class="c4u-menu__layout">
			<div class="c4u-menu__sections"><?php echo $sections; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<aside class="c4u-cart" aria-label="<?php esc_attr_e( 'My Cart', 'coffee4u' ); ?>">
				<button type="button" class="c4u-link c4u-cart__toggle" aria-expanded="true" data-c4u-cart-toggle>
					<?php esc_html_e( 'View My Cart', 'coffee4u' ); ?> <span class="c4u-cart__count" data-c4u-cart-count></span>
				</button>
				<div class="c4u-cart__panel" data-c4u-cart-panel>
					<h2><?php esc_html_e( 'My Cart', 'coffee4u' ); ?></h2>
					<div data-c4u-cart-items><p class="c4u-cart__empty"><?php esc_html_e( 'Loading...', 'coffee4u' ); ?></p></div>
				</div>
				<button type="button" class="c4u-button c4u-button--large" data-c4u-confirm-order disabled><?php esc_html_e( 'Confirm Order', 'coffee4u' ); ?></button>
			</aside>
		</div>
		<?php echo c4u_render_product_modal( __( 'Add to Cart', 'coffee4u' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</section>
	<?php
	return ob_get_clean();
}

function c4u_render_menu_sections() {
	$sections = '';
	foreach ( c4u_get_menu() as $section ) {
		$sections .= '<section class="c4u-menu__section" id="' . esc_attr( 'section-' . $section['slug'] ) . '"><h2>' . esc_html( $section['name'] ) . '</h2><ul class="c4u-menu__grid">';
		foreach ( $section['items'] as $item ) {
			$item['price_formatted'] = c4u_format_money( $item['price'] );
			$sections               .= '<li><button type="button" class="c4u-product-card" data-c4u-product="' . esc_attr( wp_json_encode( $item ) ) . '">'
				. ( $item['image'] ? '<img src="' . esc_url( $item['image'] ) . '" alt="" loading="lazy" width="160" height="160">' : '<span class="c4u-product-card__placeholder"></span>' )
				. '<span class="c4u-product-card__name">' . esc_html( $item['name'] ) . '</span>'
				. '<span class="c4u-product-card__price">' . esc_html( $item['price_formatted'] ) . '</span>'
				. '</button></li>';
		}
		$sections .= '</ul></section>';
	}

	return $sections;
}

function c4u_render_product_modal( $button_label ) {
	ob_start();
	?>
		<dialog class="c4u-modal c4u-product-modal" data-c4u-product-modal aria-labelledby="c4u-product-name">
			<button type="button" class="c4u-modal__close" data-c4u-close aria-label="<?php esc_attr_e( 'Close', 'coffee4u' ); ?>">&times;</button>
			<div class="c4u-product-modal__top">
				<img data-field="image" alt="">
				<div>
					<h2 id="c4u-product-name" data-field="name"></h2>
					<p class="c4u-product-modal__price" data-field="price_formatted"></p>
				</div>
			</div>
			<p class="c4u-product-modal__desc" data-field="description"></p>
			<div class="c4u-qty" data-c4u-qty>
				<button type="button" data-step="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'coffee4u' ); ?>">&minus;</button>
				<output aria-live="polite">1</output>
				<button type="button" data-step="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'coffee4u' ); ?>">+</button>
			</div>
			<p class="c4u-form-error" data-c4u-error role="alert"></p>
			<button type="button" class="c4u-button c4u-button--large" data-c4u-add-to-cart><?php echo esc_html( $button_label ); ?></button>
		</dialog>
	<?php
	return ob_get_clean();
}

function c4u_signin_required( $message ) {
	return '<div class="c4u-panel c4u-center"><p>' . esc_html( $message ) . '</p><button type="button" class="c4u-button" data-c4u-auth="signin">' . esc_html__( 'Sign-in/Register', 'coffee4u' ) . '</button></div>';
}

function c4u_promo_button_label( $data ) {
	if ( ! $data['coupon_code'] ) {
		return __( 'Apply', 'coffee4u' );
	}
	return $data['coupon'] ? __( 'Applied', 'coffee4u' ) : __( 'Remove', 'coffee4u' );
}

function c4u_render_checkout() {
	$cart     = c4u_get_cart();
	$location = c4u_get_location( $cart['location_id'] );
	$out      = '<section class="c4u-page c4u-checkout" data-c4u-checkout>';
	if ( ! $location || ! $cart['items'] ) {
		return $out . '<h1 class="c4u-page__title">' . esc_html__( 'Checkout', 'coffee4u' ) . '</h1><div class="c4u-panel c4u-center"><p>' . esc_html__( 'Your cart is empty.', 'coffee4u' ) . '</p><a class="c4u-button" href="' . esc_url( home_url( '/#locations' ) ) . '">' . esc_html__( 'Choose a Location', 'coffee4u' ) . '</a></div></section>';
	}
	$out .= c4u_back_link( $location['menu_url'] ) . '<h1 class="c4u-page__title">' . esc_html( $location['name'] ) . '</h1>';
	if ( ! is_user_logged_in() ) {
		return $out . c4u_signin_required( __( 'Please sign in or create a profile to check out.', 'coffee4u' ) ) . '</section>';
	}
	$data = c4u_cart_response( $cart );
	ob_start();
	?>
	<div class="c4u-summary">
		<ul class="c4u-summary__items">
			<?php foreach ( $data['items'] as $line ) : ?>
				<li><span><?php echo esc_html( $line['quantity'] . ' x ' . $line['name'] ); ?></span><span><?php echo esc_html( $line['total_formatted'] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<form class="c4u-promo" data-c4u-promo>
			<label class="screen-reader-text" for="c4u-promo-code"><?php esc_html_e( 'Promo code', 'coffee4u' ); ?></label>
			<input id="c4u-promo-code" name="code" placeholder="<?php esc_attr_e( 'Enter Promo Code', 'coffee4u' ); ?>" value="<?php echo esc_attr( (string) $data['coupon_code'] ); ?>" autocomplete="off" <?php disabled( (bool) $data['coupon_code'] ); ?>>
			<button type="submit" class="c4u-button c4u-button--dark" data-applied="<?php echo $data['coupon_code'] ? '1' : '0'; ?>"<?php echo $data['coupon_code'] ? ' title="' . esc_attr__( 'Click to remove the promo code', 'coffee4u' ) . '"' : ''; ?>><?php echo esc_html( c4u_promo_button_label( $data ) ); ?></button>
		</form>
		<p class="c4u-form-error" data-c4u-error role="alert"><?php echo esc_html( (string) $data['coupon_error'] ); ?></p>
		<dl class="c4u-summary__rows" data-c4u-rows>
			<?php foreach ( $data['rows'] as $row ) : ?>
				<div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div>
			<?php endforeach; ?>
		</dl>
		<div class="c4u-summary__total"><span><?php esc_html_e( 'Total Amount Payable', 'coffee4u' ); ?></span><strong data-c4u-total><?php echo esc_html( $data['total'] ); ?></strong></div>
		<button type="button" class="c4u-button c4u-button--large" data-c4u-proceed><?php esc_html_e( 'Proceed to Payment', 'coffee4u' ); ?></button>
	</div>
	<?php
	return $out . ob_get_clean() . '</section>';
}

function c4u_card_logo( $brand ) {
	$logos = array(
		'Visa'             => '<span class="c4u-card c4u-card--visa">VISA</span>',
		'Mastercard'       => '<span class="c4u-card c4u-card--mc"><i></i><i></i></span>',
		'American Express' => '<span class="c4u-card c4u-card--amex">AMEX</span>',
	);
	return $logos[ $brand ] ?? '';
}

function c4u_accepted_cards() {
	return '<span class="c4u-cards" aria-label="' . esc_attr__( 'Accepted cards: Visa, Mastercard, American Express', 'coffee4u' ) . '">'
		. c4u_card_logo( 'Visa' ) . c4u_card_logo( 'Mastercard' ) . c4u_card_logo( 'American Express' ) . '</span>';
}

/** Saved cards as radio options; the first usable card is selected. */
function c4u_saved_card_options( $cards ) {
	$out     = '<fieldset class="c4u-saved-cards"><legend>' . esc_html__( 'Pay with a Saved Card', 'coffee4u' ) . '</legend>';
	$checked = false;
	foreach ( $cards as $card ) {
		$select = ! $checked && ! $card['expired'];
		$checked = $checked || $select;
		$out    .= '<label class="c4u-saved-card' . ( $card['expired'] ? ' is-expired' : '' ) . '">'
			. '<input type="radio" name="card_id" value="' . esc_attr( $card['id'] ) . '" data-brand="' . esc_attr( $card['brand'] ) . '"' . checked( $select, true, false ) . disabled( $card['expired'], true, false ) . '>'
			. c4u_card_logo( $card['brand'] )
			. '<span class="c4u-saved-card__number">&bull;&bull;&bull;&bull; ' . esc_html( $card['last4'] ) . '</span>'
			. '<span class="c4u-saved-card__meta">' . esc_html( $card['expired'] ? __( 'Expired', 'coffee4u' ) . ' ' . $card['expiry'] : __( 'Expires', 'coffee4u' ) . ' ' . $card['expiry'] ) . ' &middot; ' . esc_html( $card['name'] ) . '</span>'
			. '</label>';
	}
	$out .= '<label class="c4u-saved-card"><input type="radio" name="card_id" value=""' . checked( $checked, false, false ) . '><span class="c4u-saved-card__number">' . esc_html__( 'Use a new card', 'coffee4u' ) . '</span></label>'
		. '<span class="c4u-field__error" data-error-for="card_id"></span></fieldset>';
	return $out;
}

function c4u_render_payment() {
	$out = '<section class="c4u-page c4u-payment">';
	if ( ! is_user_logged_in() ) {
		return $out . c4u_signin_required( __( 'Please sign in to pay for your order.', 'coffee4u' ) ) . '</section>';
	}
	$order = isset( $_GET['order'] ) ? c4u_get_customer_order( absint( $_GET['order'] ) ) : null;
	if ( ! $order ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'Order not found.', 'coffee4u' ) . '</p></div></section>';
	}
	$out .= c4u_back_link( c4u_page_url( 'checkout' ) ) . '<h1 class="c4u-page__title">' . esc_html( $order['location']['name'] ) . '</h1>';
	if ( 'paid' === $order['status'] ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'This order has already been paid.', 'coffee4u' ) . '</p><a class="c4u-button" href="' . esc_url( $order['confirmation_url'] ) . '">' . esc_html__( 'View Confirmation', 'coffee4u' ) . '</a></div></section>';
	}
	$payable = c4u_order_payable( $order );
	if ( is_wp_error( $payable ) ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html( $payable->get_error_message() ) . '</p><a class="c4u-button" href="' . esc_url( c4u_page_url( 'checkout' ) ) . '">' . esc_html__( 'Back to Checkout', 'coffee4u' ) . '</a></div></section>';
	}
	$user  = wp_get_current_user();
	$phone = get_user_meta( $user->ID, 'c4u_phone', true );
	$saved = c4u_cards_view( $user->ID );
	ob_start();
	?>
	<form class="c4u-pay-form" data-c4u-pay="<?php echo esc_attr( $order['id'] ); ?>" novalidate>
		<div class="c4u-summary__total"><span><?php esc_html_e( 'Total Amount Payable', 'coffee4u' ); ?></span><strong><?php echo esc_html( c4u_format_money( $order['totals']['total'] ) ); ?></strong></div>
		<div class="c4u-pay-form__head">
			<h2><?php esc_html_e( 'Enter Payment Details', 'coffee4u' ); ?></h2>
			<?php echo c4u_accepted_cards(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped markup. ?>
		</div>
		<?php if ( $saved['cards'] ) : ?>
			<?php echo c4u_saved_card_options( $saved['cards'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped markup. ?>
			<div class="c4u-field" data-c4u-saved-cvv><input id="c4u-card-cvv" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="&#8226;&#8226;&#8226;" required><label for="c4u-card-cvv"><?php esc_html_e( 'CVV of the Saved Card', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="card_cvv"></span></div>
		<?php endif; ?>
		<div data-c4u-new-card>
		<div class="c4u-field c4u-field--full"><input id="c4u-card" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226;" required><label for="c4u-card"><?php esc_html_e( 'Card Number', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="card_number"></span></div>
		<div class="c4u-field-row">
			<div class="c4u-field"><input id="c4u-exp" name="expiry" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="MM/YY" required><label for="c4u-exp"><?php esc_html_e( 'Validity Details', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="expiry"></span></div>
			<div class="c4u-field"><input id="c4u-cvv" name="cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="&#8226;&#8226;&#8226;" required><label for="c4u-cvv"><?php esc_html_e( 'CVV', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="cvv"></span></div>
		</div>
		<div class="c4u-field c4u-field--full"><input id="c4u-name" name="name" autocomplete="cc-name" value="<?php echo esc_attr( $user->display_name ); ?>" required><label for="c4u-name"><?php esc_html_e( 'Name on Card', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="name"></span></div>
			<?php if ( $saved['can_add'] ) : ?>
				<label class="c4u-check"><input type="checkbox" name="save_card" value="1"> <?php esc_html_e( 'Save this card for next time', 'coffee4u' ); ?></label>
			<?php endif; ?>
		</div>
		<div class="c4u-field c4u-field--full"><input id="c4u-phone" name="phone" type="tel" autocomplete="tel" value="<?php echo esc_attr( $phone ); ?>" required><label for="c4u-phone"><?php esc_html_e( 'Contact Number', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="phone"></span></div>
		<p class="c4u-form-error" data-c4u-error role="alert"></p>
		<div class="c4u-pay-form__submit">
			<button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Secure Payment', 'coffee4u' ); ?></button>
			<svg class="c4u-shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 9h8M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.8"/></svg>
		</div>
		<p class="c4u-test-hint"><?php esc_html_e( 'Demo payment: no money is charged, and the full card number and CVV are never stored. Use 4242 4242 4242 4242 with any future expiry and CVV. 4000 0000 0000 0002 simulates a declined card.', 'coffee4u' ); ?></p>
	</form>
	<?php
	return $out . ob_get_clean() . '</section>';
}

function c4u_render_order_confirmation() {
	$order = isset( $_GET['order'] ) ? c4u_get_customer_order( absint( $_GET['order'] ) ) : null;
	if ( ! $order || 'paid' !== $order['status'] ) {
		return '<section class="c4u-page"><div class="c4u-panel c4u-center"><p>' . esc_html__( 'Order not found.', 'coffee4u' ) . '</p></div></section>';
	}
	$location = $order['location'];
	return '<section class="c4u-page c4u-confirmation">'
		. c4u_back_link( $location['url'] ?? home_url( '/' ) )
		. '<h1 class="c4u-page__title">' . esc_html( $location['name'] ) . '</h1>'
		. '<p class="c4u-confirmation__lead">&ldquo;' . esc_html__( 'Your Order has been', 'coffee4u' ) . ' <u>' . esc_html__( 'Confirmed', 'coffee4u' ) . '</u>&rdquo;</p>'
		. '<p class="c4u-confirmation__number">' . esc_html__( 'Order Number:', 'coffee4u' ) . ' ' . esc_html( $order['number'] ) . '</p>'
		. '<p class="c4u-confirmation__barista">&ldquo;' . esc_html__( 'Our Barista is brewing your order with Love', 'coffee4u' ) . '&rdquo;</p>'
		. '<p class="c4u-confirmation__ready">' . esc_html__( 'Your pick-up will be ready in', 'coffee4u' ) . ' <span class="c4u-badge">' . esc_html( $order['ready_minutes'] ) . '</span> ' . esc_html__( 'minutes', 'coffee4u' ) . '</p>'
		/* translators: %s: location name */
		. '<p class="c4u-confirmation__at">' . esc_html( sprintf( __( 'at Coffee4U %s', 'coffee4u' ), $location['name'] ) ) . '</p>'
		. '</section>';
}

function c4u_render_my_orders() {
	$out = '<section class="c4u-page"><h1 class="c4u-page__title">' . esc_html__( 'My Orders', 'coffee4u' ) . '</h1>';
	if ( ! is_user_logged_in() ) {
		return $out . c4u_signin_required( __( 'Please sign in to see your orders.', 'coffee4u' ) ) . '</section>';
	}
	foreach ( c4u_get_user_groups( get_current_user_id() ) as $group ) {
		/* translators: 1: location, 2: responses, 3: group size */
		$out .= '<p class="c4u-notice c4u-group-notice"><span>' . esc_html( sprintf( __( 'Group order at %1$s in progress: %2$d of %3$d responses', 'coffee4u' ), $group['location']['name'] ?? '', count( $group['responses'] ), $group['size'] ) ) . '</span> <a class="c4u-button" href="' . esc_url( $group['status_url'] ) . '">' . esc_html__( 'View', 'coffee4u' ) . '</a></p>';
	}
	$orders = c4u_get_customer_orders( get_current_user_id() );
	if ( ! $orders ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'You have not placed any orders yet.', 'coffee4u' ) . '</p><a class="c4u-button" href="' . esc_url( home_url( '/#locations' ) ) . '">' . esc_html__( 'Order Now', 'coffee4u' ) . '</a></div></section>';
	}
	$out .= '<div class="c4u-panel"><table class="c4u-orders"><thead><tr><th>' . esc_html__( 'Order', 'coffee4u' ) . '</th><th>' . esc_html__( 'Date', 'coffee4u' ) . '</th><th>' . esc_html__( 'Location', 'coffee4u' ) . '</th><th>' . esc_html__( 'Total', 'coffee4u' ) . '</th><th>' . esc_html__( 'Status', 'coffee4u' ) . '</th></tr></thead><tbody>';
	foreach ( $orders as $order ) {
		$url  = 'paid' === $order['status'] ? $order['confirmation_url'] : $order['payment_url'];
		$out .= '<tr><td><a href="' . esc_url( $url ) . '">#' . esc_html( $order['number'] ) . '</a>' . ( $order['group'] ? ' <span class="c4u-tag">' . esc_html__( 'Group', 'coffee4u' ) . '</span>' : '' ) . '</td><td>' . esc_html( wp_date( get_option( 'date_format' ), $order['created'] ) ) . '</td><td>' . esc_html( $order['location']['name'] ?? '' ) . '</td><td>' . esc_html( c4u_format_money( $order['totals']['total'] ) ) . '</td><td>' . esc_html( c4u_order_status_label( $order['status'] ) ) . '</td></tr>';
	}
	return $out . '</tbody></table></div></section>';
}

function c4u_share_icon( $network ) {
	$icons = array(
		'sms'       => '<path d="M5 3h14a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H9l-5 4v-4.3A3 3 0 0 1 2 15V6a3 3 0 0 1 3-3z"/><text x="12" y="13.2" font-size="6.4" font-weight="700" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif">SMS</text>',
		'whatsapp'  => '<path d="M12 2.5a9.5 9.5 0 0 0-8.2 14.3L2.5 21.5l4.8-1.3A9.5 9.5 0 1 0 12 2.5z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8.6 7.3c.3-.4.8-.4 1.1 0l1 1.7c.2.4.1.8-.2 1.1l-.5.5a6 6 0 0 0 3.4 3.4l.5-.5c.3-.3.7-.4 1.1-.2l1.7 1c.4.3.4.8 0 1.1-1 1.1-2.4 1.3-3.9.6a9.3 9.3 0 0 1-4.6-4.6c-.7-1.5-.5-2.9.4-4.1z"/>',
		'messenger' => '<path d="M12 2C6.5 2 2.3 6 2.3 11.3c0 2.8 1.2 5.2 3.1 6.9V22l3.4-1.9c1 .3 2.1.4 3.2.4 5.5 0 9.7-4 9.7-9.2S17.5 2 12 2z"/><path d="M6.5 14.2l3.5-5.3 2.8 2.3 4.6-2.6-3.6 5.3-2.7-2.3z" fill="#fff"/>',
		'facebook'  => '<circle cx="12" cy="12" r="10"/><path d="M13.3 22v-7.2h2.4l.4-2.8h-2.8v-1.8c0-.8.3-1.4 1.4-1.4h1.5V6.3a19 19 0 0 0-2.2-.1c-2.2 0-3.6 1.3-3.6 3.7v2.1H8v2.8h2.4V22z" fill="#fff"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.3" cy="6.7" r="1.2"/>',
		'email'     => '<rect x="2.5" y="5" width="19" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 6l9 7 9-7" fill="none" stroke="currentColor" stroke-width="2"/>',
		'slack'     => '<rect x="9" y="2" width="3.6" height="9" rx="1.8"/><rect x="11.4" y="13" width="3.6" height="9" rx="1.8"/><rect x="13" y="9" width="9" height="3.6" rx="1.8"/><rect x="2" y="11.4" width="9" height="3.6" rx="1.8"/>',
		'teams'     => '<rect x="2" y="5" width="13" height="14" rx="2"/><path d="M5.5 9h6v1.8H9.4V16H7.6v-5.2H5.5z" fill="#fff"/><circle cx="18.5" cy="6.5" r="2.2"/><path d="M16.5 10h4a1.5 1.5 0 0 1 1.5 1.5v4a3.5 3.5 0 0 1-5.5 2.9z"/>',
	);
	return '<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true">' . $icons[ $network ] . '</svg>';
}

/**
 * Invite link with share buttons; the storefront script builds each network's share URL.
 */
function c4u_render_group_share( $join_url = '' ) {
	$networks = array(
		'sms'       => 'SMS',
		'whatsapp'  => 'WhatsApp',
		'messenger' => 'Messenger',
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'email'     => 'Email',
		'slack'     => 'Slack',
		'teams'     => 'Microsoft Teams',
	);
	$out = '<div class="c4u-group-share" data-c4u-group-share>'
		. '<p class="c4u-group-share__title">' . esc_html__( 'Invite friends to conveniently order', 'coffee4u' ) . '</p>'
		. '<div class="c4u-group-share__link"' . ( $join_url ? '' : ' hidden' ) . '><input type="text" readonly aria-label="' . esc_attr__( 'Invite link', 'coffee4u' ) . '" value="' . esc_attr( $join_url ) . '" data-c4u-group-link><button type="button" class="c4u-button" data-c4u-group-copy>' . esc_html__( 'Copy Link', 'coffee4u' ) . '</button></div>'
		. '<ul class="c4u-group-share__icons">';
	foreach ( $networks as $network => $label ) {
		/* translators: %s: app or network name */
		$out .= '<li><button type="button" data-c4u-share="' . esc_attr( $network ) . '" aria-label="' . esc_attr( sprintf( __( 'Share via %s', 'coffee4u' ), $label ) ) . '" title="' . esc_attr( $label ) . '"' . ( $join_url ? '' : ' disabled' ) . '>' . c4u_share_icon( $network ) . '</button></li>';
	}
	return $out . '</ul></div>';
}

function c4u_group_page_head( $location ) {
	return c4u_back_link( $location['menu_url'] )
		. '<h1 class="c4u-page__title">' . esc_html( $location['name'] ) . '</h1>'
		. '<p class="c4u-page__subtitle">' . esc_html__( 'Group Order Take-Out', 'coffee4u' ) . '</p>';
}

function c4u_group_stepper( $name, $label, $unit, $value, $min, $max, $step ) {
	return '<div class="c4u-group-setup__row"><label for="c4u-group-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong> (' . esc_html( $unit ) . ')</label>'
		. '<span class="c4u-qty" data-c4u-stepper>'
		. '<button type="button" data-step="-' . esc_attr( $step ) . '" aria-label="' . esc_attr__( 'Decrease', 'coffee4u' ) . '">&minus;</button>'
		. '<input id="c4u-group-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="number" inputmode="numeric" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '">'
		. '<button type="button" data-step="' . esc_attr( $step ) . '" aria-label="' . esc_attr__( 'Increase', 'coffee4u' ) . '">+</button>'
		. '</span><span class="c4u-field__error" data-error-for="' . esc_attr( $name ) . '"></span></div>';
}

/**
 * Organizer pages: ?location=ID sets up a new group order, ?group=ID tracks responses.
 */
function c4u_render_group_order() {
	$out = '<section class="c4u-page c4u-group">';
	if ( isset( $_GET['group'] ) ) {
		$group = c4u_get_owned_group( absint( $_GET['group'] ) );
		if ( ! $group ) {
			return $out . ( is_user_logged_in()
				? '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'Group order not found.', 'coffee4u' ) . '</p></div>'
				: c4u_signin_required( __( 'Please sign in to see your group order.', 'coffee4u' ) ) ) . '</section>';
		}
		return $out . c4u_render_group_status( $group ) . '</section>';
	}

	$location = isset( $_GET['location'] ) ? c4u_get_location( absint( $_GET['location'] ) ) : null;
	if ( ! $location || ! $location['is_open'] ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'Choose an open location to start a group order.', 'coffee4u' ) . '</p><a class="c4u-button" href="' . esc_url( home_url( '/#locations' ) ) . '">' . esc_html__( 'Locations', 'coffee4u' ) . '</a></div></section>';
	}
	$out .= c4u_group_page_head( $location );
	if ( ! is_user_logged_in() ) {
		return $out . c4u_signin_required( __( 'Please sign in to start a group order.', 'coffee4u' ) ) . '</section>';
	}
	return $out . '<form class="c4u-group-setup" data-c4u-group-setup data-location-id="' . esc_attr( $location['id'] ) . '" novalidate>'
		. '<h2>' . wp_kses( __( 'Order for your Group in <b>4</b> simple steps', 'coffee4u' ), array( 'b' => array() ) ) . '</h2>'
		. c4u_group_stepper( 'size', __( 'Enter Group Size', 'coffee4u' ), __( 'persons', 'coffee4u' ), 2, C4U_GROUP_MIN_SIZE, C4U_GROUP_MAX_SIZE, 1 )
		. c4u_group_stepper( 'minutes', __( 'Set Timer', 'coffee4u' ), __( 'minutes', 'coffee4u' ), 15, C4U_GROUP_MIN_MINUTES, C4U_GROUP_MAX_MINUTES, 5 )
		. '<fieldset class="c4u-group-setup__row c4u-group-setup__limit"><legend><strong>' . esc_html__( 'Set Amount Limit', 'coffee4u' ) . '</strong></legend>'
		. '<span class="c4u-group-setup__radios"><label><input type="radio" name="has_limit" value="1"> ' . esc_html__( 'Yes', 'coffee4u' ) . '</label><label><input type="radio" name="has_limit" value="" checked> ' . esc_html__( 'No', 'coffee4u' ) . '</label></span>'
		. '<label class="c4u-group-setup__amount" for="c4u-group-limit"><strong>' . esc_html__( 'Amount Limit', 'coffee4u' ) . '</strong> (' . esc_html__( 'per person', 'coffee4u' ) . ')</label>'
		. '<span class="c4u-group-setup__money">$ <input id="c4u-group-limit" name="limit" type="number" inputmode="decimal" min="1" max="500" step="0.01" placeholder="X.XX" disabled></span>'
		. '<span class="c4u-field__error" data-error-for="limit"></span></fieldset>'
		. '<p class="c4u-form-error" data-c4u-error role="alert"></p>'
		. '<p class="c4u-center"><button type="submit" class="c4u-button c4u-button--large">' . esc_html__( 'Generate Link', 'coffee4u' ) . '</button></p>'
		. c4u_render_group_share()
		. '<p class="c4u-center" data-c4u-group-track hidden><a class="c4u-button c4u-button--dark" href="">' . esc_html__( 'Track Responses', 'coffee4u' ) . '</a></p>'
		. '</form></section>';
}

function c4u_render_group_status( $group ) {
	$view = c4u_group_owner_view( $group );
	return c4u_group_page_head( $group['location'] )
		. '<div class="c4u-group-status" data-c4u-group-status="' . esc_attr( wp_json_encode( $view ) ) . '">'
		. '<div class="c4u-group-status__orders"><table class="c4u-group-table"><thead><tr><th><span class="screen-reader-text">#</span></th><th>' . esc_html__( 'Order Details', 'coffee4u' ) . '</th><th>' . esc_html__( 'Amount', 'coffee4u' ) . '</th><th class="c4u-group-table__edit"><span class="screen-reader-text">' . esc_html__( 'Remove', 'coffee4u' ) . '</span></th></tr></thead>'
		. '<tbody data-c4u-group-rows></tbody>'
		. '<tfoot><tr><td></td><th>' . esc_html__( 'Order Total', 'coffee4u' ) . '</th><th data-c4u-group-total></th><td class="c4u-group-table__edit"></td></tr></tfoot></table></div>'
		. '<aside class="c4u-group-status__side">'
		. '<h2>' . esc_html__( 'Responses received', 'coffee4u' ) . '<br><span data-c4u-group-count></span></h2>'
		. '<svg class="c4u-stopwatch" viewBox="0 0 64 64" width="76" height="76" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"><circle cx="32" cy="37" r="22"/><path d="M26 6h12M32 6v9M50 15l4 4M14 15l-4 4M32 37V23M32 37l9 7"/></g></svg>'
		. '<p class="c4u-group-status__left" data-c4u-group-left aria-live="polite"></p>'
		. '<div class="c4u-group-status__edit" data-c4u-group-edit-panel hidden><p>' . esc_html__( 'Remove a response, or give everyone more time.', 'coffee4u' ) . '</p><button type="button" class="c4u-button c4u-button--dark" data-c4u-group-extend>' . esc_html__( 'Add 5 minutes', 'coffee4u' ) . '</button></div>'
		. '<button type="button" class="c4u-link" data-c4u-group-edit aria-expanded="false">' . esc_html__( 'Edit Order', 'coffee4u' ) . '</button>'
		. '<button type="button" class="c4u-button c4u-button--large" data-c4u-group-confirm>' . esc_html__( 'Confirm Order', 'coffee4u' ) . '</button>'
		. '<p class="c4u-form-error" data-c4u-error role="alert"></p>'
		. '</aside></div>'
		. c4u_render_group_share( $group['join_url'] );
}

/**
 * Invite-link page: anyone with the link picks items from the take-out menu, no account needed.
 */
function c4u_render_group_join() {
	$token = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
	$group = $token ? c4u_find_group_by_token( $token ) : null;
	if ( ! $group || ! $group['location'] ) {
		return '<section class="c4u-page"><div class="c4u-panel c4u-center"><p>' . esc_html__( 'This group order link is not valid.', 'coffee4u' ) . '</p></div></section>';
	}
	$location = $group['location'];
	$view     = c4u_group_public_view( $group );
	ob_start();
	?>
	<section class="c4u-page c4u-menu c4u-group-join" data-c4u-group-join="<?php echo esc_attr( $token ); ?>" data-c4u-group-view="<?php echo esc_attr( wp_json_encode( $view ) ); ?>">
		<h1 class="c4u-page__title"><?php echo esc_html( $location['name'] ); ?></h1>
		<p class="c4u-page__subtitle"><?php esc_html_e( 'Group Order Take-Out', 'coffee4u' ); ?></p>
		<div class="c4u-group-banner c4u-group-join__banner">
			<div>
				<strong data-c4u-join-title></strong>
				<span data-c4u-join-meta></span>
			</div>
		</div>
		<div class="c4u-panel c4u-center" data-c4u-join-closed hidden><p></p></div>
		<div class="c4u-menu__layout" data-c4u-join-open>
			<div class="c4u-menu__sections"><?php echo c4u_render_menu_sections(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<aside class="c4u-cart" aria-label="<?php esc_attr_e( 'My Order', 'coffee4u' ); ?>">
				<form class="c4u-cart__panel" data-c4u-join-form novalidate>
					<h2><?php esc_html_e( 'My Order', 'coffee4u' ); ?></h2>
					<div data-c4u-cart-items></div>
					<div class="c4u-field c4u-join__name">
						<span class="c4u-field__error" data-error-for="name"></span>
						<input id="c4u-join-name" name="name" autocomplete="given-name" maxlength="40" required>
						<label for="c4u-join-name"><?php esc_html_e( 'Your Name', 'coffee4u' ); ?></label>
					</div>
					<p class="c4u-form-error" data-c4u-error role="alert"></p>
					<button type="submit" class="c4u-button c4u-button--large" data-c4u-join-submit disabled><?php esc_html_e( 'Submit My Order', 'coffee4u' ); ?></button>
					<p class="c4u-join__done" data-c4u-join-done hidden><?php esc_html_e( 'Your order is in! You can change it until the timer runs out.', 'coffee4u' ); ?></p>
				</form>
			</aside>
		</div>
		<?php echo c4u_render_product_modal( __( 'Add to My Order', 'coffee4u' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</section>
	<?php
	return ob_get_clean();
}

// Sign-in / sign-up dialog for guests, matching the "Log-in or Register" and "Create User Profile" frames.
add_action(
	'wp_footer',
	function () {
		if ( is_user_logged_in() ) {
			return;
		}
		?>
		<dialog class="c4u-modal c4u-auth" data-c4u-auth-modal aria-label="<?php esc_attr_e( 'Sign in or sign up', 'coffee4u' ); ?>">
			<button type="button" class="c4u-modal__close" data-c4u-close aria-label="<?php esc_attr_e( 'Close', 'coffee4u' ); ?>">&times;</button>
			<div data-view="signin">
				<p class="c4u-center"><button type="button" class="c4u-link" data-c4u-auth-view="signup"><?php esc_html_e( 'Sign-up', 'coffee4u' ); ?></button> <?php esc_html_e( 'for your First Free Coffee', 'coffee4u' ); ?></p>
				<p class="c4u-center"><button type="button" class="c4u-button c4u-button--large" data-c4u-auth-view="signup"><?php esc_html_e( 'Sign-up', 'coffee4u' ); ?></button></p>
				<p class="c4u-center"><?php esc_html_e( 'Create User Profile', 'coffee4u' ); ?></p>
				<form data-c4u-auth-form="login" novalidate>
					<p class="c4u-center c4u-auth__sub"><?php esc_html_e( 'Already have an account?', 'coffee4u' ); ?> <u><?php esc_html_e( 'Sign-in', 'coffee4u' ); ?></u></p>
					<div class="c4u-auth__row"><label for="c4u-login-email"><?php esc_html_e( 'E-mail:', 'coffee4u' ); ?></label><input id="c4u-login-email" name="email" type="email" autocomplete="email" required></div>
					<div class="c4u-auth__row"><label for="c4u-login-password"><?php esc_html_e( 'Password:', 'coffee4u' ); ?></label><input id="c4u-login-password" name="password" type="password" autocomplete="current-password" required></div>
					<p class="c4u-form-error" data-c4u-error role="alert"></p>
					<p class="c4u-center"><button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Sign-in', 'coffee4u' ); ?></button></p>
				</form>
			</div>
			<div data-view="signup" hidden>
				<form data-c4u-auth-form="register" novalidate>
					<div class="c4u-auth__row"><label for="c4u-reg-name"><?php esc_html_e( 'Full Name:', 'coffee4u' ); ?></label><input id="c4u-reg-name" name="name" autocomplete="name" required><span class="c4u-field__error" data-error-for="name"></span></div>
					<div class="c4u-auth__row"><label for="c4u-reg-phone"><?php esc_html_e( 'Phone No.:', 'coffee4u' ); ?></label><input id="c4u-reg-phone" name="phone" type="tel" autocomplete="tel" required><span class="c4u-field__error" data-error-for="phone"></span></div>
					<div class="c4u-auth__row"><label for="c4u-reg-email"><?php esc_html_e( 'E-mail:', 'coffee4u' ); ?></label><input id="c4u-reg-email" name="email" type="email" autocomplete="email" required><span class="c4u-field__error" data-error-for="email"></span></div>
					<div class="c4u-auth__row"><label for="c4u-reg-password"><?php esc_html_e( 'Create Password:', 'coffee4u' ); ?></label><input id="c4u-reg-password" name="password" type="password" autocomplete="new-password" minlength="8" required><span class="c4u-field__error" data-error-for="password"></span></div>
					<p class="c4u-form-error" data-c4u-error role="alert"></p>
					<p class="c4u-center"><button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Sign-up', 'coffee4u' ); ?></button></p>
					<p class="c4u-center"><?php esc_html_e( 'Use Promocode', 'coffee4u' ); ?> <strong class="c4u-code">NEWSIGNUP</strong> <?php esc_html_e( 'at check-out', 'coffee4u' ); ?></p>
					<p class="c4u-center c4u-auth__sub"><button type="button" class="c4u-link" data-c4u-auth-view="signin"><?php esc_html_e( 'Already have an account? Sign-in', 'coffee4u' ); ?></button></p>
				</form>
			</div>
		</dialog>
		<?php
	}
);

// "Edit User Profile" frame. The current-password row is shown by JS once the e-mail or password changes.
add_action(
	'wp_footer',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$profile = c4u_profile_view( wp_get_current_user() );
		$fields  = array(
			'name'             => array( __( 'Edit Name:', 'coffee4u' ), 'text', 'name', $profile['name'] ),
			'phone'            => array( __( 'Update Phone No.:', 'coffee4u' ), 'tel', 'tel', $profile['phone'] ),
			'email'            => array( __( 'Update E-mail:', 'coffee4u' ), 'email', 'email', $profile['email'] ),
			'password'         => array( __( 'Change Password:', 'coffee4u' ), 'password', 'new-password', '' ),
			'current_password' => array( __( 'Current Password:', 'coffee4u' ), 'password', 'current-password', '' ),
		);
		?>
		<dialog class="c4u-modal c4u-auth c4u-profile" data-c4u-profile-modal aria-labelledby="c4u-profile-title">
			<button type="button" class="c4u-modal__close" data-c4u-close aria-label="<?php esc_attr_e( 'Close', 'coffee4u' ); ?>">&times;</button>
			<h2 class="c4u-profile__title" id="c4u-profile-title"><?php esc_html_e( 'Edit User Details', 'coffee4u' ); ?></h2>
			<form data-c4u-profile-form data-email="<?php echo esc_attr( $profile['email'] ); ?>" novalidate>
				<?php foreach ( $fields as $key => $field ) : ?>
					<div class="c4u-auth__row"<?php echo 'current_password' === $key ? ' data-c4u-current-password hidden' : ''; ?>>
						<label for="c4u-profile-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label>
						<input id="c4u-profile-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $field[1] ); ?>" autocomplete="<?php echo esc_attr( $field[2] ); ?>" value="<?php echo esc_attr( $field[3] ); ?>"<?php echo 'password' === $key ? ' placeholder="' . esc_attr__( 'Leave blank to keep your password', 'coffee4u' ) . '"' : ''; ?>>
						<span class="c4u-field__error" data-error-for="<?php echo esc_attr( $key ); ?>"></span>
					</div>
				<?php endforeach; ?>
				<p class="c4u-form-error" data-c4u-error role="alert"></p>
				<p class="c4u-center"><button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Save Changes', 'coffee4u' ); ?></button></p>
			</form>
		</dialog>
		<?php
	}
);

// Payment Cards: the saved-card list, and the "Save Payment Card" frame for adding one.
add_action(
	'wp_footer',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		?>
		<dialog class="c4u-modal c4u-cards-modal" data-c4u-cards-modal aria-labelledby="c4u-cards-title">
			<button type="button" class="c4u-modal__close" data-c4u-close aria-label="<?php esc_attr_e( 'Close', 'coffee4u' ); ?>">&times;</button>
			<div data-c4u-cards-view>
				<h2 class="c4u-profile__title" id="c4u-cards-title"><?php esc_html_e( 'Payment Cards', 'coffee4u' ); ?></h2>
				<ul class="c4u-card-list" data-c4u-card-list></ul>
				<p class="c4u-center" data-c4u-cards-empty hidden><?php esc_html_e( 'You have no saved cards yet.', 'coffee4u' ); ?></p>
				<p class="c4u-form-error" data-c4u-error role="alert"></p>
				<p class="c4u-center"><button type="button" class="c4u-button c4u-button--large" data-c4u-add-card><?php esc_html_e( 'Add Card', 'coffee4u' ); ?></button></p>
				<p class="c4u-center" data-c4u-cards-full hidden><?php esc_html_e( 'You can save up to 5 cards. Remove one to add another.', 'coffee4u' ); ?></p>
			</div>
			<form class="c4u-card-form" data-c4u-card-form hidden novalidate>
				<h2 class="c4u-profile__title"><?php esc_html_e( 'Save Payment Card', 'coffee4u' ); ?></h2>
				<div class="c4u-card-form__brands"><?php echo c4u_accepted_cards(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped markup. ?></div>
				<div class="c4u-field c4u-field--full"><input id="c4u-new-card" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226;" required><label for="c4u-new-card"><?php esc_html_e( 'Card Number', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="card_number"></span></div>
				<div class="c4u-field-row">
					<div class="c4u-field"><input id="c4u-new-exp" name="expiry" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="MM/YY" required><label for="c4u-new-exp"><?php esc_html_e( 'Validity Details', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="expiry"></span></div>
					<div class="c4u-field"><input id="c4u-new-cvv" name="cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="&#8226;&#8226;&#8226;" required><label for="c4u-new-cvv"><?php esc_html_e( 'CVV', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="cvv"></span></div>
				</div>
				<div class="c4u-field c4u-field--full"><input id="c4u-new-name" name="name" autocomplete="cc-name" required><label for="c4u-new-name"><?php esc_html_e( 'Name on Card', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="name"></span></div>
				<p class="c4u-form-error" data-c4u-error role="alert"></p>
				<p class="c4u-center"><button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Save Card', 'coffee4u' ); ?></button></p>
				<p class="c4u-center"><button type="button" class="c4u-link" data-c4u-cards-back><?php esc_html_e( 'Back to saved cards', 'coffee4u' ); ?></button></p>
				<p class="c4u-test-hint"><?php esc_html_e( 'Only the card brand, last four digits, expiry and name are kept. The full number and CVV are never stored, and the CVV is asked again at payment.', 'coffee4u' ); ?></p>
			</form>
		</dialog>
		<?php
	}
);

// Favourite Location: pick one open cafe location, or clear it.
add_action(
	'wp_footer',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$view = c4u_favourite_view( get_current_user_id() );
		?>
		<dialog class="c4u-modal c4u-fav-modal" data-c4u-fav-modal aria-labelledby="c4u-fav-title">
			<button type="button" class="c4u-modal__close" data-c4u-close aria-label="<?php esc_attr_e( 'Close', 'coffee4u' ); ?>">&times;</button>
			<h2 class="c4u-profile__title" id="c4u-fav-title"><?php esc_html_e( 'Favourite Location', 'coffee4u' ); ?></h2>
			<p class="c4u-center"><?php esc_html_e( 'Choose your favourite cafe. It is shown first on the home page.', 'coffee4u' ); ?></p>
			<ul class="c4u-location-list c4u-fav-list">
				<?php foreach ( $view['locations'] as $location ) : ?>
					<li>
						<button type="button" class="c4u-location-button" data-c4u-fav-set="<?php echo esc_attr( $location['id'] ); ?>" aria-pressed="<?php echo $location['favourite'] ? 'true' : 'false'; ?>"<?php disabled( ! $location['is_open'] ); ?>><?php echo esc_html( $location['name'] ); ?></button>
						<?php if ( ! $location['is_open'] ) : ?>
							<span class="c4u-location-soon"><?php esc_html_e( 'Opening Shortly', 'coffee4u' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="c4u-form-error" data-c4u-error role="alert"></p>
			<p class="c4u-center"><button type="button" class="c4u-link" data-c4u-fav-clear<?php echo $view['location'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear favourite', 'coffee4u' ); ?></button></p>
		</dialog>
		<?php
	}
);

add_action(
	'wp_footer',
	function () {
		echo '<div class="c4u-toast" data-c4u-toast role="status" aria-live="polite" hidden></div>';
	}
);
