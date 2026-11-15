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
		$user    = wp_get_current_user();
		$account = '<div class="c4u-account">'
			. '<button type="button" class="c4u-nav-button" aria-haspopup="true" aria-expanded="false" data-c4u-toggle="account">' . esc_html__( 'My Profile', 'coffee4u' ) . '</button>'
			. '<div class="c4u-account__menu" hidden>'
			. '<p class="c4u-account__name">' . esc_html( $user->display_name ) . '</p>'
			. '<a href="' . esc_url( c4u_page_url( 'my-orders' ) ) . '">' . esc_html__( 'My Orders', 'coffee4u' ) . '</a>'
			. '<a href="' . esc_url( wp_logout_url( $home ) ) . '">' . esc_html__( 'Log Out', 'coffee4u' ) . '</a>'
			. '</div></div>';
	} else {
		$account = '<button type="button" class="c4u-nav-button" data-c4u-auth="signin">' . esc_html__( 'Sign-in/Register', 'coffee4u' ) . '</button>';
	}

	return '<nav class="c4u-nav" aria-label="' . esc_attr__( 'Main', 'coffee4u' ) . '">'
		. '<button type="button" class="c4u-nav__toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Menu', 'coffee4u' ) . '" data-c4u-toggle="nav"><span></span><span></span><span></span></button>'
		. '<div class="c4u-nav__items">' . $account . $links . '</div></nav>';
}

function c4u_render_location_list() {
	$posts = get_posts(
		array(
			'post_type'      => 'c4u_location',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);
	$out   = '<ul class="c4u-location-list">';
	foreach ( $posts as $post ) {
		$location = c4u_get_location( $post );
		if ( $location['is_open'] ) {
			$out .= '<li><a class="c4u-location-button" href="' . esc_url( $location['url'] ) . '">' . esc_html( $location['name'] ) . '</a></li>';
		} else {
			$out .= '<li><span class="c4u-location-button is-disabled" aria-disabled="true">' . esc_html( $location['name'] ) . '</span><span class="c4u-location-soon">' . esc_html__( 'Opening Shortly', 'coffee4u' ) . '</span></li>';
		}
	}
	return $out . '</ul>';
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

	ob_start();
	?>
	<section class="c4u-page c4u-menu" data-c4u-menu data-location-id="<?php echo esc_attr( $location['id'] ); ?>">
		<?php echo c4u_back_link( $location['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<h1 class="c4u-page__title"><?php echo esc_html( $location['name'] ); ?></h1>
		<p class="c4u-page__subtitle"><?php esc_html_e( 'Take-Out Menu', 'coffee4u' ); ?></p>
		<div class="c4u-group-banner">
			<div><strong><?php esc_html_e( 'Ordering for a Group?', 'coffee4u' ); ?></strong><span><?php esc_html_e( 'Try our new Group Ordering Feature', 'coffee4u' ); ?></span></div>
			<button type="button" class="c4u-button c4u-button--dark" data-c4u-coming-soon><?php esc_html_e( 'Group Order', 'coffee4u' ); ?></button>
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
			<button type="button" class="c4u-button c4u-button--large" data-c4u-add-to-cart><?php esc_html_e( 'Add to Cart', 'coffee4u' ); ?></button>
		</dialog>
	</section>
	<?php
	return ob_get_clean();
}

function c4u_signin_required( $message ) {
	return '<div class="c4u-panel c4u-center"><p>' . esc_html( $message ) . '</p><button type="button" class="c4u-button" data-c4u-auth="signin">' . esc_html__( 'Sign-in/Register', 'coffee4u' ) . '</button></div>';
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
			<input id="c4u-promo-code" name="code" placeholder="<?php esc_attr_e( 'Enter Promo Code', 'coffee4u' ); ?>" value="<?php echo esc_attr( (string) $data['coupon'] ); ?>" autocomplete="off" <?php disabled( (bool) $data['coupon'] ); ?>>
			<button type="submit" class="c4u-button c4u-button--dark" data-applied="<?php echo $data['coupon'] ? '1' : '0'; ?>"><?php echo $data['coupon'] ? esc_html__( 'Applied', 'coffee4u' ) : esc_html__( 'Apply', 'coffee4u' ); ?></button>
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
	$user  = wp_get_current_user();
	$phone = get_user_meta( $user->ID, 'c4u_phone', true );
	ob_start();
	?>
	<form class="c4u-pay-form" data-c4u-pay="<?php echo esc_attr( $order['id'] ); ?>" novalidate>
		<div class="c4u-summary__total"><span><?php esc_html_e( 'Total Amount Payable', 'coffee4u' ); ?></span><strong><?php echo esc_html( c4u_format_money( $order['totals']['total'] ) ); ?></strong></div>
		<div class="c4u-pay-form__head">
			<h2><?php esc_html_e( 'Enter Payment Details', 'coffee4u' ); ?></h2>
			<span class="c4u-cards" aria-label="<?php esc_attr_e( 'Accepted cards: Visa, Mastercard, American Express', 'coffee4u' ); ?>"><span class="c4u-card c4u-card--visa">VISA</span><span class="c4u-card c4u-card--mc"><i></i><i></i></span><span class="c4u-card c4u-card--amex">AMEX</span></span>
		</div>
		<div class="c4u-field c4u-field--full"><input id="c4u-card" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226;" required><label for="c4u-card"><?php esc_html_e( 'Card Number', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="card_number"></span></div>
		<div class="c4u-field-row">
			<div class="c4u-field"><input id="c4u-exp" name="expiry" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="MM/YY" required><label for="c4u-exp"><?php esc_html_e( 'Validity Details', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="expiry"></span></div>
			<div class="c4u-field"><input id="c4u-cvv" name="cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="&#8226;&#8226;&#8226;" required><label for="c4u-cvv"><?php esc_html_e( 'CVV', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="cvv"></span></div>
		</div>
		<div class="c4u-field c4u-field--full"><input id="c4u-name" name="name" autocomplete="cc-name" value="<?php echo esc_attr( $user->display_name ); ?>" required><label for="c4u-name"><?php esc_html_e( 'Name on Card', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="name"></span></div>
		<div class="c4u-field c4u-field--full"><input id="c4u-phone" name="phone" type="tel" autocomplete="tel" value="<?php echo esc_attr( $phone ); ?>" required><label for="c4u-phone"><?php esc_html_e( 'Contact Number', 'coffee4u' ); ?></label><span class="c4u-field__error" data-error-for="phone"></span></div>
		<p class="c4u-form-error" data-c4u-error role="alert"></p>
		<div class="c4u-pay-form__submit">
			<button type="submit" class="c4u-button c4u-button--large"><?php esc_html_e( 'Secure Payment', 'coffee4u' ); ?></button>
			<svg class="c4u-shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 9h8M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.8"/></svg>
		</div>
		<p class="c4u-test-hint"><?php esc_html_e( 'Demo payment: no money is charged and card details are never stored. Use 4242 4242 4242 4242 with any future expiry and CVV. 4000 0000 0000 0002 simulates a declined card.', 'coffee4u' ); ?></p>
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
	$orders = c4u_get_customer_orders( get_current_user_id() );
	if ( ! $orders ) {
		return $out . '<div class="c4u-panel c4u-center"><p>' . esc_html__( 'You have not placed any orders yet.', 'coffee4u' ) . '</p><a class="c4u-button" href="' . esc_url( home_url( '/#locations' ) ) . '">' . esc_html__( 'Order Now', 'coffee4u' ) . '</a></div></section>';
	}
	$out .= '<div class="c4u-panel"><table class="c4u-orders"><thead><tr><th>' . esc_html__( 'Order', 'coffee4u' ) . '</th><th>' . esc_html__( 'Date', 'coffee4u' ) . '</th><th>' . esc_html__( 'Location', 'coffee4u' ) . '</th><th>' . esc_html__( 'Total', 'coffee4u' ) . '</th><th>' . esc_html__( 'Status', 'coffee4u' ) . '</th></tr></thead><tbody>';
	foreach ( $orders as $order ) {
		$url  = 'paid' === $order['status'] ? $order['confirmation_url'] : $order['payment_url'];
		$out .= '<tr><td><a href="' . esc_url( $url ) . '">#' . esc_html( $order['number'] ) . '</a></td><td>' . esc_html( wp_date( get_option( 'date_format' ), $order['created'] ) ) . '</td><td>' . esc_html( $order['location']['name'] ?? '' ) . '</td><td>' . esc_html( c4u_format_money( $order['totals']['total'] ) ) . '</td><td>' . esc_html( c4u_order_status_label( $order['status'] ) ) . '</td></tr>';
	}
	return $out . '</tbody></table></div></section>';
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

add_action(
	'wp_footer',
	function () {
		echo '<div class="c4u-toast" data-c4u-toast role="status" aria-live="polite" hidden></div>';
	}
);
