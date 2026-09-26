<?php
/**
 * Video Modal - PHP Loader
 *
 * Handles frontend rendering of video modal blocks (Cover, Button) with modal functionality.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supported blocks for video modal functionality
 */
define( 'OLLIE_PRO_VIDEO_MODAL_BLOCKS', array( 'core/cover', 'core/button' ) );

/**
 * Blocks that show the play icon
 */
define( 'OLLIE_PRO_VIDEO_MODAL_PLAY_ICON_BLOCKS', array( 'core/cover' ) );

/**
 * Video attributes that may be overridden per synced-pattern instance.
 */
define(
	'OLLIE_PRO_VIDEO_MODAL_OVERRIDABLE_ATTRIBUTES',
	array(
		'ollieVideoSource',
		'ollieVideoUrl',
		'ollieVideoId',
		'ollieVideoAutoplay',
		'ollieVideoStartTime',
	)
);

/**
 * Opt the video attributes into block bindings so synced patterns can
 * override the video per instance (WP 7.0+ pattern overrides).
 *
 * @param array $supported_attributes Attributes already supported for bindings.
 * @return array Attributes including the video modal set.
 */
function ollie_pro_video_modal_bindings_attributes( $supported_attributes ) {
	return array_merge( $supported_attributes, OLLIE_PRO_VIDEO_MODAL_OVERRIDABLE_ATTRIBUTES );
}

// The filter's consumer (get_block_bindings_supported_attributes) exists on
// WP 6.9+; registering on older versions is a harmless no-op.
foreach ( OLLIE_PRO_VIDEO_MODAL_BLOCKS as $ollie_video_modal_block ) {
	add_filter( "block_bindings_supported_attributes_{$ollie_video_modal_block}", 'ollie_pro_video_modal_bindings_attributes' );
}
unset( $ollie_video_modal_block );

/**
 * Add data attributes and class to video modal blocks
 *
 * @param string        $block_content The block content.
 * @param array         $block         The block data.
 * @param WP_Block|null $instance      The block instance, whose attributes
 *                                     include processed binding values
 *                                     (e.g. pattern overrides).
 * @return string Modified block content.
 */
function ollie_pro_video_modal_render_block( $block_content, $block, $instance = null ) {
	if ( ! in_array( $block['blockName'], OLLIE_PRO_VIDEO_MODAL_BLOCKS, true ) ) {
		return $block_content;
	}

	// Prefer the instance attributes: WP_Block::render() merges block
	// binding values (pattern overrides) into them before this filter runs.
	if ( $instance instanceof WP_Block ) {
		$attrs = $instance->attributes;
	} else {
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	}

	// Check if video modal is enabled for this block
	if ( empty( $attrs['ollieVideoModal'] ) ) {
		return $block_content;
	}

	$video_source     = isset( $attrs['ollieVideoSource'] ) ? $attrs['ollieVideoSource'] : 'youtube';
	$video_url        = isset( $attrs['ollieVideoUrl'] ) ? $attrs['ollieVideoUrl'] : '';
	$video_id         = isset( $attrs['ollieVideoId'] ) ? absint( $attrs['ollieVideoId'] ) : 0;
	$video_autoplay   = isset( $attrs['ollieVideoAutoplay'] ) ? rest_sanitize_boolean( $attrs['ollieVideoAutoplay'] ) : true;
	$video_start_time = isset( $attrs['ollieVideoStartTime'] ) ? absint( $attrs['ollieVideoStartTime'] ) : 0;
	$play_icon        = isset( $attrs['olliePlayIcon'] ) ? $attrs['olliePlayIcon'] : 'always';
	$show_play_icon   = in_array( $block['blockName'], OLLIE_PRO_VIDEO_MODAL_PLAY_ICON_BLOCKS, true );

	// Resolve the trigger's accessible label: explicit label attribute first,
	// then the block's custom List View name — a generic "Play video" on every
	// trigger leaves screen reader users unable to tell videos apart.
	$aria_label = isset( $attrs['ollieVideoAriaLabel'] ) ? trim( (string) $attrs['ollieVideoAriaLabel'] ) : '';
	if ( '' === $aria_label && isset( $attrs['metadata']['name'] ) ) {
		$block_label = trim( (string) $attrs['metadata']['name'] );
		if ( '' !== $block_label ) {
			/* translators: %s: the block's custom name from the List View. */
			$aria_label = sprintf( __( 'Play video: %s', 'ollie-pro' ), $block_label );
		}
	}

	// Get YouTube video ID if applicable
	$youtube_id = '';
	if ( 'youtube' === $video_source && $video_url ) {
		$youtube_id = ollie_pro_get_youtube_video_id( $video_url );
		// Only YouTube's ID alphabet — anything else is treated as no video.
		if ( $youtube_id && ! preg_match( '/^[A-Za-z0-9_-]{5,20}$/', $youtube_id ) ) {
			$youtube_id = '';
		}
	}

	// Get media library video URL if applicable
	$library_video_url = '';
	if ( 'library' === $video_source && $video_id ) {
		$library_video_url = wp_get_attachment_url( $video_id );
	}

	// No valid video (e.g. a pattern override cleared it): strip the
	// save-time trigger class so the element is inert, and bail.
	if ( ( 'youtube' === $video_source && ! $youtube_id )
		|| ( 'library' === $video_source && ! $library_video_url )
		|| ( 'youtube' !== $video_source && 'library' !== $video_source )
	) {
		$processor = new WP_HTML_Tag_Processor( $block_content );
		// The save-time class is baked onto the block's ROOT element for
		// both blocks (getSaveContent.extraProps applies to the root), so
		// target the first tag — the button's inner <a> only ever gets the
		// class from this filter's valid-video path, which didn't run.
		if ( $processor->next_tag() ) {
			$processor->remove_class( 'ollie-video-modal-trigger' );
		}
		return $processor->get_updated_html();
	}

	// Enqueue assets only when we have a valid video modal block
	ollie_pro_video_modal_enqueue_assets();

	// Process the block HTML
	$processor = new WP_HTML_Tag_Processor( $block_content );

	// For button blocks, we need to target the anchor element inside, not the wrapper div
	if ( 'core/button' === $block['blockName'] ) {
		// Find the anchor element inside the button wrapper
		if ( $processor->next_tag( 'a' ) ) {
			$processor->add_class( 'ollie-video-modal-trigger' );
			$processor->set_attribute( 'data-video-source', $video_source );
			$processor->set_attribute( 'data-video-autoplay', $video_autoplay ? 'true' : 'false' );

			if ( '' !== $aria_label ) {
				$processor->set_attribute( 'aria-label', $aria_label );
			} elseif ( '' === trim( wp_strip_all_tags( $block_content ) ) ) {
				// Icon-only button — it needs some accessible name. Buttons with
				// visible text keep that text as their name (WCAG label-in-name).
				$processor->set_attribute( 'aria-label', __( 'Play video', 'ollie-pro' ) );
			}

			if ( 'youtube' === $video_source ) {
				$processor->set_attribute( 'data-youtube-id', $youtube_id );
				if ( $video_start_time > 0 ) {
					$processor->set_attribute( 'data-video-start', $video_start_time );
				}
				if ( strpos( $video_url, 'youtube.com/embed/' ) !== false ) {
					$processor->set_attribute( 'data-embed-url', esc_url( $video_url ) );
				}
			} else {
				$processor->set_attribute( 'data-video-url', $library_video_url );
			}
		}
	} else {
		// For other blocks (Cover), target the first element
		if ( $processor->next_tag() ) {
			$processor->add_class( 'ollie-video-modal-trigger' );
			$processor->set_attribute( 'data-video-source', $video_source );
			$processor->set_attribute( 'data-video-autoplay', $video_autoplay ? 'true' : 'false' );

			if ( $show_play_icon ) {
				$processor->set_attribute( 'data-play-icon', esc_attr( $play_icon ) );
			}

			$processor->set_attribute( 'role', 'button' );
			$processor->set_attribute( 'tabindex', '0' );
			$processor->set_attribute( 'aria-label', '' !== $aria_label ? $aria_label : __( 'Play video', 'ollie-pro' ) );

			if ( 'youtube' === $video_source ) {
				$processor->set_attribute( 'data-youtube-id', $youtube_id );
				if ( $video_start_time > 0 ) {
					$processor->set_attribute( 'data-video-start', $video_start_time );
				}
				if ( strpos( $video_url, 'youtube.com/embed/' ) !== false ) {
					$processor->set_attribute( 'data-embed-url', esc_url( $video_url ) );
				}
			} else {
				$processor->set_attribute( 'data-video-url', $library_video_url );
			}
		}
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'ollie_pro_video_modal_render_block', 10, 3 );

/**
 * Extract YouTube video ID from URL
 *
 * @param string $url The YouTube URL.
 * @return string|null The video ID or null if not found.
 */
function ollie_pro_get_youtube_video_id( $url ) {
	if ( empty( $url ) ) {
		return null;
	}

	$patterns = array(
		'/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\s?]+)/',
		'/youtube\.com\/shorts\/([^&\s?]+)/',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $url, $matches ) ) {
			return $matches[1];
		}
	}

	return null;
}

/**
 * Track whether we've found a video modal block on this page
 */
global $ollie_pro_has_video_modal;
$ollie_pro_has_video_modal = false;

/**
 * Enqueue frontend scripts and styles for video modal
 * Only enqueues when a video modal block is actually present
 */
function ollie_pro_video_modal_enqueue_assets() {
	global $ollie_pro_has_video_modal;

	// Only enqueue once
	if ( wp_script_is( 'ollie-pro-video-modal', 'enqueued' ) ) {
		return;
	}

	$ollie_pro_has_video_modal = true;

	wp_enqueue_style(
		'ollie-pro-video-modal',
		OLPO_URL . '/inc/extensions/loader/video-modal/video-modal.css',
		array(),
		OLPO_VERSION
	);

	wp_enqueue_script(
		'ollie-pro-video-modal',
		OLPO_URL . '/inc/extensions/loader/video-modal/video-modal-frontend.js',
		array(),
		OLPO_VERSION,
		true
	);
}

/**
 * Add modal container to footer
 * Only adds if a video modal block was rendered on this page
 */
function ollie_pro_video_modal_add_modal() {
	global $ollie_pro_has_video_modal;

	// Only add if we're not in the admin and a video modal block exists
	if ( is_admin() || ! $ollie_pro_has_video_modal ) {
		return;
	}
	?>
	<div class="ollie-video-modal-overlay" id="ollie-video-modal-overlay" aria-hidden="true">
		<div class="ollie-video-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Video player', 'ollie-pro' ); ?>">
			<button class="ollie-video-modal__close" aria-label="<?php esc_attr_e( 'Close video', 'ollie-pro' ); ?>"></button>
			<div class="ollie-video-modal__content" id="ollie-video-modal-content"></div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'ollie_pro_video_modal_add_modal' );
