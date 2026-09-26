/**
 * Ollie Editor Refresh
 *
 * Listens on the WordPress Heartbeat API to detect when a post has been
 * modified externally (e.g. via MCP / Claude Desktop) and automatically
 * refreshes the block editor content so changes are immediately visible.
 *
 * After refreshing, any blocks that Gutenberg marks as invalid
 * ("Attempt block recovery") are automatically recovered so users
 * never see those prompts.
 */
( function () {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.data || ! wp.domReady || ! wp.blocks || typeof jQuery === 'undefined' ) {
		return;
	}

	wp.domReady( function () {
		var editor      = wp.data.select( 'core/editor' );
		var coreData    = wp.data.dispatch( 'core' );
		var notices     = wp.data.dispatch( 'core/notices' );
		var blockEditor = wp.data.select( 'core/block-editor' );
		var blockEditorDispatch = wp.data.dispatch( 'core/block-editor' );

		if ( ! editor ) {
			return;
		}

		var lastKnownModified = '';

		/**
		 * Recursively recover invalid blocks.
		 *
		 * Walks the full block tree. For every block whose `isValid`
		 * flag is false, creates a fresh block (re-serialised from
		 * parsed attributes) and replaces the original. This is
		 * exactly what the "Attempt recovery" button does in the UI.
		 *
		 * @param {Array} blocks List of blocks to check.
		 * @return {number} Number of blocks recovered.
		 */
		function recoverInvalidBlocks( blocks ) {
			var recovered = 0;

			if ( ! blocks || ! blocks.length ) {
				return recovered;
			}

			for ( var i = 0; i < blocks.length; i++ ) {
				var block = blocks[ i ];

				// Recurse into inner blocks first.
				if ( block.innerBlocks && block.innerBlocks.length ) {
					recovered += recoverInvalidBlocks( block.innerBlocks );
				}

				// Recover invalid blocks.
				if ( block.isValid === false && block.name && block.clientId ) {
					try {
						var recoveredBlock = wp.blocks.createBlock(
							block.name,
							block.attributes,
							block.innerBlocks
						);

						blockEditorDispatch.replaceBlock( block.clientId, recoveredBlock );
						recovered++;
					} catch ( e ) {
						// Silently skip blocks that can't be recovered.
					}
				}
			}

			return recovered;
		}

		/**
		 * Attempt block recovery after a short delay to let the
		 * editor finish parsing the refreshed content.
		 *
		 * Retries up to 3 times (with increasing delays) in case
		 * the editor hasn't finished loading the new blocks yet.
		 *
		 * @param {number} attempt Current attempt number.
		 */
		function attemptRecovery( attempt ) {
			if ( attempt > 3 ) {
				return;
			}

			var delay = attempt * 1500; // 1.5s, 3s, 4.5s

			setTimeout( function () {
				var allBlocks = blockEditor.getBlocks();

				if ( ! allBlocks || ! allBlocks.length ) {
					// Editor may not have loaded blocks yet — retry.
					attemptRecovery( attempt + 1 );
					return;
				}

				var count = recoverInvalidBlocks( allBlocks );

				if ( count > 0 && notices ) {
					notices.createInfoNotice(
						count + ( count === 1 ? ' block was' : ' blocks were' ) + ' automatically recovered.',
						{
							id:            'ollie-block-recovery',
							isDismissible: true,
							type:          'snackbar',
						}
					);
				}
			}, delay );
		}

		// Send the current post ID on every heartbeat tick.
		jQuery( document ).on( 'heartbeat-send', function ( _event, data ) {
			var postId = editor.getCurrentPostId();
			if ( ! postId ) {
				return;
			}
			data.ollie_editor_refresh = {
				post_id: postId,
			};
		} );

		// Process the heartbeat response — check for external modifications.
		jQuery( document ).on( 'heartbeat-tick', function ( _event, data ) {
			if ( ! data.ollie_editor_refresh || ! data.ollie_editor_refresh.post_modified ) {
				return;
			}

			var serverModified = data.ollie_editor_refresh.post_modified;

			// First tick — just record the baseline.
			if ( ! lastKnownModified ) {
				lastKnownModified = serverModified;
				return;
			}

			// No change since last tick.
			if ( serverModified === lastKnownModified ) {
				return;
			}

			// External modification detected — refresh editor content.
			lastKnownModified = serverModified;

			var postId   = editor.getCurrentPostId();
			var postType = editor.getCurrentPostType();

			if ( postId && postType && coreData && coreData.invalidateResolution ) {
				// Invalidate the cached entity so the editor fetches fresh data.
				coreData.invalidateResolution( 'getEntityRecord', [ 'postType', postType, postId ] );

				if ( notices ) {
					notices.createInfoNotice(
						'This post was updated externally. The editor content has been refreshed.',
						{
							id:          'ollie-editor-refresh',
							isDismissible: true,
							type:        'snackbar',
						}
					);
				}

				// After refreshing, attempt to recover any invalid blocks.
				attemptRecovery( 1 );
			}
		} );
	} );
} )();
