/**
 * Scroll Resize - Frontend Script
 *
 * Scales down sticky elements as the user scrolls past the stick point.
 * Add the class 'has-scroll-resize' to a sticky element to trigger.
 *
 * @package OlliePro
 */

( function () {
	'use strict';

	var MOBILE_BREAKPOINT = 781;
	var SCROLL_DISTANCE = 200;
	var MIN_SCALE = 0.85;

	var elements = [];

	function isMobile() {
		return window.innerWidth <= MOBILE_BREAKPOINT;
	}

	function init() {
		var els = document.querySelectorAll( '.has-scroll-resize' );

		if ( ! els.length ) {
			return;
		}

		var initialScrollY = window.scrollY || window.pageYOffset;

		els.forEach( function ( el ) {
			var stickyOffset = getComputedStyle( el ).getPropertyValue( '--sticky-top-offset' );
			var stickyTop = stickyOffset ? parseFloat( stickyOffset ) : ( parseFloat( getComputedStyle( el ).top ) || 0 );

			var rect = el.getBoundingClientRect();
			var stickPoint = rect.top + initialScrollY - stickyTop;

			elements.push( {
				el: el,
				stickPoint: stickPoint,
			} );

			el.style.transformOrigin = 'top left';
		} );

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}

	function onScroll() {
		if ( isMobile() ) {
			elements.forEach( function ( item ) {
				item.el.style.transform = '';
			} );
			return;
		}

		var scrollY = window.scrollY || window.pageYOffset;

		elements.forEach( function ( item ) {
			var scrolledPast = scrollY - item.stickPoint;

			if ( scrolledPast > 0 ) {
				var progress = Math.min( scrolledPast / SCROLL_DISTANCE, 1 );
				var scale = 1 - ( 1 - MIN_SCALE ) * progress;
				item.el.style.transform = 'scale(' + scale + ')';
			} else {
				item.el.style.transform = '';
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
