<?php
/**
 * Layer 2 — Design Token Slot Definitions and theme.json Palette Integration.
 *
 * Generates one CSS custom property slot per color role per taxonomy,
 * injects those slots into the theme.json palette, and outputs the
 * :root / body preset override rules that map --wp--preset--color--*
 * to the intermediate --flavor--* properties.
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
 * Singleton managing design token definitions and theme.json integration.
 *
 * @since 0.1.0
 */
class Term_Color_Tokens {

	use Core\Traits\Singleton;


		/**
		 * Private constructor — registers palette and CSS override hooks.
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
		add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_term_color_design_tokens' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_preset_custom_property_overrides' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'inject_editor_preset_overrides' ) );
	}

		/**
		 * Returns the abstract term color slot definitions — one slot per
		 * role per taxonomy.
		 *
		 * Only includes slots for taxonomies that are currently registered.
		 *
		 * @since  0.1.0
		 * @return array<int, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}>
		 */
	public function get_term_color_slots(): array {
		$taxonomies = Plugin::get_instance()->get_color_taxonomies();
		$roles      = Helpers::get_color_roles();
		$slots      = array();

		// Base fallback hues per taxonomy index for deterministic neutral colors.
		$base_hues = array( 30, 200, 100, 300, 50, 180 );

		foreach ( $taxonomies as $tax_index => $taxonomy ) {
			$tax_object = get_taxonomy( $taxonomy );

			if ( ! $tax_object ) {
				continue;
			}

			$tax_label      = $tax_object->labels->singular_name;
			$normalized_tax = Helpers::normalize_taxonomy_slug( $taxonomy );

			// Determine a base hue for this taxonomy.
			if ( isset( $base_hues[ $tax_index ] ) ) {
				$base_hue = $base_hues[ $tax_index ];
			} else {
				$base_hue = abs( crc32( $taxonomy ) ) % 360;
			}

			foreach ( $roles as $role_index => $role ) {
				// Generate a unique muted fallback per role.
				// Primary roles get slightly more saturated/darker fallbacks.
				$saturation = max( 8, 15 - ( $role_index * 3 ) );
				$lightness  = min( 75, 50 + ( $role_index * 10 ) );
				$fallback   = self::hsl_to_hex( $base_hue, $saturation, $lightness );

				$slots[] = array(
					'slug'     => $normalized_tax . '-' . $role['slug'],
					'name'     => sprintf(
						/* translators: 1: taxonomy label, 2: role label (Primary/Secondary/etc.) */
						__( '%1$s Color (%2$s)', 'gatherpress-taxonomy-colors' ),
						$tax_label,
						$role['label']
					),
					'property' => '--flavor--' . $normalized_tax . '-' . $role['slug'],
					'fallback' => $fallback,
					'taxonomy' => $taxonomy,
					'meta_key' => $role['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);
			}
		}

				/**
	 * Filters the term color design token slot definitions.
	 *
	 * Each slot represents one entry in the `theme.json` color palette
	 * and one CSS custom property pair (`--wp--preset--color--{slug}` and
	 * `--flavor--{slug}`). Slots are generated automatically from the
	 * cartesian product of **color-enabled taxonomies**
	 * (`gptc_term_color_taxonomies`) × **color roles**
	 * (`gptc_term_color_roles`).
	 *
	 * Use this filter to:
	 *
	 * - **Add** extra slots beyond what the automatic generation provides
	 *   (e.g., a composite "brand" slot that merges multiple taxonomies).
	 * - **Remove** specific slots for taxonomies that should not appear in
	 *   the editor palette.
	 * - **Change fallback colors** to match your theme's neutral palette.
	 * - **Rename** slot labels for editor UX clarity.
	 *
	 * Each slot array contains:
	 *
	 * - **`slug`** *(string)* — Unique identifier used as the `theme.json`
	 *   palette slug and in the CSS custom property name. Format:
	 *   `{normalized-taxonomy}-{role-slug}`, e.g. `category-primary`.
	 * - **`name`** *(string)* — Human-readable label shown in the editor
	 *   color picker, e.g. "Category Color (Primary)".
	 * - **`property`** *(string)* — The intermediate CSS custom property
	 *   name, e.g. `--flavor--category-primary`. This is the property
	 *   that contextual resolution (Layers 3–5) sets to the actual hex.
	 * - **`fallback`** *(string)* — Hex color used as the `theme.json`
	 *   palette `color` value and as the `var()` fallback in the CSS
	 *   override. Shown when no term color resolves for the context.
	 * - **`taxonomy`** *(string)* — The raw taxonomy slug this slot
	 *   belongs to. Used for resolution traceability.
	 * - **`meta_key`** *(string)* — The term meta key that supplies the
	 *   actual color value, e.g. `term_color`.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}> $slots {
	 *     Array of design token slot definitions.
	 *
	 *     @type string $slug     Palette slug / CSS identifier.
	 *     @type string $name     Human-readable label for the editor.
	 *     @type string $property Intermediate CSS custom property name.
	 *     @type string $fallback Hex color fallback value.
	 *     @type string $taxonomy Raw taxonomy slug.
	 *     @type string $meta_key Term meta key for the color value.
	 * }
	 *
	 * @example
	 * ```php
	 * // Change the fallback color for all category slots to a custom neutral.
	 * add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
	 *     foreach ( $slots as &$slot ) {
	 *         if ( 'category' === $slot['taxonomy'] ) {
	 *             $slot['fallback'] = '#cccccc';
	 *         }
	 *     }
	 *     return $slots;
	 * } );
	 * ```
	 *
	 * @example
	 * ```php
	 * // Remove all tag slots from the palette (keep only category slots).
	 * add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
	 *     return array_values( array_filter( $slots, function ( $slot ) {
	 *         return 'post_tag' !== $slot['taxonomy'];
	 *     } ) );
	 * } );
	 * ```
	 *
	 * @example
	 * ```php
	 * // Add a custom composite slot that doesn't map to a specific taxonomy.
	 * add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
	 *     $slots[] = array(
	 *         'slug'     => 'brand-highlight',
	 *         'name'     => __( 'Brand Highlight', 'my-theme' ),
	 *         'property' => '--flavor--brand-highlight',
	 *         'fallback' => '#ff6600',
	 *         'taxonomy' => '',
	 *         'meta_key' => '',
	 *     );
	 *     return $slots;
	 * } );
	 * ```
	 */
		return (array) apply_filters( 'gptc_term_color_slots', $slots );
	}

		/**
		 * Injects term color design tokens into the theme.json palette.
		 *
		 * @since  0.1.0
		 * @param  \WP_Theme_JSON_Data $theme_json The theme.json data object.
		 * @return \WP_Theme_JSON_Data Modified theme.json data.
		 */
	public function inject_term_color_design_tokens( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
		$new_items = array_map(
			function ( array $slot ): array {
				return array(
					'slug'  => sanitize_key( $slot['slug'] ),
					'color' => sanitize_hex_color( $slot['fallback'] ) ?? '#888888',
					'name'  => $slot['name'],
				);
			},
			$this->get_term_color_slots()
		);
		return Helpers::merge_palette_into_theme_json( $theme_json, $new_items );
	}

		/**
		 * Builds CSS override properties mapping preset slugs to flavor vars.
		 *
		 * @since  0.1.1
		 * @return array<string, string> CSS property name to value map.
		 */
	private function build_preset_override_properties(): array {
		$properties = array();

		foreach ( $this->get_term_color_slots() as $slot ) {
			$prop_name                = '--wp--preset--color--' . esc_attr( $slot['slug'] );
			$prop_value               = sprintf(
				'var(%s, %s)',
				$slot['property'],
				esc_attr( sanitize_hex_color( $slot['fallback'] ) ?? '#888888' )
			);
			$properties[ $prop_name ] = $prop_value;
		}

		return $properties;
	}

		/**
		 * Injects CSS overrides on the frontend.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function inject_preset_custom_property_overrides(): void {
		$css = Helpers::build_css_block( ':root', $this->build_preset_override_properties() );

		if ( $css ) {
			wp_add_inline_style( 'global-styles', $css );
		}
	}

		/**
		 * Injects CSS overrides in the block editor.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function inject_editor_preset_overrides(): void {
		$css = Helpers::build_css_block( 'body', $this->build_preset_override_properties() );

		if ( $css ) {
			wp_add_inline_style( 'wp-edit-blocks', $css );
		}
	}

		/**
		 * Converts HSL values to a hex color string.
		 *
		 * @since  0.1.0
		 * @param  int $hue        Hue angle (0-360).
		 * @param  int $saturation Saturation percentage (0-100).
		 * @param  int $lightness  Lightness percentage (0-100).
		 * @return string Hex color string.
		 */
	public static function hsl_to_hex( int $hue, int $saturation, int $lightness ): string {
		$h = $hue / 360;
		$s = $saturation / 100;
		$l = $lightness / 100;

		if ( 0.0 === $s ) {
			$r = $g = $b = $l;
		} else {
			$q = $l < 0.5
				? $l * ( 1 + $s )
				: $l + $s - $l * $s;
			$p = 2 * $l - $q;

			$r = self::hue_to_rgb( $p, $q, $h + 1 / 3 );
			$g = self::hue_to_rgb( $p, $q, $h );
			$b = self::hue_to_rgb( $p, $q, $h - 1 / 3 );
		}

		return sprintf(
			'#%02x%02x%02x',
			(int) round( $r * 255 ),
			(int) round( $g * 255 ),
			(int) round( $b * 255 )
		);
	}

		/**
		 * Helper for HSL-to-RGB conversion.
		 *
		 * @since  0.1.0
		 * @param  float $p Lower bound.
		 * @param  float $q Upper bound.
		 * @param  float $t Hue offset.
		 * @return float Channel value (0-1).
		 */
	private static function hue_to_rgb( float $p, float $q, float $t ): float {
		if ( $t < 0 ) {
			$t += 1;
		}
		if ( $t > 1 ) {
			$t -= 1;
		}
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	}
}
