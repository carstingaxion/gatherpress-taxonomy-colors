<?php
/**
 * Shared utility methods used across multiple layers: taxonomy slug
 * normalization, CSS block generation, palette merging, color role
 * retrieval, and per-taxonomy term color resolution.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.1
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use WP_Post;
use WP_Term;
use WP_Theme_JSON_Data;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Static helper methods shared across plugin classes.
 *
 * @since 0.1.1
 */
class Helpers {

		/**
		 * Cached color roles array.
		 *
		 * @since 0.2.0
		 * @var list<array{slug: string, label: string, meta_key: string}>|null
		 */
	private static ?array $color_roles_cache = null;

		/**
		 * Returns the registered color roles — the single source of truth
		 * for how many (and which) color slots exist per taxonomy term.
		 *
		 * Each role is an associative array with keys:
		 * - slug     (string) Unique identifier, e.g. 'primary', 'secondary', 'accent'.
		 * - label    (string) Human-readable label, e.g. 'Primary'.
		 * - meta_key (string) Term meta key, e.g. 'term_color', 'term_color_secondary'.
		 *
		 * @since  0.2.0
		 * @return array<int, array{slug: string, label: string, meta_key: string}>
		 */
	public static function get_color_roles(): array {
		if ( null !== self::$color_roles_cache ) {
			return self::$color_roles_cache;
		}

		$defaults = array(
			array(
				'slug'     => 'primary',
				'label'    => __( 'Primary', 'gatherpress-taxonomy-colors' ),
				'meta_key' => 'term_color', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			),
			array(
				'slug'     => 'secondary',
				'label'    => __( 'Secondary', 'gatherpress-taxonomy-colors' ),
				'meta_key' => 'term_color_secondary', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			),
		);
		/**
		 * Type safety
		 *
		 * @var array<int, array{slug: string, label: string, meta_key: string}> $roles
		 */
		/**
		 * Filters the term color roles (or amount of colors).
		 *
		 * This is the single source of truth for
		 * which color slots are available per taxonomy term.
		 *
		 * Each role is an associative array with three required keys:
		 *
		 * - **`slug`** *(string)* — Unique identifier used in CSS custom property
		 *   names and design token slugs. Must be a valid CSS identifier fragment
		 *   (lowercase, hyphens allowed). Examples: `'primary'`, `'accent-dark'`.
		 * - **`label`** *(string)* — Human-readable label shown in the admin color
		 *   picker fields, list table swatch tooltips, and the editor sidebar
		 *   panel. Translatable.
		 * - **`meta_key`** *(string)* — The `term_meta` key used to store and
		 *   retrieve the hex color value. Must be unique across all roles.
		 *   Registered automatically via `register_term_meta()` with
		 *   `sanitize_hex_color` and `show_in_rest`.
		 *
		 * The default roles are `primary` (`term_color`) and `secondary`
		 * (`term_color_secondary`). Every layer in the architecture derives
		 * its behavior from this filter:
		 *
		 * - **Layer 1** registers one meta key per role per taxonomy.
		 * - **Layer 2** generates one design token slot per role per taxonomy.
		 * - **Layers 3–5** resolve and inject `--flavor--{taxonomy}-{role}` CSS
		 *   custom properties.
		 * - **Admin UI** renders one color picker field per role on term edit
		 *   screens and one swatch per role in list table columns.
		 * - **Shadow panel (JS)** renders one color row per role dynamically.
		 *
		 * Roles are validated after filtering: entries missing any of the three
		 * required keys are silently dropped. Values are sanitized via
		 * `sanitize_key()` (slug, meta_key) and `sanitize_text_field()` (label).
		 *
		 * @since 0.2.0
		 *
		 * @param array<int, array{slug: string, label: string, meta_key: string}> $roles {
		 *     Array of color role definitions.
		 *
		 *     @type string $slug     Unique role identifier for CSS and token slugs.
		 *     @type string $label    Human-readable label for admin UI and editor.
		 *     @type string $meta_key Term meta key for storing the hex color value.
		 * }
		 *
		 * @example
		 * ```php
		 * // Add an "accent" and "accent-dark" role to every taxonomy term.
		 * add_filter( 'gptc_term_color_roles', function ( array $roles ): array {
		 *     $roles[] = array(
		 *         'slug'     => 'accent',
		 *         'label'    => __( 'Accent', 'my-theme' ),
		 *         'meta_key' => 'term_color_accent',
		 *     );
		 *     $roles[] = array(
		 *         'slug'     => 'accent-dark',
		 *         'label'    => __( 'Accent Dark', 'my-theme' ),
		 *         'meta_key' => 'term_color_accent_dark',
		 *     );
		 *     return $roles;
		 * } );
		 * ```
		 *
		 * @example
		 * ```php
		 * // Replace the defaults entirely with a single "base" role.
		 * add_filter( 'gptc_term_color_roles', function (): array {
		 *     return array(
		 *         array(
		 *             'slug'     => 'base',
		 *             'label'    => __( 'Base', 'my-theme' ),
		 *             'meta_key' => 'term_color_base',
		 *         ),
		 *     );
		 * } );
		 * ```
		 */
		$roles = (array) apply_filters( 'gptc_term_color_roles', $defaults );

		// Validate and normalize.
		$validated = array();
		foreach ( $roles as $role ) {
			if (
				! empty( $role['slug'] ) &&
				! empty( $role['label'] ) &&
				! empty( $role['meta_key'] )
			) {
				$validated[] = array(
					'slug'     => sanitize_key( $role['slug'] ),
					'label'    => sanitize_text_field( $role['label'] ),
					'meta_key' => sanitize_key( $role['meta_key'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);
			}
		}

		self::$color_roles_cache = $validated;

		return $validated;
	}

		/**
		 * Returns just the meta keys from the registered color roles.
		 *
		 * @since  0.2.0
		 * @return array<int, string>
		 */
	public static function get_color_meta_keys(): array {
		return array_column( self::get_color_roles(), 'meta_key' );
	}

		/**
		 * Normalizes a taxonomy slug for use in CSS custom property names.
		 *
		 * Replaces underscores with hyphens and sanitizes.
		 *
		 * @since  0.1.1
		 * @param  string $taxonomy Raw taxonomy slug.
		 * @return string Normalized slug safe for CSS identifiers.
		 */
	public static function normalize_taxonomy_slug( string $taxonomy ): string {
		$taxonomy = ltrim( $taxonomy, '_' ); // Remove leading underscore for shadow taxonomies.
		return str_replace( '_', '-', sanitize_key( $taxonomy ) );
	}

		/**
		 * Builds a CSS rule block string from a selector and property map.
		 *
		 * @since  0.1.1
		 * @param  string                $selector CSS selector (e.g. ':root', 'body').
		 * @param  array<string, string> $properties Map of property name to value.
		 * @return string Complete CSS rule block.
		 */
	public static function build_css_block( string $selector, array $properties ): string {
		if ( empty( $properties ) ) {
			return '';
		}

		$css = $selector . ' {' . PHP_EOL;

		foreach ( $properties as $prop => $value ) {
			$css .= sprintf( '    %s: %s;%s', $prop, $value, PHP_EOL );
		}

		$css .= '}';

		return $css;
	}

		/**
		 * Merges palette entries into a WP_Theme_JSON_Data object by slug.
		 *
		 * Indexes existing palette entries by slug, overwrites with new
		 * entries sharing the same slug, and returns the updated object.
		 *
		 * @since  0.1.1
		 * @param  WP_Theme_JSON_Data                                           $theme_json  Theme JSON data.
		 * @param  array<int, array{slug: string, color: string, name: string}> $new_items   Palette entries to merge.
		 * @return WP_Theme_JSON_Data Modified theme JSON data.
		 */
	public static function merge_palette_into_theme_json( WP_Theme_JSON_Data $theme_json, array $new_items ): WP_Theme_JSON_Data {
		if ( empty( $new_items ) ) {
			return $theme_json;
		}

		/**
		 * Type safety
		 *
		 * @var array<string, mixed> $existing_data
		 */
		$existing_data    = $theme_json->get_data();
		$settings         = is_array( $existing_data['settings'] ?? null ) ? $existing_data['settings'] : array();
		$color            = is_array( $settings['color'] ?? null ) ? $settings['color'] : array();
		$palette_data     = is_array( $color['palette'] ?? null ) ? $color['palette'] : array();
		$existing_palette = is_array( $palette_data['theme'] ?? null ) ? $palette_data['theme'] : array();
		$indexed          = array();
		foreach ( $existing_palette as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'] ) && is_string( $entry['slug'] ) ) {
				$indexed[ $entry['slug'] ] = $entry;
			}
		}
		foreach ( $new_items as $item ) {
			$indexed[ $item['slug'] ] = $item;
		}

		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'color' => array(
						'palette' => array(
							'theme' => array_values( $indexed ),
						),
					),
				),
			)
		);
	}

		/**
		 * Resolves term colors for a single taxonomy from a set of terms.
		 *
		 * Returns all configured color roles from the first term that has
		 * at least a primary-role color set — either directly or inherited
		 * from an ancestor in a hierarchical taxonomy.
		 *
		 * @since  0.1.1
		 * @param  WP_Term[] $terms          Array of term objects.
		 * @param  string    $normalized_tax Normalized taxonomy slug.
		 * @return array<string, string> Slot slug to hex color map (may be empty).
		 */
	public static function resolve_colors_from_terms( array $terms, string $normalized_tax ): array {
		$colors = array();
		$roles  = self::get_color_roles();

		if ( empty( $roles ) ) {
			return $colors;
		}

		// The first role is considered the "primary" gatekeeper —
		// a term must have it (directly or inherited) to be used.
		$primary_role = $roles[0];

		foreach ( $terms as $term ) {
			$primary_color = self::get_inherited_term_color( $term, $primary_role['meta_key'] );

			if ( ! $primary_color ) {
				continue;
			}

			// Resolve all roles for this term.
			foreach ( $roles as $role ) {
				$value = self::get_inherited_term_color( $term, $role['meta_key'] );

				if ( $value ) {
					$sanitized = sanitize_hex_color( $value );
					if ( null !== $sanitized ) {
						$colors[ $normalized_tax . '-' . $role['slug'] ] = $sanitized;
					}
				}
			}

			break; // First term with a primary color wins.
		}

		return $colors;
	}

		/**
		 * Retrieves a term meta value, walking up the parent chain for
		 * hierarchical taxonomies when the immediate term has no value.
		 *
		 * For non-hierarchical taxonomies (e.g. post_tag) or terms with
		 * no parent, this falls back immediately. A depth limit of 10
		 * guards against malformed data causing infinite loops.
		 *
		 * @since  0.1.4
		 * @param  WP_Term $term     The starting term.
		 * @param  string  $meta_key The meta key to look up.
		 * @return string The meta value (hex color) or empty string.
		 */
	public static function get_inherited_term_color( WP_Term $term, string $meta_key ): string {
		// Check the term itself first.
		$value = get_term_meta( $term->term_id, $meta_key, true );

		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		// Only walk ancestors for hierarchical taxonomies.
		$taxonomy_object = get_taxonomy( $term->taxonomy );

		if ( ! $taxonomy_object || ! $taxonomy_object->hierarchical ) {
			return '';
		}

		// Walk up the parent chain with a depth limit.
		$parent_id = (int) $term->parent;
		$depth     = 0;
		$max_depth = 10;

		while ( $parent_id > 0 && $depth < $max_depth ) {
			$parent_term = get_term( $parent_id, $term->taxonomy );

			if ( ! $parent_term instanceof WP_Term ) {
				break;
			}

			$parent_value = get_term_meta( $parent_term->term_id, $meta_key, true );

			if ( is_string( $parent_value ) && '' !== $parent_value ) {
				return $parent_value;
			}

			$parent_id = (int) $parent_term->parent;
			++$depth;
		}

		return '';
	}

		/**
		 * Resolves term colors across all color-enabled taxonomies for a post.
		 *
		 * For shadow taxonomies where the post IS the shadow source,
		 * uses GatherPress's helper to derive the shadow term directly.
		 * For all other taxonomies (including consumer-side shadow term
		 * assignments), uses the standard get_the_terms() path.
		 *
		 * @since  0.1.1
		 * @param  int $post_id The post ID.
		 * @return array<string, string> Slot slug to hex color map.
		 */
	public static function resolve_all_taxonomy_colors_for_post( int $post_id ): array {
		$colors     = array();
		$taxonomies = Plugin::get_instance()->get_color_taxonomies();
		$post       = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return $colors;
		}

		$shadow_support = Shadow_Taxonomy_Support::get_instance();

		foreach ( $taxonomies as $taxonomy ) {
			$normalized_tax = self::normalize_taxonomy_slug( $taxonomy );

			// Shadow-source path: the post IS the shadow source for this taxonomy.
			if (
				0 === strpos( $taxonomy, '_' ) &&
				$shadow_support->is_shadow_source_post_type( $post->post_type ) &&
				$shadow_support->get_shadow_taxonomy_for_post_type( $post->post_type ) === $taxonomy
			) {
				$shadow_term = $shadow_support->resolve_shadow_term( $post, $taxonomy );

				if ( $shadow_term ) {
					$resolved = self::resolve_colors_from_terms( array( $shadow_term ), $normalized_tax );
					$colors   = array_merge( $colors, $resolved );
				}

				continue;
			}

			// Standard path: get_the_terms() — works for regular taxonomies
			// and consumer-side shadow term assignments.
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}

			$resolved = self::resolve_colors_from_terms( $terms, $normalized_tax );
			$colors   = array_merge( $colors, $resolved );
		}

		return $colors;
	}
}
