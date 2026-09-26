/**
 * Video Modal - Frontend JavaScript
 *
 * Handles click events on video modal triggers and modal functionality.
 */

( function() {
	'use strict';

	const overlay = document.getElementById( 'ollie-video-modal-overlay' );
	const modal = overlay?.querySelector( '.ollie-video-modal' );
	const content = document.getElementById( 'ollie-video-modal-content' );
	const closeBtn = overlay?.querySelector( '.ollie-video-modal__close' );

	// Store the element that triggered the modal for focus return
	let triggerElement = null;

	if ( ! overlay || ! content || ! modal ) {
		return;
	}

	/**
	 * Get all focusable elements within the modal
	 *
	 * @return {Array} Array of focusable elements
	 */
	function getFocusableElements() {
		const focusableSelectors = [
			'button',
			'[href]',
			'input:not([disabled])',
			'select:not([disabled])',
			'textarea:not([disabled])',
			'[tabindex]:not([tabindex="-1"])',
			'iframe',
			'video',
		].join( ', ' );

		return Array.from( modal.querySelectorAll( focusableSelectors ) );
	}

	/**
	 * Handle Tab key to trap focus within modal
	 *
	 * @param {KeyboardEvent} event The keyboard event
	 */
	function handleTabKey( event ) {
		if ( event.key !== 'Tab' ) {
			return;
		}

		const focusableElements = getFocusableElements();

		if ( focusableElements.length === 0 ) {
			event.preventDefault();
			return;
		}

		const firstElement = focusableElements[ 0 ];
		const lastElement = focusableElements[ focusableElements.length - 1 ];

		// Shift+Tab on first element -> go to last
		if ( event.shiftKey && document.activeElement === firstElement ) {
			event.preventDefault();
			lastElement.focus();
		}
		// Tab on last element -> go to first
		else if ( ! event.shiftKey && document.activeElement === lastElement ) {
			event.preventDefault();
			firstElement.focus();
		}
	}

	/**
	 * Open the video modal
	 *
	 * @param {HTMLElement} card The video card element
	 */
	function openModal( card ) {
		// Store the trigger element for focus return
		triggerElement = card;

		const source = card.dataset.videoSource;
		const autoplay = card.dataset.videoAutoplay === 'true';
		const startTime = parseInt( card.dataset.videoStart, 10 ) || 0;

		if ( source === 'youtube' ) {
			const youtubeId = card.dataset.youtubeId;
			const userEmbedUrl = card.dataset.embedUrl; // If user entered embed URL directly

			if ( ! youtubeId ) {
				return;
			}

			let embedUrl;

			// If user provided an embed URL, use it directly (it works!)
			if ( userEmbedUrl ) {
				embedUrl = userEmbedUrl;
				// Append autoplay if enabled and not already in URL
				if ( autoplay && ! embedUrl.includes( 'autoplay=' ) ) {
					embedUrl += ( embedUrl.includes( '?' ) ? '&' : '?' ) + 'autoplay=1';
				}
			} else {
				// Build embed URL from watch URL parameters
				const params = [ 'rel=0' ];
				if ( startTime > 0 ) {
					params.push( `start=${ Math.floor( startTime ) }` );
				}
				if ( autoplay ) {
					params.push( 'autoplay=1' );
				}
				embedUrl = `https://www.youtube.com/embed/${ youtubeId }?${ params.join( '&' ) }`;
			}

			content.innerHTML = `<iframe
				src="${ embedUrl }"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				allowfullscreen
				title="Video player"
			></iframe>`;
		} else if ( source === 'library' ) {
			const videoUrl = card.dataset.videoUrl;
			if ( ! videoUrl ) {
				return;
			}

			const autoplayAttr = autoplay ? 'autoplay' : '';
			content.innerHTML = `<video
				src="${ videoUrl }"
				controls
				${ autoplayAttr }
				playsinline
				title="Video player"
			></video>`;
		}

		overlay.classList.add( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';

		// Add focus trap listener
		modal.addEventListener( 'keydown', handleTabKey );

		// Focus the close button for accessibility
		setTimeout( () => {
			closeBtn?.focus();
		}, 100 );
	}

	/**
	 * Close the video modal
	 */
	function closeModal() {
		overlay.classList.remove( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';

		// Remove focus trap listener
		modal.removeEventListener( 'keydown', handleTabKey );

		// Return focus to the element that triggered the modal
		if ( triggerElement ) {
			// Use setTimeout to ensure focus happens after the modal transition
			setTimeout( () => {
				triggerElement.focus();
				triggerElement = null;
			}, 100 );
		}

		// Stop any playing video by clearing content
		setTimeout( () => {
			content.innerHTML = '';
		}, 300 );
	}

	/**
	 * Check if the card itself is a button/link element
	 *
	 * @param {HTMLElement} card The video card element
	 * @return {boolean} True if the card is a button or link
	 */
	function isCardInteractive( card ) {
		const tagName = card.tagName.toLowerCase();
		return tagName === 'a' || tagName === 'button' || card.classList.contains( 'wp-block-button__link' );
	}

	/**
	 * Check if an element or its ancestors are interactive (button, link)
	 *
	 * @param {HTMLElement} element The element to check
	 * @param {HTMLElement} container The container to stop checking at
	 * @return {boolean} True if element is interactive
	 */
	function isInteractiveElement( element, container ) {
		let current = element;

		while ( current && current !== container ) {
			const tagName = current.tagName.toLowerCase();
			const role = current.getAttribute( 'role' );

			// Check for buttons and links
			if (
				tagName === 'a' ||
				tagName === 'button' ||
				role === 'button' ||
				role === 'link' ||
				current.classList.contains( 'wp-block-button__link' )
			) {
				return true;
			}

			current = current.parentElement;
		}

		return false;
	}

	/**
	 * Handle click on video cards
	 *
	 * @param {Event} event The click event
	 */
	function handleCardClick( event ) {
		const card = event.currentTarget;

		// If the card itself is a button/link (e.g., Button block), always open modal
		if ( isCardInteractive( card ) ) {
			event.preventDefault();
			openModal( card );
			return;
		}

		// For container blocks (e.g., Cover), don't open modal if clicking on interactive elements inside
		if ( isInteractiveElement( event.target, card ) ) {
			return;
		}

		event.preventDefault();
		openModal( card );
	}

	/**
	 * Handle keyboard events on video cards
	 *
	 * @param {KeyboardEvent} event The keyboard event
	 */
	function handleCardKeydown( event ) {
		if ( event.key === 'Enter' || event.key === ' ' ) {
			const card = event.currentTarget;

			// Don't open modal if the focused element is interactive
			if ( isInteractiveElement( event.target, card ) ) {
				return;
			}

			event.preventDefault();
			openModal( card );
		}
	}

	// Initialize video cards
	const videoCards = document.querySelectorAll( '.ollie-video-modal-trigger' );
	videoCards.forEach( ( card ) => {
		card.addEventListener( 'click', handleCardClick );
		card.addEventListener( 'keydown', handleCardKeydown );
	} );

	// Close modal on close button click
	closeBtn?.addEventListener( 'click', closeModal );

	// Close modal on overlay click (outside the modal content)
	overlay.addEventListener( 'click', ( event ) => {
		if ( event.target === overlay ) {
			closeModal();
		}
	} );

	// Close modal on Escape key
	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && overlay.classList.contains( 'is-open' ) ) {
			closeModal();
		}
	} );
} )();
