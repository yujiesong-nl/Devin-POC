/* Coffee4U storefront: navigation, sign-in, menu, cart, checkout and payment. No dependencies. */
( function () {
	'use strict';

	const config = window.c4uConfig || {};
	const AFTER_AUTH_KEY = 'c4uAfterAuth';
	const $ = ( selector, root ) => ( root || document ).querySelector( selector );
	const $$ = ( selector, root ) => Array.from( ( root || document ).querySelectorAll( selector ) );

	async function api( path, options ) {
		const opts = options || {};
		const response = await fetch( config.restUrl + path, {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} );
		const data = await response.json().catch( () => ( {} ) );
		if ( ! response.ok ) {
			const error = new Error( data.message || 'Something went wrong. Please try again.' );
			error.status = response.status;
			error.code = data.code;
			error.fields = data.fields || {};
			throw error;
		}
		return data;
	}

	function toast( message ) {
		const node = $( '[data-c4u-toast]' );
		if ( ! node ) {
			return;
		}
		node.textContent = message;
		node.hidden = false;
		clearTimeout( toast.timer );
		toast.timer = setTimeout( () => ( node.hidden = true ), 2600 );
	}

	function setBusy( button, busy ) {
		if ( button ) {
			button.disabled = busy;
			button.classList.toggle( 'is-busy', busy );
		}
	}

	function showErrors( form, error ) {
		$$( '[data-error-for]', form ).forEach( ( node ) => {
			const message = error && error.fields ? error.fields[ node.dataset.errorFor ] : '';
			node.textContent = message || '';
			const input = form.elements[ node.dataset.errorFor ];
			if ( input ) {
				input.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
			}
		} );
		const summary = $( '[data-c4u-error]', form );
		if ( summary ) {
			summary.textContent = error ? error.message : '';
		}
	}

	function formData( form ) {
		return Object.fromEntries( new FormData( form ).entries() );
	}

	function openDialog( dialog ) {
		if ( dialog && ! dialog.open ) {
			dialog.showModal();
		}
	}

	// Dialogs close on the X button or a click on the backdrop.
	document.addEventListener( 'click', ( event ) => {
		const dialog = event.target.closest( 'dialog' );
		if ( event.target.closest( '[data-c4u-close]' ) || ( dialog && event.target === dialog ) ) {
			dialog.close();
		}
	} );

	/* Navigation ------------------------------------------------------- */
	document.addEventListener( 'click', ( event ) => {
		const toggle = event.target.closest( '[data-c4u-toggle]' );
		$$( '[data-c4u-toggle]' ).forEach( ( other ) => {
			if ( other !== toggle && ! event.target.closest( '.c4u-account__menu' ) ) {
				other.setAttribute( 'aria-expanded', 'false' );
				other.closest( '.c4u-account, .c4u-nav' ).classList.remove( 'is-open' );
				const menu = other.parentElement.querySelector( '.c4u-account__menu' );
				if ( menu && other.dataset.c4uToggle === 'account' ) {
					menu.hidden = true;
				}
			}
		} );
		if ( ! toggle ) {
			return;
		}
		const expanded = toggle.getAttribute( 'aria-expanded' ) !== 'true';
		toggle.setAttribute( 'aria-expanded', String( expanded ) );
		if ( toggle.dataset.c4uToggle === 'account' ) {
			toggle.parentElement.querySelector( '.c4u-account__menu' ).hidden = ! expanded;
		} else {
			toggle.closest( '.c4u-nav' ).classList.toggle( 'is-open', expanded );
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-c4u-coming-soon]' ) ) {
			toast( 'Group ordering is coming soon.' );
		}
	} );

	/* Sign-in / sign-up ----------------------------------------------- */
	const authModal = $( '[data-c4u-auth-modal]' );

	function showAuthView( view ) {
		$$( '[data-view]', authModal ).forEach( ( node ) => ( node.hidden = node.dataset.view !== view ) );
		const first = $( '[data-view="' + view + '"] input', authModal );
		if ( first ) {
			first.focus();
		}
	}

	function openAuth( view, afterAuthUrl ) {
		if ( ! authModal ) {
			return;
		}
		if ( afterAuthUrl ) {
			sessionStorage.setItem( AFTER_AUTH_KEY, afterAuthUrl );
		}
		openDialog( authModal );
		showAuthView( view || 'signin' );
	}

	document.addEventListener( 'click', ( event ) => {
		const opener = event.target.closest( '[data-c4u-auth]' );
		if ( opener ) {
			openAuth( opener.dataset.c4uAuth );
		}
		const switcher = event.target.closest( '[data-c4u-auth-view]' );
		if ( switcher ) {
			showAuthView( switcher.dataset.c4uAuthView );
		}
	} );

	$$( '[data-c4u-auth-form]' ).forEach( ( form ) => {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( '[type="submit"]', form );
			setBusy( button, true );
			showErrors( form, null );
			try {
				await api( 'auth/' + form.dataset.c4uAuthForm, { method: 'POST', body: formData( form ) } );
				const next = sessionStorage.getItem( AFTER_AUTH_KEY );
				sessionStorage.removeItem( AFTER_AUTH_KEY );
				// Reload so the page, header and REST nonce reflect the signed-in customer.
				window.location.assign( next || window.location.href );
			} catch ( error ) {
				showErrors( form, error );
				setBusy( button, false );
			}
		} );
	} );

	/* Menu and cart ---------------------------------------------------- */
	const menu = $( '[data-c4u-menu]' );

	function renderCart( cart ) {
		const list = $( '[data-c4u-cart-items]', menu );
		const count = $( '[data-c4u-cart-count]', menu );
		const confirm = $( '[data-c4u-confirm-order]', menu );
		const sameLocation = cart.location && String( cart.location.id ) === menu.dataset.locationId;
		const items = sameLocation ? cart.items : [];

		count.textContent = items.length ? '(' + cart.count + ')' : '';
		confirm.disabled = ! items.length;
		confirm.dataset.checkoutUrl = cart.checkout_url;
		list.textContent = '';

		if ( ! items.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'c4u-cart__empty';
			empty.textContent = cart.location && ! sameLocation
				? 'Your cart has items from ' + cart.location.name + '.'
				: 'Your cart is empty. Select an item to add it.';
			list.append( empty );
			return;
		}

		const ul = document.createElement( 'ul' );
		ul.className = 'c4u-cart__lines';
		items.forEach( ( line ) => {
			const li = document.createElement( 'li' );
			li.innerHTML =
				'<span class="c4u-cart__name"></span>' +
				'<span class="c4u-qty c4u-qty--small">' +
				'<button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>' +
				'<output></output>' +
				'<button type="button" data-step="1" aria-label="Increase quantity">+</button>' +
				'</span><span class="c4u-cart__price"></span>';
			$( '.c4u-cart__name', li ).textContent = line.name;
			$( 'output', li ).textContent = line.quantity;
			$( '.c4u-cart__price', li ).textContent = line.total_formatted;
			$$( '[data-step]', li ).forEach( ( button ) =>
				button.addEventListener( 'click', () => updateLine( line.product_id, line.quantity + Number( button.dataset.step ) ) )
			);
			ul.append( li );
		} );
		const total = document.createElement( 'p' );
		total.className = 'c4u-cart__total';
		total.innerHTML = '<span>Total</span><strong></strong>';
		$( 'strong', total ).textContent = cart.subtotal;
		list.append( ul, total );
	}

	async function updateLine( productId, quantity ) {
		try {
			renderCart( await api( 'cart/items/' + productId, { method: 'PUT', body: { quantity: Math.max( 0, quantity ) } } ) );
		} catch ( error ) {
			toast( error.message );
		}
	}

	if ( menu ) {
		const modal = $( '[data-c4u-product-modal]', menu );
		const qtyOutput = $( '[data-c4u-qty] output', modal );
		let product = null;
		let quantity = 1;

		api( 'cart' ).then( renderCart ).catch( () => {} );

		menu.addEventListener( 'click', ( event ) => {
			const card = event.target.closest( '[data-c4u-product]' );
			if ( ! card ) {
				return;
			}
			product = JSON.parse( card.dataset.c4uProduct );
			quantity = 1;
			qtyOutput.textContent = '1';
			$( '[data-c4u-error]', modal ).textContent = '';
			$$( '[data-field]', modal ).forEach( ( node ) => {
				if ( node.tagName === 'IMG' ) {
					node.src = product.image;
					node.hidden = ! product.image;
				} else {
					node.textContent = product[ node.dataset.field ] || '';
				}
			} );
			openDialog( modal );
		} );

		$$( '[data-c4u-qty] [data-step]', modal ).forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				quantity = Math.min( 99, Math.max( 1, quantity + Number( button.dataset.step ) ) );
				qtyOutput.textContent = String( quantity );
			} )
		);

		$( '[data-c4u-add-to-cart]', modal ).addEventListener( 'click', async ( event ) => {
			const button = event.currentTarget;
			const body = { product_id: product.id, location_id: Number( menu.dataset.locationId ), quantity };
			setBusy( button, true );
			try {
				let cart;
				try {
					cart = await api( 'cart/items', { method: 'POST', body } );
				} catch ( error ) {
					if ( error.code !== 'c4u_location_mismatch' || ! window.confirm( error.message ) ) {
						throw error;
					}
					cart = await api( 'cart/items', { method: 'POST', body: Object.assign( body, { replace: true } ) } );
				}
				renderCart( cart );
				modal.close();
				toast( quantity + ' x ' + product.name + ' added to your cart.' );
			} catch ( error ) {
				$( '[data-c4u-error]', modal ).textContent = error.message;
			} finally {
				setBusy( button, false );
			}
		} );

		$( '[data-c4u-cart-toggle]', menu ).addEventListener( 'click', ( event ) => {
			const panel = $( '[data-c4u-cart-panel]', menu );
			panel.hidden = ! panel.hidden;
			event.currentTarget.setAttribute( 'aria-expanded', String( ! panel.hidden ) );
		} );

		$( '[data-c4u-confirm-order]', menu ).addEventListener( 'click', ( event ) => {
			const url = event.currentTarget.dataset.checkoutUrl;
			if ( config.loggedIn ) {
				window.location.assign( url );
			} else {
				openAuth( 'signin', url );
			}
		} );
	}

	/* Checkout --------------------------------------------------------- */
	const checkout = $( '[data-c4u-checkout]' );
	const promo = checkout && $( '[data-c4u-promo]', checkout );

	function renderSummary( cart ) {
		const rows = $( '[data-c4u-rows]', checkout );
		rows.textContent = '';
		cart.rows.forEach( ( row ) => {
			const div = document.createElement( 'div' );
			div.innerHTML = '<dt></dt><dd></dd>';
			$( 'dt', div ).textContent = row.label;
			$( 'dd', div ).textContent = row.value;
			rows.append( div );
		} );
		$( '[data-c4u-total]', checkout ).textContent = cart.total;
		const input = promo.elements.code;
		const button = $( 'button', promo );
		input.disabled = Boolean( cart.coupon );
		input.value = cart.coupon || input.value;
		button.dataset.applied = cart.coupon ? '1' : '0';
		button.textContent = cart.coupon ? 'Applied' : 'Apply';
		button.title = cart.coupon ? 'Click to remove the promo code' : '';
		$( '[data-c4u-error]', checkout ).textContent = cart.coupon_error || '';
	}

	if ( promo ) {
		promo.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( 'button', promo );
			const applied = button.dataset.applied === '1';
			setBusy( button, true );
			try {
				const cart = applied
					? await api( 'cart/coupon', { method: 'DELETE' } )
					: await api( 'cart/coupon', { method: 'POST', body: { code: promo.elements.code.value } } );
				if ( applied ) {
					promo.elements.code.value = '';
				}
				renderSummary( cart );
				toast( applied ? 'Promo code removed.' : 'Promo code ' + cart.coupon + ' applied.' );
			} catch ( error ) {
				$( '[data-c4u-error]', checkout ).textContent = error.message;
			} finally {
				setBusy( button, false );
				button.disabled = false;
			}
		} );

		$( '[data-c4u-proceed]', checkout ).addEventListener( 'click', async ( event ) => {
			const button = event.currentTarget;
			setBusy( button, true );
			try {
				const order = await api( 'checkout', { method: 'POST' } );
				window.location.assign( order.payment_url );
			} catch ( error ) {
				$( '[data-c4u-error]', checkout ).textContent = error.message;
				setBusy( button, false );
			}
		} );
	}

	/* Payment ---------------------------------------------------------- */
	const payForm = $( '[data-c4u-pay]' );
	if ( payForm ) {
		const card = payForm.elements.card_number;
		const expiry = payForm.elements.expiry;
		card.addEventListener( 'input', () => {
			card.value = card.value.replace( /\D/g, '' ).slice( 0, 19 ).replace( /(\d{4})(?=\d)/g, '$1 ' );
		} );
		expiry.addEventListener( 'input', ( event ) => {
			let digits = expiry.value.replace( /\D/g, '' ).slice( 0, 4 );
			if ( digits.length >= 3 || ( digits.length === 2 && event.inputType !== 'deleteContentBackward' ) ) {
				digits = digits.slice( 0, 2 ) + '/' + digits.slice( 2 );
			}
			expiry.value = digits;
		} );
		payForm.elements.cvv.addEventListener( 'input', ( event ) => {
			event.target.value = event.target.value.replace( /\D/g, '' ).slice( 0, 4 );
		} );

		payForm.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( '[type="submit"]', payForm );
			setBusy( button, true );
			showErrors( payForm, null );
			try {
				const result = await api( 'orders/' + payForm.dataset.c4uPay + '/pay', { method: 'POST', body: formData( payForm ) } );
				window.location.assign( result.confirmation_url );
			} catch ( error ) {
				showErrors( payForm, error );
				setBusy( button, false );
			}
		} );
	}
} )();
