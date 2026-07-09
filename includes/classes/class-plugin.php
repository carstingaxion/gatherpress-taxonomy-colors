<?php
/**
 * Slim orchestrator — registers the Gutenberg block and bootstraps
 * all sub-singletons.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Main plugin orchestrator — Singleton.
 *
 * @since 0.1.0
 */
class Plugin {

	use Core\Traits\Singleton;


		/**
		 * Private constructor — registers the block and bootstraps
		 * all sub-singletons.
		 *
		 * @since 0.1.0
		 */
	protected function __construct() {
		$this->setup_hooks();
	}

		/**
		 * Register hooks.
		 *
		 * @since 0.1.0
		 * @return void
		 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_block' ) );

		// Bootstrap sub-singletons — each self-registers its hooks.
		Term_Color_Meta::get_instance();
		Term_Color_Tokens::get_instance();
		Term_Color_Resolver::get_instance();
		Term_Color_Scoper::get_instance();
		Shadow_Taxonomy_Support::get_instance();
	}

		/**
		 * Registers the Gutenberg block from the build directory.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function register_block(): void {
		register_block_type( __DIR__ . '/build/' );
	}

		/**
		 * Returns the list of taxonomy slugs that support term color meta.
		 *
		 * @since  0.1.0
		 * @return array<int, string> Array of taxonomy slugs.
		 */
	public function get_color_taxonomies(): array {
		/**
		 * Filters the taxonomies that participate in the term color system.
		 *
		 * This is the **single source of truth** for which taxonomies receive
		 * color meta registration (Layer 1), design token slots (Layer 2),
		 * frontend CSS custom property injection (Layer 3), editor palette
		 * resolution (Layer 4), scoped per-post injection (Layer 5), and
		 * shadow taxonomy detection (Layer 6).
		 *
		 * Each entry should be a registered taxonomy slug. Adding a slug
		 * automatically triggers:
		 *
		 * - `register_term_meta()` for every color role defined by
		 *   `gptc_term_color_roles`.
		 * - Palette entries in `theme.json` (one per role per taxonomy).
		 * - `--flavor--{taxonomy}-{role}` CSS custom properties on the
		 *   frontend and in the editor.
		 * - Color picker fields on the term edit screen.
		 * - A "Colors" swatch column in the term list table.
		 *
		 * **Shadow taxonomy convention:** Slugs prefixed with `_` (underscore)
		 * are treated as shadow taxonomy candidates. The plugin strips the
		 * leading underscore, checks whether a matching post type exists with
		 * `gatherpress-shadow-source` support, and — if confirmed — moves the
		 * admin UI to the post editor and uses GatherPress helpers for term
		 * resolution. See Layer 6 documentation for details.
		 *
		 * @since 0.1.0
		 *
		 * @param array<int, string> $taxonomies Array of taxonomy slugs.
		 *                                       Default: `array( '_gatherpress_play', 'post_tag', 'category' )`.
		 *
		 * @example
		 * ```php
		 * // Add a custom "genre" taxonomy to the color system.
		 * add_filter( 'gptc_term_color_taxonomies', function ( array $taxonomies ): array {
		 *     $taxonomies[] = 'genre';
		 *     return $taxonomies;
		 * } );
		 * ```
		 *
		 * @example
		 * ```php
		 * // Enable only categories (remove tags and shadow taxonomies).
		 * add_filter( 'gptc_term_color_taxonomies', function (): array {
		 *     return array( 'category' );
		 * } );
		 * ```
		 *
		 * @example
		 * ```php
		 * // Add the GatherPress shadow taxonomy for the "venue" post type.
		 * add_filter( 'gptc_term_color_taxonomies', function ( array $taxonomies ): array {
		 *     $taxonomies[] = '_gatherpress_venue';
		 *     return $taxonomies;
		 * } );
		 * ```
		 */
		return (array) apply_filters(
			'gptc_term_color_taxonomies',
			array(
				'gatherpress_topic',
				'_gatherpress_venue',
				'_gatherpress_play',
				'_gatherpress_season',
				'post_tag',
				'category',
			)
		);
	}
}

	// Bootstrap the plugin.
	Plugin::get_instance();
