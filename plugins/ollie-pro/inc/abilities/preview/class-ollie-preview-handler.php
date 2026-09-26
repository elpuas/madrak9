<?php
/**
 * Ollie Preview Handler — transient-based preview with auth + expiry.
 *
 * Generates a short-lived preview URL for block markup by storing
 * it in a transient and serving it via a front-end template.
 *
 * Spec response contract fields: preview_url, screenshot (future).
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Preview_Handler {

	/**
	 * Transient prefix for previews.
	 */
 private const TRANSIENT_PREFIX = 'ollie_abilities_preview_';

	/**
	 * Preview expiry in seconds (5 minutes).
	 */
	private const PREVIEW_TTL = 300;

	/**
	 * Constructor — register the preview rendering endpoint.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_render_preview' ) );
	}

	/**
	 * Create a preview for block markup and return the preview URL.
	 *
	 * @param string $markup Block markup to preview.
	 * @param int    $user_id User ID that owns this preview (for auth).
	 * @return array{preview_url: string, token: string, expires_at: int}
	 */
	public function create_preview( string $markup, int $user_id = 0 ): array {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$token      = wp_generate_password( 32, false );
		$expires_at = time() + self::PREVIEW_TTL;

		$data = array(
			'markup'     => $markup,
			'user_id'    => $user_id,
			'expires_at' => $expires_at,
		);

		set_transient( self::TRANSIENT_PREFIX . $token, $data, self::PREVIEW_TTL );

		$preview_url = add_query_arg(
			array(
				'ollie_preview' => $token,
			),
			home_url( '/' )
		);

		return array(
			'preview_url' => $preview_url,
			'token'       => $token,
			'expires_at'  => $expires_at,
		);
	}

	/**
	 * Intercept template_redirect and render a preview if requested.
	 */
	public function maybe_render_preview(): void {
		if ( ! isset( $_GET['ollie_preview'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['ollie_preview'] ) );
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( false === $data || ! is_array( $data ) ) {
			wp_die(
				esc_html__( 'This preview has expired or does not exist.', 'ollie-pro' ),
				esc_html__( 'Preview Not Found', 'ollie-pro' ),
				array( 'response' => 404 )
			);
		}

		// Auth check — only the user who created the preview can view it.
		if ( ! is_user_logged_in() || get_current_user_id() !== (int) $data['user_id'] ) {
			wp_die(
				esc_html__( 'You are not authorized to view this preview.', 'ollie-pro' ),
				esc_html__( 'Unauthorized', 'ollie-pro' ),
				array( 'response' => 403 )
			);
		}

		// Expiry check.
		if ( time() > (int) $data['expires_at'] ) {
			delete_transient( self::TRANSIENT_PREFIX . $token );
			wp_die(
				esc_html__( 'This preview has expired.', 'ollie-pro' ),
				esc_html__( 'Preview Expired', 'ollie-pro' ),
				array( 'response' => 410 )
			);
		}

		$this->render( $data['markup'] );
		exit;
	}

	/**
	 * Render a full-page preview of block markup using the active theme.
	 *
	 * @param string $markup Block markup.
	 */
	private function render( string $markup ): void {
		$rendered = do_blocks( $markup );

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex, nofollow">
			<?php wp_head(); ?>
			<style>
				body {
					margin: 0;
					padding: 0;
				}
				.ollie-preview-container {
					max-width: var(--wp--style--global--wide-size, 1260px);
					margin: 0 auto;
					padding: 2rem;
				}
			</style>
		</head>
		<body <?php body_class( 'ollie-abilities-preview' ); ?>>
			<div class="ollie-preview-container">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup is pre-sanitized.
				echo $rendered;
				?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Delete a preview transient.
	 *
	 * @param string $token Preview token.
	 * @return bool True if deleted.
	 */
	public function delete_preview( string $token ): bool {
		return delete_transient( self::TRANSIENT_PREFIX . $token );
	}
}
