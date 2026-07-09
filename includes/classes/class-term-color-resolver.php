<?php
/**
 * Layers 3 & 4 — Contextual Color Resolution.
 *
 * Resolves term colors for the current frontend context (singular post,
 * taxonomy archive) and for the block editor, injecting --flavor--*
 * CSS custom properties and updating the editor palette with resolved
 * hex values for the post being edited.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;
use WP_Post;
use WP_Term;
use WP_Theme_JSON_Data;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Singleton managing contextual term color resolution and CSS injection.
 *
 * @since 0.1.0
 */
class Term_Color_Resolver {

	use Core\Traits\Singleton;


		/**
		 * Private constructor — registers frontend and editor hooks.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_frontend_term_color_properties' ) );
		add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_editor_term_color_tokens' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'inject_editor_term_color_styles' ) );
	}

		/**
		 * Detects the current post ID in the block editor context.
		 *
		 * `get_the_ID()` can return false/0 during editor bootstrap for
		 * custom post types. This method checks multiple sources:
		 * 1. `get_the_ID()` (standard path)
		 * 2. `$_GET['post']` (edit screen URL parameter)
		 * 3. Global `$post` object
		 *
		 * @since  0.1.5
		 * @return int Post ID or 0 if not found.
		 */
	private function get_editor_post_id(): int {
		$post_id = get_the_ID();

		if ( $post_id ) {
			return (int) $post_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( is_scalar( $_GET['post'] ) ? $_GET['post'] : 0 );

			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		global $post;

		if ( $post instanceof WP_Post && $post->ID > 0 ) {
			return (int) $post->ID;
		}

		return 0;
	}

		/**
		 * Resolves term colors for the current frontend context.
		 *
		 * @since  0.1.0
		 * @return array<string, string> Map of slot slug to sanitized hex color.
		 */
	public function resolve_contextual_term_colors(): array {
		if ( is_singular() ) {
			return Helpers::resolve_all_taxonomy_colors_for_post( get_queried_object_id() );
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$normalized_tax = Helpers::normalize_taxonomy_slug( $term->taxonomy );
				return Helpers::resolve_colors_from_terms( array( $term ), $normalized_tax );
			}
		}

		return array();
	}

		/**
		 * Resolves term colors for a specific post ID.
		 *
		 * @since  0.1.0
		 * @param  int $post_id The post ID to resolve colors for.
		 * @return array<string, string> Map of slot slug to sanitized hex.
		 */
	public function resolve_term_colors_for_post( int $post_id ): array {
		return Helpers::resolve_all_taxonomy_colors_for_post( $post_id );
	}

		/**
		 * Builds a CSS property map from resolved flavor colors.
		 *
		 * @since  0.1.1
		 * @param  array<string, string> $colors Slot slug to hex map.
		 * @return array<string, string> CSS property name to value map.
		 */
	private function build_flavor_properties( array $colors ): array {
		$properties = array();

		foreach ( $colors as $slot => $hex ) {
			$properties[ '--flavor--' . esc_attr( $slot ) ] = esc_attr( $hex );
		}

		return $properties;
	}

		/**
		 * Injects resolved properties on the frontend :root.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function inject_frontend_term_color_properties(): void {
		$css = Helpers::build_css_block(
			':root',
			$this->build_flavor_properties( $this->resolve_contextual_term_colors() )
		);

		if ( $css ) {
			wp_add_inline_style( 'global-styles', $css );
		}
	}

		/**
		 * Replaces abstract palette entries with resolved hex values in the editor.
		 *
		 * @since  0.1.0
		 * @param  WP_Theme_JSON_Data $theme_json The theme.json data object.
		 * @return WP_Theme_JSON_Data Modified theme.json data.
		 */
	public function inject_editor_term_color_tokens( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
		$post_id = $this->get_editor_post_id();

		if ( ! $post_id ) {
			return $theme_json;
		}

		$colors = $this->resolve_term_colors_for_post( $post_id );
		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();

		$new_items = array_map(
			function ( array $slot ) use ( $colors ): array { // phpcs:ignore Universal.FunctionDeclarations.NoLongClosures.ExceedsRecommended
				$slug = sanitize_key( $slot['slug'] );
				return array(
					'slug'  => $slug,
					'color' => isset( $colors[ $slug ] ) ? $colors[ $slug ] : ( sanitize_hex_color( $slot['fallback'] ) ?? $slot['fallback'] ),
					'name'  => $slot['name'],
				);
			},
			$slots
		);

		return Helpers::merge_palette_into_theme_json( $theme_json, $new_items );
	}

		/**
		 * Injects resolved properties into the editor iframe.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function inject_editor_term_color_styles(): void {
		$post_id = $this->get_editor_post_id();

		if ( ! $post_id ) {
			return;
		}

		$css = Helpers::build_css_block(
			'body',
			$this->build_flavor_properties( $this->resolve_term_colors_for_post( $post_id ) )
		);

		if ( $css ) {
			wp_add_inline_style( 'wp-edit-blocks', $css );
		}
	}
}
