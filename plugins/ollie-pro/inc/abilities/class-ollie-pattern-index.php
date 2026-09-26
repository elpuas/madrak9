<?php
/**
 * Ollie Pattern Index — search, retrieve, and inspect registered patterns.
 *
 * Provides fast local pattern lookups via WP_Block_Patterns_Registry
 * and semantic vector search via the Ollie cloud pattern library.
 *
 * Performance notes:
 * - get_all() results are cached in memory for the request lifetime.
 * - cloud_search() results are cached in a transient (5 min TTL) keyed
 *   by prompt + parameters, so repeated identical queries are instant.
 * - Cloud patterns are auto-saved to disk for future local resolution.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Pattern_Index {

	/**
	 * Subdirectory inside the active theme's patterns/ folder
	 * where cloud-fetched patterns are cached on disk.
	 */
	const CLOUD_PATTERNS_DIR = 'cloud';

	/**
	 * Supabase edge function URL for semantic pattern search.
	 */
	const CLOUD_SEARCH_URL = 'https://vttiicmlzxzxrcyyewfn.supabase.co/functions/v1/pattern-search';

	/**
	 * Supabase anon key for Authorization header.
	 */
	const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZ0dGlpY21senh6eHJjeXlld2ZuIiwicm9sZSI6ImFub24iLCJpYXQiOjE2OTgwNTgwNzUsImV4cCI6MjAxMzYzNDA3NX0.Pxu-gsXoxazhKDcT1VvbzfYA2kyjiG07rphZmWvCEAg';

	/**
	 * Transient TTL for cloud search results (5 minutes).
	 */
	private const CLOUD_CACHE_TTL = 300;

	/**
	 * In-memory cache of parsed pattern metadata (per-request).
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Get all registered patterns with their metadata.
	 *
	 * Results are cached in memory — multiple calls within the same
	 * request return instantly without re-querying the registry.
	 *
	 * @return array[] Associative array keyed by pattern name.
	 */
	public function get_all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$registry     = \WP_Block_Patterns_Registry::get_instance();
		$all_patterns = $registry->get_all_registered();
		$this->cache  = array();

		foreach ( $all_patterns as $pattern ) {
			$name = $pattern['name'] ?? '';
			if ( '' === $name ) {
				continue;
			}

			$this->cache[ $name ] = array(
				'name'          => $name,
				'title'         => $pattern['title'] ?? '',
				'description'   => $pattern['description'] ?? '',
				'categories'    => $pattern['categories'] ?? array(),
				'keywords'      => $pattern['keywords'] ?? array(),
				'blockTypes'    => $pattern['blockTypes'] ?? array(),
				'content'       => $pattern['content'] ?? '',
				'viewportWidth' => $pattern['viewportWidth'] ?? 1200,
			);
		}

		return $this->cache;
	}

	/**
	 * Search patterns by keyword / category / free-text query.
	 *
	 * Searches across title, description, keywords, and categories.
	 * Returns metadata only (no full content) to keep payloads small.
	 *
	 * @param string $query    Free-text search query.
	 * @param string $category Optional category slug filter.
	 * @param int    $limit    Maximum results (default 20).
	 * @return array[] Matching patterns (metadata only).
	 */
	public function search( string $query = '', string $category = '', int $limit = 20 ): array {
		$all     = $this->get_all();
		$results = array();
		$query   = strtolower( trim( $query ) );

		foreach ( $all as $name => $pattern ) {
			if ( '' !== $category && ! in_array( $category, $pattern['categories'], true ) ) {
				continue;
			}

			if ( '' !== $query ) {
				$haystack = strtolower(
					$pattern['title'] . ' ' .
					$pattern['description'] . ' ' .
					implode( ' ', $pattern['keywords'] ) . ' ' .
					implode( ' ', $pattern['categories'] )
				);

				if ( false === strpos( $haystack, $query ) ) {
					continue;
				}
			}

			$results[] = array(
				'name'        => $pattern['name'],
				'title'       => $pattern['title'],
				'description' => $pattern['description'],
				'categories'  => $pattern['categories'],
				'keywords'    => $pattern['keywords'],
			);

			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Get a single pattern by name, including its full content.
	 *
	 * @param string $name Pattern name (e.g. "ollie/card-call-to-action").
	 * @return array|null Pattern data or null if not found.
	 */
	public function get( string $name ): ?array {
		$all = $this->get_all();
		return $all[ $name ] ?? null;
	}

	/**
	 * Get the block markup content for a pattern.
	 *
	 * @param string $name Pattern name.
	 * @return string Block markup or empty string.
	 */
	public function get_content( string $name ): string {
		$pattern = $this->get( $name );
		return $pattern['content'] ?? '';
	}

	/**
	 * List all available pattern categories.
	 *
	 * @return array[] Array of category data with 'name' and 'label' keys.
	 */
	public function get_categories(): array {
		$registry   = \WP_Block_Pattern_Categories_Registry::get_instance();
		$categories = $registry->get_all_registered();
		$result     = array();

		foreach ( $categories as $cat ) {
			$result[] = array(
				'name'  => $cat['name'] ?? '',
				'label' => $cat['label'] ?? '',
			);
		}

		return $result;
	}

	/**
	 * Semantic vector search via the Ollie cloud pattern library.
	 *
	 * Calls the Supabase edge function which generates embeddings and
	 * performs cosine-similarity search against the pattern vector DB.
	 *
	 * Results are cached in a transient for 5 minutes — identical
	 * queries within that window return instantly without an API call.
	 * Matched patterns are auto-saved to disk for local resolution.
	 *
	 * @param string $prompt          Free-text search prompt.
	 * @param int    $limit           Maximum results (1–100, default 5).
	 * @param bool   $include_content Whether to include full pattern markup.
	 * @param float  $threshold       Minimum similarity score (0–1, default 0.5).
	 * @return array Search results or error array.
	 */
	public function cloud_search( string $prompt, int $limit = 5, bool $include_content = true, float $threshold = 0.5 ): array {
		$prompt = trim( $prompt );
		if ( '' === $prompt ) {
			return array( 'error' => __( 'Missing or empty search prompt.', 'ollie-pro' ) );
		}

		// Clamp parameters.
		$limit     = max( 1, min( 100, $limit ) );
		$threshold = max( 0.0, min( 1.0, $threshold ) );

		// Check transient cache first.
		$cache_key = 'ollie_cs_' . md5( $prompt . '|' . $limit . '|' . (int) $include_content . '|' . $threshold );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		$response = wp_remote_post(
			self::CLOUD_SEARCH_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . self::SUPABASE_ANON_KEY,
					'apikey'        => self::SUPABASE_ANON_KEY,
				),
				'body'    => wp_json_encode( array(
					'prompt'          => $prompt,
					'limit'           => $limit,
					'include_content' => $include_content,
					'threshold'       => $threshold,
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => sprintf(
					/* translators: %s: error message */
					__( 'Cloud search request failed: %s', 'ollie-pro' ),
					$response->get_error_message()
				),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			$error_message = isset( $data['error'] ) ? $data['error'] : __( 'Unknown cloud search error.', 'ollie-pro' );
			return array(
				'error' => sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'Cloud search returned HTTP %1$d: %2$s', 'ollie-pro' ),
					$code,
					$error_message
				),
			);
		}

		// Auto-cache patterns locally when content is included.
		if ( $include_content && ! empty( $data['patterns'] ) && is_array( $data['patterns'] ) ) {
			$saved = $this->save_cloud_patterns( $data['patterns'] );

			foreach ( $data['patterns'] as &$p ) {
				$slug = $p['slug'] ?? '';
				if ( '' !== $slug ) {
					$p['pattern_slug'] = 'cloud/' . $slug;
				}
			}
			unset( $p );

			$data['cached_locally'] = count( $saved );
		}

		// Cache in transient for fast repeated lookups.
		set_transient( $cache_key, $data, self::CLOUD_CACHE_TTL );

		return $data;
	}

	/**
	 * Invalidate the in-memory pattern cache.
	 *
	 * Call after saving new patterns or modifying registrations
	 * so subsequent get_all() calls see fresh data.
	 */
	public function flush(): void {
		$this->cache = null;
	}

	/**
	 * Get the directory path for cached cloud patterns.
	 *
	 * @return string Absolute path to the cloud patterns directory.
	 */
	private function get_cloud_patterns_dir(): string {
		return get_stylesheet_directory() . '/patterns/' . self::CLOUD_PATTERNS_DIR;
	}

	/**
	 * Save cloud search results as local pattern PHP files inside the active theme.
	 *
	 * Each pattern is saved as a standard WordPress block pattern PHP file
	 * in {theme}/patterns/cloud/{slug}.php so it can be referenced locally
	 * without re-fetching from the API.
	 *
	 * @param array $patterns Array of pattern data from cloud_search().
	 * @return array Slugs of successfully saved patterns.
	 */
	public function save_cloud_patterns( array $patterns ): array {
		$dir = $this->get_cloud_patterns_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return array();
		}

		$saved_slugs = array();

		foreach ( $patterns as $pattern ) {
			$slug    = $pattern['slug'] ?? '';
			$title   = $pattern['title'] ?? '';
			$content = $pattern['content'] ?? '';

			if ( '' === $slug || '' === $content ) {
				continue;
			}

			$filename = sanitize_file_name( str_replace( '/', '-', $slug ) ) . '.php';
			$filepath = $dir . '/' . $filename;

			// Build categories string.
			$categories_raw = $pattern['categories'] ?? array();
			$categories_str = '';
			if ( is_array( $categories_raw ) ) {
				$cat_names = array();
				foreach ( $categories_raw as $cat ) {
					if ( is_array( $cat ) && isset( $cat['name'] ) ) {
						$cat_names[] = $cat['name'];
					} elseif ( is_string( $cat ) ) {
						$cat_names[] = $cat;
					}
				}
				$categories_str = implode( ', ', $cat_names );
			}

			// Build standard WP pattern file.
			$open_tag  = chr( 60 ) . '?php';
			$close_tag = '?' . chr( 62 );
			$header  = $open_tag . "\n";
			$header .= "/**\n";
			$header .= " * Title: " . str_replace( '*/', '', $title ) . "\n";
			$header .= " * Slug: cloud/" . str_replace( '*/', '', $slug ) . "\n";
			$header .= " * Description: Cloud pattern from Ollie library\n";
			$header .= " * Categories: " . str_replace( '*/', '', $categories_str ) . "\n";
			$header .= " * Keywords: cloud, ollie\n";
			$header .= " * Inserter: true\n";
			$header .= " */\n";
			$header .= $close_tag . "\n";

			$written = file_put_contents( $filepath, $header . $content );
			if ( false !== $written ) {
				$saved_slugs[] = 'cloud/' . $slug;
			}
		}

		// Flush so newly saved patterns are discoverable.
		$this->flush();

		return $saved_slugs;
	}

	/**
	 * Get a cached cloud pattern by its slug.
	 *
	 * Reads the pattern file from disk, parsing the content
	 * after the PHP header block.
	 *
	 * @param string $slug Pattern slug (with or without "cloud/" prefix).
	 * @return array|null Pattern data or null if not found.
	 */
	public function get_cached_cloud_pattern( string $slug ): ?array {
		$raw_slug = preg_replace( '#^cloud/#', '', $slug );
		$dir      = $this->get_cloud_patterns_dir();
		$filename = sanitize_file_name( str_replace( '/', '-', $raw_slug ) ) . '.php';
		$filepath = $dir . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			return null;
		}

		$raw = file_get_contents( $filepath );
		if ( false === $raw ) {
			return null;
		}

		// Extract title from file header.
		$title = '';
		if ( preg_match( '/^\s*\*\s*Title:\s*(.+)$/m', $raw, $m ) ) {
			$title = trim( $m[1] );
		}

		// Content comes after the closing PHP tag.
		$close_pattern = '/' . '\\?' . chr( 62 ) . '\\s*\\n/';
		$parts   = preg_split( $close_pattern, $raw, 2 );
		$content = isset( $parts[1] ) ? $parts[1] : '';

		if ( '' === $content ) {
			return null;
		}

		return array(
			'slug'    => 'cloud/' . $raw_slug,
			'title'   => $title,
			'content' => $content,
		);
	}

	/**
	 * List all locally cached cloud pattern slugs.
	 *
	 * @return array[] Array of pattern data with 'slug' and 'title' keys.
	 */
	public function list_cached_cloud_patterns(): array {
		$dir = $this->get_cloud_patterns_dir();

		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$files    = glob( $dir . '/*.php' );
		if ( false === $files ) {
			return array();
		}

		$patterns = array();

		foreach ( $files as $filepath ) {
			$raw = file_get_contents( $filepath );
			if ( false === $raw ) {
				continue;
			}

			$title = '';
			if ( preg_match( '/^\s*\*\s*Title:\s*(.+)$/m', $raw, $m ) ) {
				$title = trim( $m[1] );
			}

			$slug = '';
			if ( preg_match( '/^\s*\*\s*Slug:\s*(.+)$/m', $raw, $m ) ) {
				$slug = trim( $m[1] );
			}

			if ( '' !== $slug ) {
				$patterns[] = array(
					'slug'  => $slug,
					'title' => $title,
				);
			}
		}

		return $patterns;
	}
}
