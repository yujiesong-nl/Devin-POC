/* Coffee4U storefront: navigation, sign-in, menu, cart, group orders, checkout and payment. No dependencies. */
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
			if ( input && input.setAttribute ) {
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
			const container = other.closest( '.c4u-account, .c4u-nav' );
			// Keep the hamburger open while the account menu nested inside it is toggled.
			const isAncestor = toggle && toggle !== other && container.contains( toggle );
			if ( other !== toggle && ! isAncestor && ! event.target.closest( '.c4u-account__menu' ) ) {
				other.setAttribute( 'aria-expanded', 'false' );
				container.classList.remove( 'is-open' );
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

	/* Edit profile ------------------------------------------------------ */
	const profileModal = $( '[data-c4u-profile-modal]' );
	if ( profileModal ) {
		const form = $( '[data-c4u-profile-form]', profileModal );
		const currentRow = $( '[data-c4u-current-password]', form );
		const needsCurrent = () =>
			form.elements.email.value.trim().toLowerCase() !== form.dataset.email.toLowerCase() || form.elements.password.value !== '';
		// Restores the saved details, dropping anything typed but not saved.
		const reset = () => {
			form.reset();
			currentRow.hidden = true;
			showErrors( form, null );
		};

		document.addEventListener( 'click', ( event ) => {
			if ( event.target.closest( '[data-c4u-profile]' ) ) {
				reset();
				openDialog( profileModal );
				form.elements.name.focus();
			}
		} );
		form.addEventListener( 'input', () => ( currentRow.hidden = ! needsCurrent() ) );
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( '[type="submit"]', form );
			setBusy( button, true );
			showErrors( form, null );
			try {
				const profile = await api( 'account/profile', { method: 'POST', body: formData( form ) } );
				[ 'name', 'phone', 'email' ].forEach( ( field ) => ( form.elements[ field ].defaultValue = profile[ field ] ) );
				form.dataset.email = profile.email;
				$$( '[data-c4u-account-name]' ).forEach( ( node ) => ( node.textContent = profile.name ) );
				reset();
				profileModal.close();
				toast( 'Your profile has been updated.' );
			} catch ( error ) {
				showErrors( form, error );
				currentRow.hidden = ! needsCurrent() && ! error.fields.current_password;
			} finally {
				setBusy( button, false );
			}
		} );
	}

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

	/**
	 * Opens the product dialog when a menu card in root is clicked; onAdd( product, quantity ) resolves to close it.
	 */
	function bindProductPicker( root, onAdd ) {
		const modal = $( '[data-c4u-product-modal]', root );
		const qtyOutput = $( '[data-c4u-qty] output', modal );
		let product = null;
		let quantity = 1;

		root.addEventListener( 'click', ( event ) => {
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
			setBusy( button, true );
			try {
				await onAdd( product, quantity );
				modal.close();
			} catch ( error ) {
				$( '[data-c4u-error]', modal ).textContent = error.message;
			} finally {
				setBusy( button, false );
			}
		} );
	}

	if ( menu ) {
		api( 'cart' ).then( renderCart ).catch( () => {} );

		bindProductPicker( menu, async ( product, quantity ) => {
			const body = { product_id: product.id, location_id: Number( menu.dataset.locationId ), quantity };
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
			toast( quantity + ' x ' + product.name + ' added to your cart.' );
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
		// A stored code that no longer applies stays removable, otherwise checkout is blocked.
		input.disabled = Boolean( cart.coupon_code );
		input.value = cart.coupon_code || input.value;
		button.dataset.applied = cart.coupon_code ? '1' : '0';
		button.textContent = cart.promo_label;
		button.title = cart.coupon_code ? 'Click to remove the promo code' : '';
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

	/* Card fields ------------------------------------------------------ */
	function formatCardInputs( form ) {
		const card = form.elements.card_number;
		const expiry = form.elements.expiry;
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
		$$( '[name="cvv"], [name="card_cvv"]', form ).forEach( ( input ) =>
			input.addEventListener( 'input', () => ( input.value = input.value.replace( /\D/g, '' ).slice( 0, 4 ) ) )
		);
	}

	function cardLogo( brand ) {
		const logo = document.createElement( 'span' );
		logo.className = 'c4u-card c4u-card--' + ( { Visa: 'visa', Mastercard: 'mc', 'American Express': 'amex' }[ brand ] || 'visa' );
		if ( brand === 'Mastercard' ) {
			logo.append( document.createElement( 'i' ), document.createElement( 'i' ) );
		} else {
			logo.textContent = brand === 'Visa' ? 'VISA' : 'AMEX';
		}
		return logo;
	}

	/* Payment cards ---------------------------------------------------- */
	const cardsModal = $( '[data-c4u-cards-modal]' );
	if ( cardsModal ) {
		const listView = $( '[data-c4u-cards-view]', cardsModal );
		const cardForm = $( '[data-c4u-card-form]', cardsModal );
		const listError = $( '[data-c4u-error]', listView );
		formatCardInputs( cardForm );

		const showList = () => {
			listView.hidden = false;
			cardForm.hidden = true;
		};

		const renderCards = ( data ) => {
			const list = $( '[data-c4u-card-list]', cardsModal );
			list.textContent = '';
			data.cards.forEach( ( card ) => {
				const item = document.createElement( 'li' );
				item.className = 'c4u-card-list__item' + ( card.expired ? ' is-expired' : '' );
				const text = document.createElement( 'span' );
				text.className = 'c4u-card-list__text';
				const number = document.createElement( 'strong' );
				number.textContent = card.brand + ' \u2022\u2022\u2022\u2022 ' + card.last4;
				const meta = document.createElement( 'span' );
				meta.textContent = ( card.expired ? 'Expired ' : 'Expires ' ) + card.expiry + ' \u00b7 ' + card.name;
				text.append( number, meta );
				const actions = document.createElement( 'span' );
				actions.className = 'c4u-card-list__actions';
				if ( card.default ) {
					const tag = document.createElement( 'span' );
					tag.className = 'c4u-tag';
					tag.textContent = 'Default';
					actions.append( tag );
				} else {
					const makeDefault = document.createElement( 'button' );
					makeDefault.type = 'button';
					makeDefault.className = 'c4u-link';
					makeDefault.textContent = 'Make default';
					makeDefault.dataset.c4uCardDefault = card.id;
					actions.append( makeDefault );
				}
				const remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'c4u-link';
				remove.textContent = 'Remove';
				remove.dataset.c4uCardRemove = card.id;
				remove.setAttribute( 'aria-label', 'Remove card ending ' + card.last4 );
				actions.append( remove );
				item.append( cardLogo( card.brand ), text, actions );
				list.append( item );
			} );
			$( '[data-c4u-cards-empty]', cardsModal ).hidden = data.cards.length > 0;
			$( '[data-c4u-add-card]', cardsModal ).hidden = ! data.can_add;
			$( '[data-c4u-cards-full]', cardsModal ).hidden = data.can_add;
		};

		const run = async ( request ) => {
			listError.textContent = '';
			try {
				renderCards( await request );
			} catch ( error ) {
				listError.textContent = error.message;
			}
		};

		document.addEventListener( 'click', ( event ) => {
			if ( event.target.closest( '[data-c4u-cards]' ) ) {
				showList();
				openDialog( cardsModal );
				run( api( 'account/cards' ) );
			}
		} );

		cardsModal.addEventListener( 'click', ( event ) => {
			const target = event.target.closest( 'button' );
			if ( ! target ) {
				return;
			}
			if ( target.dataset.c4uCardRemove && window.confirm( 'Remove this card?' ) ) {
				run( api( 'account/cards/' + target.dataset.c4uCardRemove, { method: 'DELETE' } ) );
			} else if ( target.dataset.c4uCardDefault ) {
				run( api( 'account/cards/' + target.dataset.c4uCardDefault + '/default', { method: 'POST' } ) );
			} else if ( target.matches( '[data-c4u-add-card]' ) ) {
				cardForm.reset();
				showErrors( cardForm, null );
				listView.hidden = true;
				cardForm.hidden = false;
				cardForm.elements.card_number.focus();
			} else if ( target.matches( '[data-c4u-cards-back]' ) ) {
				showList();
			}
		} );

		cardForm.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( '[type="submit"]', cardForm );
			setBusy( button, true );
			showErrors( cardForm, null );
			try {
				renderCards( await api( 'account/cards', { method: 'POST', body: formData( cardForm ) } ) );
				cardForm.reset();
				showList();
				toast( 'Your card has been saved.' );
			} catch ( error ) {
				showErrors( cardForm, error );
			} finally {
				setBusy( button, false );
			}
		} );
	}

	/* Favourite location ------------------------------------------------ */
	const favModal = $( '[data-c4u-fav-modal]' );
	if ( favModal ) {
		const favError = $( '[data-c4u-error]', favModal );
		const STAR = '\u2605 ';

		// Reflects the saved favourite in the menu, the dialog, the location page and the home page list.
		const applyFavourite = ( data ) => {
			const fav = data.location;
			const name = fav ? fav.name : 'Not set';
			$$( '[data-c4u-fav-name]' ).forEach( ( node ) => ( node.textContent = name ) );
			$$( '[data-c4u-fav-link]' ).forEach( ( link ) => {
				link.textContent = name;
				if ( fav ) {
					link.href = fav.menu_url;
				} else {
					link.removeAttribute( 'href' );
				}
			} );
			$$( '[data-c4u-fav-set]', favModal ).forEach( ( button ) =>
				button.setAttribute( 'aria-pressed', String( Boolean( fav ) && Number( button.dataset.c4uFavSet ) === fav.id ) )
			);
			$( '[data-c4u-fav-clear]', favModal ).hidden = ! fav;
			$$( '[data-c4u-fav-toggle]' ).forEach( ( toggle ) => {
				const pressed = Boolean( fav ) && Number( toggle.dataset.c4uFavToggle ) === fav.id;
				toggle.setAttribute( 'aria-pressed', String( pressed ) );
				toggle.textContent = pressed ? STAR + 'My Favourite' : '\u2606 Set as Favourite';
			} );
			$$( '[data-c4u-location-list]' ).forEach( ( list ) => {
				const items = $$( 'li', list );
				items.forEach( ( item ) => {
					const tag = $( '[data-c4u-fav-tag]', item );
					if ( tag ) {
						tag.hidden = ! fav || Number( item.dataset.locationId ) !== fav.id;
					}
				} );
				const rank = ( item ) => ( fav && Number( item.dataset.locationId ) === fav.id ? -1 : Number( item.dataset.order ) );
				items.sort( ( a, b ) => rank( a ) - rank( b ) ).forEach( ( item ) => list.append( item ) );
			} );
		};

		// locationId > 0 sets it; < 0 clears it only if -locationId is still the favourite; 0 clears it.
		const saveFavourite = async ( locationId, button ) => {
			favError.textContent = '';
			setBusy( button, true );
			try {
				const data = locationId > 0
					? await api( 'account/favourite-location', { method: 'POST', body: { location_id: locationId } } )
					: await api( 'account/favourite-location', { method: 'DELETE', body: locationId ? { location_id: -locationId } : undefined } );
				applyFavourite( data );
				toast( data.location ? data.location.name + ' is now your favourite location.' : 'Your favourite location has been cleared.' );
				return true;
			} catch ( error ) {
				favError.textContent = error.message;
				return false;
			} finally {
				setBusy( button, false );
			}
		};

		document.addEventListener( 'click', async ( event ) => {
			if ( event.target.closest( '[data-c4u-favourite]' ) ) {
				favError.textContent = '';
				openDialog( favModal );
				return;
			}
			const toggle = event.target.closest( '[data-c4u-fav-toggle]' );
			if ( toggle ) {
				const pressed = toggle.getAttribute( 'aria-pressed' ) === 'true';
				const id = Number( toggle.dataset.c4uFavToggle );
				if ( ! ( await saveFavourite( pressed ? -id : id, toggle ) ) ) {
					toast( favError.textContent );
				}
			}
		} );

		favModal.addEventListener( 'click', ( event ) => {
			const pick = event.target.closest( '[data-c4u-fav-set]' );
			if ( pick && pick.getAttribute( 'aria-pressed' ) !== 'true' ) {
				saveFavourite( Number( pick.dataset.c4uFavSet ), pick );
			} else if ( event.target.closest( '[data-c4u-fav-clear]' ) ) {
				saveFavourite( 0, event.target.closest( '[data-c4u-fav-clear]' ) );
			}
		} );
	}

	/* Payment ---------------------------------------------------------- */
	const payForm = $( '[data-c4u-pay]' );
	if ( payForm ) {
		formatCardInputs( payForm );

		// A saved card needs only its CVV; disabled fields are left out of the request.
		const newCard = $( '[data-c4u-new-card]', payForm );
		const savedCvv = $( '[data-c4u-saved-cvv]', payForm );
		const toggleCard = () => {
			const choice = $( '[name="card_id"]:checked', payForm );
			const useSaved = Boolean( choice && choice.value );
			newCard.hidden = useSaved;
			$$( 'input', newCard ).forEach( ( input ) => ( input.disabled = useSaved ) );
			if ( savedCvv ) {
				savedCvv.hidden = ! useSaved;
				savedCvv.querySelector( 'input' ).disabled = ! useSaved;
				savedCvv.querySelector( 'input' ).maxLength = useSaved && choice.dataset.brand === 'American Express' ? 4 : 3;
			}
		};
		$$( '[name="card_id"]', payForm ).forEach( ( radio ) =>
			radio.addEventListener( 'change', () => {
				showErrors( payForm, null );
				toggleCard();
			} )
		);
		toggleCard();

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

	/* Group ordering --------------------------------------------------- */
	const SHARE_TEXT = 'Join my Coffee4U group order and pick what you would like: ';
	const money = ( cents ) => '$' + ( cents / 100 ).toFixed( 2 );

	function timeLeft( seconds ) {
		if ( seconds >= 60 ) {
			const minutes = Math.ceil( seconds / 60 );
			return minutes + ( minutes === 1 ? ' minute' : ' minutes' ) + ' left';
		}
		return seconds + ( seconds === 1 ? ' second' : ' seconds' ) + ' left';
	}

	// Returns a setter taking the server's seconds_left; onTick( seconds ) then runs every second.
	function countdown( onTick ) {
		let deadline = Date.now();
		const tick = () => onTick( Math.max( 0, Math.round( ( deadline - Date.now() ) / 1000 ) ) );
		setInterval( tick, 1000 );
		return ( secondsLeft ) => {
			deadline = Date.now() + secondsLeft * 1000;
			tick();
		};
	}

	function shareUrl( network, link ) {
		const text = encodeURIComponent( SHARE_TEXT + link );
		const url = encodeURIComponent( link );
		return {
			sms: 'sms:?&body=' + text,
			whatsapp: 'https://wa.me/?text=' + text,
			facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
			email: 'mailto:?subject=' + encodeURIComponent( 'Coffee4U group order' ) + '&body=' + text,
			teams: 'https://teams.microsoft.com/share?href=' + url + '&msgText=' + encodeURIComponent( SHARE_TEXT.trim() ),
		}[ network ];
	}

	async function copyText( text ) {
		try {
			await navigator.clipboard.writeText( text );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	function bindShare( root, getLink ) {
		root.addEventListener( 'click', async ( event ) => {
			const copy = event.target.closest( '[data-c4u-group-copy]' );
			const button = event.target.closest( '[data-c4u-share]' );
			const link = getLink();
			if ( ! link || ! ( copy || button ) ) {
				return;
			}
			if ( copy ) {
				$( '[data-c4u-group-link]', root ).select();
				toast( ( await copyText( link ) ) ? 'Invite link copied.' : 'Select the link and copy it.' );
				return;
			}
			const direct = shareUrl( button.dataset.c4uShare, link );
			if ( direct ) {
				if ( direct.startsWith( 'https:' ) ) {
					window.open( direct, '_blank', 'noopener' );
				} else {
					window.location.href = direct;
				}
				return;
			}
			// Messenger, Instagram and Slack have no web share link: use the device share sheet or copy the link.
			if ( navigator.share ) {
				try {
					await navigator.share( { title: 'Coffee4U group order', text: SHARE_TEXT, url: link } );
					return;
				} catch ( error ) {
					if ( error.name === 'AbortError' ) {
						return;
					}
				}
			}
			toast( ( ( await copyText( link ) ) ? 'Link copied. Paste it in ' : 'Copy the link and paste it in ' ) + button.title + '.' );
		} );
	}

	const groupSetup = $( '[data-c4u-group-setup]' );
	if ( groupSetup ) {
		let link = '';
		const limit = groupSetup.elements.limit;
		bindShare( groupSetup, () => link );

		$$( '[data-c4u-stepper]', groupSetup ).forEach( ( stepper ) => {
			const input = $( 'input', stepper );
			$$( '[data-step]', stepper ).forEach( ( button ) =>
				button.addEventListener( 'click', () => {
					const value = ( Number( input.value ) || 0 ) + Number( button.dataset.step );
					input.value = String( Math.min( Number( input.max ), Math.max( Number( input.min ), value ) ) );
				} )
			);
		} );

		$$( '[name="has_limit"]', groupSetup ).forEach( ( radio ) =>
			radio.addEventListener( 'change', () => {
				limit.disabled = ! groupSetup.elements.has_limit.value;
				if ( ! limit.disabled ) {
					limit.focus();
				}
			} )
		);

		groupSetup.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const button = $( '[type="submit"]', groupSetup );
			const data = formData( groupSetup );
			setBusy( button, true );
			showErrors( groupSetup, null );
			try {
				const group = await api( 'groups', {
					method: 'POST',
					body: {
						location_id: Number( groupSetup.dataset.locationId ),
						size: Number( data.size ),
						minutes: Number( data.minutes ),
						has_limit: Boolean( data.has_limit ),
						limit: Number( data.limit || 0 ),
					},
				} );
				link = group.join_url;
				$( '[data-c4u-group-link]', groupSetup ).value = link;
				$( '.c4u-group-share__link', groupSetup ).hidden = false;
				$$( '[data-c4u-share]', groupSetup ).forEach( ( share ) => ( share.disabled = false ) );
				$$( '.c4u-group-setup__row input, .c4u-group-setup__row button', groupSetup ).forEach( ( field ) => ( field.disabled = true ) );
				const track = $( '[data-c4u-group-track]', groupSetup );
				$( 'a', track ).href = group.status_url;
				track.hidden = false;
				button.classList.remove( 'is-busy' );
				button.textContent = 'Link Generated';
				toast( 'Your invite link is ready. Share it with your group.' );
			} catch ( error ) {
				showErrors( groupSetup, error );
				setBusy( button, false );
			}
		} );
	}

	const groupStatus = $( '[data-c4u-group-status]' );
	if ( groupStatus ) {
		const STATE_TEXT = { full: 'All spots are filled', expired: 'Time is up', confirmed: 'Added to your cart', ordered: 'Ordered and paid' };
		const error = $( '[data-c4u-error]', groupStatus );
		let view = JSON.parse( groupStatus.dataset.c4uGroupStatus );
		let editing = false;
		const setTimer = countdown( ( seconds ) => {
			const open = view.state === 'open' && seconds > 0;
			$( '[data-c4u-group-left]', groupStatus ).textContent = open ? timeLeft( seconds ) : STATE_TEXT[ view.state === 'open' ? 'expired' : view.state ];
		} );
		bindShare( groupStatus.parentElement, () => view.join_url );

		const render = ( next ) => {
			view = next;
			const rows = $( '[data-c4u-group-rows]', groupStatus );
			rows.textContent = '';
			for ( let i = 0; i < Math.max( view.size, view.responses.length ); i++ ) {
				const response = view.responses[ i ];
				const tr = document.createElement( 'tr' );
				tr.innerHTML = '<td></td><td></td><td></td><td class="c4u-group-table__edit"></td>';
				tr.cells[ 0 ].textContent = String( i + 1 );
				if ( response ) {
					const name = document.createElement( 'strong' );
					const items = document.createElement( 'span' );
					const remove = document.createElement( 'button' );
					name.textContent = response.name;
					items.textContent = response.summary;
					remove.type = 'button';
					remove.className = 'c4u-link';
					remove.textContent = 'Remove';
					remove.dataset.c4uRemove = response.id;
					remove.setAttribute( 'aria-label', 'Remove the order from ' + response.name );
					tr.cells[ 1 ].append( name, items );
					tr.cells[ 2 ].textContent = response.subtotal;
					tr.cells[ 3 ].append( remove );
				} else {
					tr.className = 'is-empty';
					tr.cells[ 1 ].textContent = 'Waiting for a response';
					tr.cells[ 2 ].textContent = '\u2014';
				}
				rows.append( tr );
			}
			$( '[data-c4u-group-total]', groupStatus ).textContent = view.total;
			$( '[data-c4u-group-count]', groupStatus ).textContent = view.count + ' of ' + view.size;
			const ordered = view.state === 'ordered';
			editing = editing && ! ordered;
			groupStatus.classList.toggle( 'is-editing', editing );
			$( '[data-c4u-group-edit-panel]', groupStatus ).hidden = ! editing;
			$( '[data-c4u-group-edit]', groupStatus ).hidden = ordered;
			$( '[data-c4u-group-edit]', groupStatus ).setAttribute( 'aria-expanded', String( editing ) );
			$( '[data-c4u-group-edit]', groupStatus ).textContent = editing ? 'Done Editing' : 'Edit Order';
			$( '[data-c4u-group-confirm]', groupStatus ).disabled = ordered || ! view.count;
			setTimer( view.seconds_left );
		};

		const act = async ( button, request ) => {
			setBusy( button, true );
			error.textContent = '';
			try {
				render( await request() );
			} catch ( failure ) {
				error.textContent = failure.message;
			} finally {
				setBusy( button, false );
			}
		};

		render( view );
		setInterval( () => {
			// Participants can still edit their orders while the group is full.
			if ( [ 'open', 'full' ].includes( view.state ) && ! editing && ! document.hidden ) {
				api( 'groups/' + view.id ).then( render ).catch( () => {} );
			}
		}, 5000 );

		groupStatus.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest( '[data-c4u-remove]' );
			if ( remove && window.confirm( remove.getAttribute( 'aria-label' ) + '?' ) ) {
				act( remove, () => api( 'groups/' + view.id + '/responses/' + remove.dataset.c4uRemove, { method: 'DELETE' } ) );
			}
		} );
		$( '[data-c4u-group-edit]', groupStatus ).addEventListener( 'click', () => {
			editing = ! editing;
			render( view );
		} );
		$( '[data-c4u-group-extend]', groupStatus ).addEventListener( 'click', ( event ) =>
			act( event.currentTarget, () => api( 'groups/' + view.id + '/extend', { method: 'POST', body: { minutes: 5 } } ) )
		);
		$( '[data-c4u-group-confirm]', groupStatus ).addEventListener( 'click', async ( event ) => {
			const button = event.currentTarget;
			const path = 'groups/' + view.id + '/confirm';
			setBusy( button, true );
			error.textContent = '';
			try {
				try {
					await api( path, { method: 'POST' } );
				} catch ( failure ) {
					if ( failure.code !== 'c4u_cart_not_empty' || ! window.confirm( failure.message ) ) {
						throw failure;
					}
					await api( path, { method: 'POST', body: { replace: true } } );
				}
				window.location.assign( view.checkout_url );
			} catch ( failure ) {
				error.textContent = failure.message;
				setBusy( button, false );
			}
		} );
	}

	const groupJoin = $( '[data-c4u-group-join]' );
	if ( groupJoin ) {
		const token = groupJoin.dataset.c4uGroupJoin;
		const storageKey = 'c4uGroup_' + token;
		const form = $( '[data-c4u-join-form]', groupJoin );
		const submit = $( '[data-c4u-join-submit]', form );
		const products = {};
		const picks = {};
		let view = JSON.parse( groupJoin.dataset.c4uGroupView );
		let secondsLeft = view.seconds_left;

		$$( '[data-c4u-product]', groupJoin ).forEach( ( card ) => {
			const product = JSON.parse( card.dataset.c4uProduct );
			products[ product.id ] = product;
		} );

		const subtotal = () => Object.keys( picks ).reduce( ( sum, id ) => sum + products[ id ].price * picks[ id ], 0 );
		const isOpen = () => secondsLeft > 0 && ( view.state === 'open' || ( view.state === 'full' && view.mine ) );

		const renderPicks = () => {
			const list = $( '[data-c4u-cart-items]', form );
			const ids = Object.keys( picks );
			list.textContent = '';
			if ( ! ids.length ) {
				const empty = document.createElement( 'p' );
				empty.className = 'c4u-cart__empty';
				empty.textContent = 'Select an item to add it to your order.';
				list.append( empty );
			} else {
				const ul = document.createElement( 'ul' );
				ul.className = 'c4u-cart__lines';
				ids.forEach( ( id ) => {
					const li = document.createElement( 'li' );
					li.innerHTML =
						'<span class="c4u-cart__name"></span>' +
						'<span class="c4u-qty c4u-qty--small">' +
						'<button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>' +
						'<output></output>' +
						'<button type="button" data-step="1" aria-label="Increase quantity">+</button>' +
						'</span><span class="c4u-cart__price"></span>';
					$( '.c4u-cart__name', li ).textContent = products[ id ].name;
					$( 'output', li ).textContent = picks[ id ];
					$( '.c4u-cart__price', li ).textContent = money( products[ id ].price * picks[ id ] );
					$$( '[data-step]', li ).forEach( ( button ) =>
						button.addEventListener( 'click', () => {
							picks[ id ] = Math.min( 99, picks[ id ] + Number( button.dataset.step ) );
							if ( picks[ id ] < 1 ) {
								delete picks[ id ];
							}
							renderPicks();
						} )
					);
					ul.append( li );
				} );
				const total = document.createElement( 'p' );
				total.className = 'c4u-cart__total';
				total.innerHTML = '<span>Total</span><strong></strong>';
				$( 'strong', total ).textContent = money( subtotal() );
				list.append( ul, total );
			}
			const over = view.limit && subtotal() > view.limit;
			const error = $( '[data-c4u-error]', form );
			// Only clear the limit message this function wrote, not a server error.
			if ( over || error.dataset.limit ) {
				error.textContent = over ? 'The limit is ' + money( view.limit ) + ' per person.' : '';
			}
			error.dataset.limit = over ? '1' : '';
			submit.disabled = ! ids.length || over || ! isOpen();
		};

		const renderState = () => {
			const open = isOpen();
			const meta = [ open ? timeLeft( secondsLeft ) : 'Closed' ];
			if ( view.limit ) {
				meta.push( 'Limit ' + money( view.limit ) + ' per person' );
			}
			if ( view.state === 'open' ) {
				meta.push( view.spots_left + ( view.spots_left === 1 ? ' spot' : ' spots' ) + ' left' );
			}
			$( '[data-c4u-join-title]', groupJoin ).textContent = view.organizer ? 'You are invited to ' + view.organizer + "'s group order" : 'You are invited to a group order';
			$( '[data-c4u-join-meta]', groupJoin ).textContent = meta.join( ' \u00b7 ' );
			const closed = $( '[data-c4u-join-closed]', groupJoin );
			closed.hidden = open;
			$( '[data-c4u-join-open]', groupJoin ).hidden = ! open;
			if ( ! open ) {
				const reason = view.state === 'full' ? 'This group order is full.' : 'This group order is no longer taking orders.';
				$( 'p', closed ).textContent = view.mine ? reason + ' Your order is in, thank you!' : reason;
			}
			submit.disabled = submit.disabled || ! open;
		};

		const applyView = ( next ) => {
			view = next;
			secondsLeft = view.seconds_left;
			if ( view.mine ) {
				form.elements.name.value = view.mine.name;
				Object.keys( picks ).forEach( ( id ) => delete picks[ id ] );
				Object.keys( view.mine.items ).forEach( ( id ) => {
					if ( products[ id ] ) {
						picks[ id ] = view.mine.items[ id ];
					}
				} );
				$( '[data-c4u-join-done]', form ).hidden = false;
				submit.textContent = 'Update My Order';
			}
			renderPicks();
			renderState();
		};

		const key = localStorage.getItem( storageKey );
		applyView( view );
		if ( key ) {
			api( 'group-join/' + token + '?key=' + encodeURIComponent( key ) ).then( applyView ).catch( () => {} );
		}
		setInterval( () => {
			secondsLeft = Math.max( 0, secondsLeft - 1 );
			renderState();
		}, 1000 );
		// Picks up timer extensions and spots freed by the organizer without touching unsaved picks.
		setInterval( () => {
			if ( document.hidden || view.state === 'ordered' ) {
				return;
			}
			const saved = localStorage.getItem( storageKey );
			api( 'group-join/' + token + ( saved ? '?key=' + encodeURIComponent( saved ) : '' ) )
				.then( ( next ) => {
					view = next;
					secondsLeft = next.seconds_left;
					renderPicks();
					renderState();
				} )
				.catch( () => {} );
		}, 10000 );

		bindProductPicker( groupJoin, async ( product, quantity ) => {
			picks[ product.id ] = Math.min( 99, ( picks[ product.id ] || 0 ) + quantity );
			renderPicks();
			toast( quantity + ' x ' + product.name + ' added to your order.' );
		} );

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			setBusy( submit, true );
			showErrors( form, null );
			try {
				const result = await api( 'group-join/' + token, {
					method: 'POST',
					body: { name: form.elements.name.value, items: picks, key: localStorage.getItem( storageKey ) || '' },
				} );
				localStorage.setItem( storageKey, result.key );
				applyView( result );
				toast( 'Your order has been added to the group order.' );
			} catch ( error ) {
				showErrors( form, error );
				if ( error.code === 'c4u_group_closed' ) {
					api( 'group-join/' + token ).then( applyView ).catch( () => {} );
				}
			} finally {
				submit.classList.remove( 'is-busy' );
				renderPicks();
				renderState();
			}
		} );
	}
} )();
