/* Turns any .c4u-carousel group of image blocks into an accessible auto-advancing slider. */
( function () {
	'use strict';
	document.querySelectorAll( '.c4u-carousel' ).forEach( function ( root ) {
		const slides = Array.from( root.querySelectorAll( ':scope > .wp-block-image' ) );
		if ( slides.length < 2 ) {
			return;
		}
		let index = 0;
		let timer;
		root.setAttribute( 'role', 'region' );
		root.setAttribute( 'aria-roledescription', 'carousel' );
		root.setAttribute( 'aria-label', 'Featured' );

		const dots = document.createElement( 'div' );
		dots.className = 'c4u-carousel__dots';
		const buttons = slides.map( function ( slide, i ) {
			slide.setAttribute( 'aria-roledescription', 'slide' );
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.setAttribute( 'aria-label', 'Show slide ' + ( i + 1 ) );
			dot.addEventListener( 'click', function () {
				go( i );
			} );
			dots.append( dot );
			return dot;
		} );

		[ [ 'prev', 'Previous slide', -1 ], [ 'next', 'Next slide', 1 ] ].forEach( function ( def ) {
			const arrow = document.createElement( 'button' );
			arrow.type = 'button';
			arrow.className = 'c4u-carousel__arrow c4u-carousel__arrow--' + def[ 0 ];
			arrow.setAttribute( 'aria-label', def[ 1 ] );
			arrow.addEventListener( 'click', function () {
				go( index + def[ 2 ] );
			} );
			root.append( arrow );
		} );
		root.append( dots );

		function go( next ) {
			index = ( next + slides.length ) % slides.length;
			slides.forEach( function ( slide, i ) {
				slide.classList.toggle( 'is-active', i === index );
				slide.setAttribute( 'aria-hidden', String( i !== index ) );
				buttons[ i ].setAttribute( 'aria-current', String( i === index ) );
			} );
			restart();
		}

		function restart() {
			clearInterval( timer );
			if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				timer = setInterval( function () {
					go( index + 1 );
				}, 5000 );
			}
		}

		root.addEventListener( 'mouseenter', function () {
			clearInterval( timer );
		} );
		root.addEventListener( 'mouseleave', restart );
		root.classList.add( 'is-ready' );
		go( 0 );
	} );
} )();
