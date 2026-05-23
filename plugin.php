<?php
/**
 * Plugin Name:       GatherPress Taxonomy Colors
 * Description:       Assign colors to taxonomy terms and use them as native design tokens in the block editor — resolved contextually per post, per archive, and per Query Loop item.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            GatherPress
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-taxonomy-colors
 *
 * @package GatherpressTaxonomyColors
 */

namespace GatherpressTaxonomyColors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ====================================================================
 * Trait: Singleton
 *
 * Eliminates repeated $instance / get_instance / __clone / __wakeup
 * boilerplate across every class in the plugin.
 *
 * @since 0.1.1
 * ==================================================================== */

if ( ! trait_exists( __NAMESPACE__ . '\\Singleton' ) ) {

	/**
	 * Reusable singleton boilerplate.
	 *
	 * @since 0.1.1
	 */
	trait Singleton {

		/**
		 * @since 0.1.1
		 * @var static|null
		 */
		private static $instance = null;

		/**
		 * Returns the singleton instance.
		 *
		 * @since  0.1.1
		 * @return static
		 */
		public static function get_instance(): static {
			if ( null === static::$instance ) {
				static::$instance = new static();
			}
			return static::$instance;
		}

		/** @since 0.1.1 */
		private function __clone() {}

		/**
		 * @since  0.1.1
		 * @throws \RuntimeException Always.
		 * @return void
		 */
		public function __wakeup(): void {
			throw new \RuntimeException(
				esc_html__( 'Cannot unserialize a Singleton.', 'gatherpress-taxonomy-colors' )
			);
		}
	}
}

/* ====================================================================
 * Class: Helpers
 *
 * Shared utility methods used across multiple layers: taxonomy slug
 * normalization, CSS block generation, palette merging, and per-
 * taxonomy term color resolution.
 *
 * @since 0.1.1
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Helpers' ) ) {

	/**
	 * Static helper methods shared across plugin classes.
	 *
	 * @since 0.1.1
	 */
	final class Helpers {

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
		 * @param  string               $selector CSS selector (e.g. ':root', 'body').
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
		 * @param  \WP_Theme_JSON_Data                       $theme_json  Theme JSON data.
		 * @param  array<int, array{slug: string, color: string, name: string}> $new_items   Palette entries to merge.
		 * @return \WP_Theme_JSON_Data Modified theme JSON data.
		 */
		public static function merge_palette_into_theme_json( \WP_Theme_JSON_Data $theme_json, array $new_items ): \WP_Theme_JSON_Data {
			if ( empty( $new_items ) ) {
				return $theme_json;
			}

			$existing_data    = $theme_json->get_data();
			$existing_palette = $existing_data['settings']['color']['palette'] ?? array();

			$indexed = array();
			foreach ( $existing_palette as $entry ) {
				if ( isset( $entry['slug'] ) ) {
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
							'palette' => array_values( $indexed ),
						),
					),
				)
			);
		}

		/**
		 * Resolves term colors for a single taxonomy from a set of terms.
		 *
		 * Returns the primary and (optionally) secondary color from the
		 * first term that has a primary color set — either directly or
		 * inherited from an ancestor in a hierarchical taxonomy.
		 *
		 * @since  0.1.1
		 * @param  \WP_Term[] $terms          Array of term objects.
		 * @param  string     $normalized_tax Normalized taxonomy slug.
		 * @return array<string, string> Slot slug to hex color map (may be empty).
		 */
		public static function resolve_colors_from_terms( array $terms, string $normalized_tax ): array {
			$colors = array();

			foreach ( $terms as $term ) {
				$primary_color = self::get_inherited_term_color( $term, 'term_color' );

				if ( ! $primary_color ) {
					continue;
				}

				$colors[ $normalized_tax . '-primary' ] = sanitize_hex_color( $primary_color );

				$secondary_color = self::get_inherited_term_color( $term, 'term_color_secondary' );
				if ( $secondary_color ) {
					$colors[ $normalized_tax . '-secondary' ] = sanitize_hex_color( $secondary_color );
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
		 * @param  \WP_Term $term     The starting term.
		 * @param  string   $meta_key The meta key to look up.
		 * @return string The meta value (hex color) or empty string.
		 */
		public static function get_inherited_term_color( \WP_Term $term, string $meta_key ): string {
			// Check the term itself first.
			$value = get_term_meta( $term->term_id, $meta_key, true );

			if ( $value ) {
				return (string) $value;
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

				if ( ! $parent_term instanceof \WP_Term ) {
					break;
				}

				$parent_value = get_term_meta( $parent_term->term_id, $meta_key, true );

				if ( $parent_value ) {
					return (string) $parent_value;
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

			if ( ! $post instanceof \WP_Post ) {
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
}

/* ====================================================================
 * Class: Term_Color_Meta
 *
 * Responsibility: Layer 1 — Term Meta for Color Storage.
 *
 * @since 0.1.0
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Meta' ) ) {

	/**
	 * Singleton managing term color meta registration and admin UI.
	 *
	 * @since 0.1.0
	 */
	final class Term_Color_Meta {

		use Singleton;

		/**
		 * Private constructor — registers all admin-side hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_term_color_meta' ) );
			add_action( 'init', array( $this, 'register_colors_updated_meta' ) );
			add_action( 'init', array( $this, 'register_term_color_hooks' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_term_color_assets' ) );
		}

		/**
		 * Registers `term_color` and `term_color_secondary` meta keys
		 * for each supported taxonomy.
		 *
		 * @since  0.1.0
		 * @return void
		 */
		public function register_term_color_meta(): void {
			$taxonomies = Plugin::get_instance()->get_color_taxonomies();
			$meta_keys  = array( 'term_color', 'term_color_secondary' );

			foreach ( $taxonomies as $taxonomy ) {
				foreach ( $meta_keys as $meta_key ) {
					register_term_meta(
						$taxonomy,
						$meta_key,
						array(
							'type'              => 'string',
							'single'            => true,
							'show_in_rest'      => true,
							'sanitize_callback' => 'sanitize_hex_color',
							'default'           => '',
						)
					);
				}
			}
		}

		/**
		 * Registers the _gptc_colors_updated post meta used to dirty
		 * the editor when shadow term colors change.
		 *
		 * The meta value is a timestamp string and carries no semantic
		 * meaning — it exists solely to make the editor detect a change
		 * so the Save button activates after a shadow color edit.
		 *
		 * @since  0.1.6
		 * @return void
		 */
		public function register_colors_updated_meta(): void {
			register_post_meta( '', '_gptc_colors_updated', array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}

		/**
		 * Renders the color picker fields on the "Add New Term" form.
		 *
		 * @since  0.1.0
		 * @param  string $taxonomy The taxonomy slug.
		 * @return void
		 */
		public function render_add_term_color_field( string $taxonomy ): void {
			wp_nonce_field( 'gptc_save_term_color', 'gptc_term_color_nonce' );
			$this->render_color_field_pair( 'div', '', '' );
		}

		/**
		 * Renders the color picker fields on the "Edit Term" form.
		 *
		 * @since  0.1.0
		 * @param  \WP_Term $term The term being edited.
		 * @return void
		 */
		public function render_edit_term_color_field( \WP_Term $term ): void {
			$color           = get_term_meta( $term->term_id, 'term_color', true );
			$color_secondary = get_term_meta( $term->term_id, 'term_color_secondary', true );
			wp_nonce_field( 'gptc_save_term_color', 'gptc_term_color_nonce' );
			$this->render_color_field_pair( 'tr', $color, $color_secondary );
		}

		/**
		 * Renders a pair of color picker fields (primary + secondary).
		 *
		 * Adapts the wrapper markup to either the "Add New" form (div)
		 * or the "Edit" form (table row).
		 *
		 * @since  0.1.1
		 * @param  string $wrapper 'div' for add-form, 'tr' for edit-form.
		 * @param  string $primary_value   Current primary hex value.
		 * @param  string $secondary_value Current secondary hex value.
		 * @return void
		 */
		private function render_color_field_pair( string $wrapper, string $primary_value, string $secondary_value ): void {
			$fields = array(
				array(
					'id'    => 'gptc-term-color',
					'name'  => 'term_color',
					'label' => __( 'Term Color (Primary)', 'gatherpress-taxonomy-colors' ),
					'desc'  => __( 'Primary color for this term. Available as the "Term Color (Primary)" design token in the block editor.', 'gatherpress-taxonomy-colors' ),
					'value' => $primary_value,
				),
				array(
					'id'    => 'gptc-term-color-secondary',
					'name'  => 'term_color_secondary',
					'label' => __( 'Term Color (Secondary)', 'gatherpress-taxonomy-colors' ),
					'desc'  => __( 'Secondary color for this term. Available as the "Term Color (Secondary)" design token in the block editor.', 'gatherpress-taxonomy-colors' ),
					'value' => $secondary_value,
				),
			);

			foreach ( $fields as $field ) {
				if ( 'tr' === $wrapper ) {
					?>
					<tr class="form-field">
						<th scope="row">
							<label for="<?php echo esc_attr( $field['id'] ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="<?php echo esc_attr( $field['id'] ); ?>"
								name="<?php echo esc_attr( $field['name'] ); ?>"
								value="<?php echo esc_attr( $field['value'] ); ?>"
								class="gptc-color-field"
								data-default-color=""
							/>
							<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
						</td>
					</tr>
					<?php
				} else {
					?>
					<div class="form-field">
						<label for="<?php echo esc_attr( $field['id'] ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( $field['id'] ); ?>"
							name="<?php echo esc_attr( $field['name'] ); ?>"
							value="<?php echo esc_attr( $field['value'] ); ?>"
							class="gptc-color-field"
							data-default-color=""
						/>
						<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
					</div>
					<?php
				}
			}
		}

		/**
		 * Saves term color meta on term creation.
		 *
		 * @since  0.1.0
		 * @param  int $term_id The newly created term ID.
		 * @return void
		 */
		public function save_term_color_on_create( int $term_id ): void {
			$this->save_term_color( $term_id );
		}

		/**
		 * Saves term color meta on term edit.
		 *
		 * @since  0.1.0
		 * @param  int $term_id The edited term ID.
		 * @return void
		 */
		public function save_term_color_on_edit( int $term_id ): void {
			$this->save_term_color( $term_id );
		}

		/**
		 * Shared save handler for both create and edit contexts.
		 *
		 * @since  0.1.0
		 * @param  int $term_id The term ID to save colors for.
		 * @return void
		 */
		private function save_term_color( int $term_id ): void {
			if (
				! isset( $_POST['gptc_term_color_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['gptc_term_color_nonce'] ) ),
					'gptc_save_term_color'
				)
			) {
				return;
			}

			if ( ! current_user_can( 'edit_term', $term_id ) ) {
				return;
			}

			$meta_keys = array( 'term_color', 'term_color_secondary' );

			foreach ( $meta_keys as $meta_key ) {
				$value = isset( $_POST[ $meta_key ] )
					? sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) )
					: '';

				update_term_meta( $term_id, $meta_key, $value );
			}
		}

		/**
		 * Registers dynamic taxonomy hooks for form fields, save
		 * handlers, and list table columns.
		 *
		 * @since  0.1.0
		 * @return void
		 */
		public function register_term_color_hooks(): void {
			$taxonomies = Plugin::get_instance()->get_color_taxonomies();

			foreach ( $taxonomies as $taxonomy ) {
				add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add_term_color_field' ) );
				add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit_term_color_field' ) );
				add_action( "created_{$taxonomy}", array( $this, 'save_term_color_on_create' ) );
				add_action( "edited_{$taxonomy}", array( $this, 'save_term_color_on_edit' ) );
				add_filter( "manage_edit-{$taxonomy}_columns", array( $this, 'add_term_color_column' ) );
				add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'render_term_color_column' ), 10, 3 );
			}
		}

		/**
		 * Adds a "Colors" column to the taxonomy term list table.
		 *
		 * @since  0.1.0
		 * @param  array<string, string> $columns Existing columns.
		 * @return array<string, string> Modified columns.
		 */
		public function add_term_color_column( array $columns ): array {
			$new_columns = array();

			foreach ( $columns as $key => $label ) {
				$new_columns[ $key ] = $label;

				if ( 'name' === $key ) {
					$new_columns['term_colors'] = __( 'Colors', 'gatherpress-taxonomy-colors' );
				}
			}

			if ( ! isset( $new_columns['term_colors'] ) ) {
				$new_columns['term_colors'] = __( 'Colors', 'gatherpress-taxonomy-colors' );
			}

			return $new_columns;
		}

		/**
		 * Renders color swatches in the list table's custom column.
		 *
		 * @since  0.1.0
		 * @param  string $content     Current column content.
		 * @param  string $column_name The column slug.
		 * @param  int    $term_id     The term ID for the current row.
		 * @return string HTML for the column cell.
		 */
		public function render_term_color_column( string $content, string $column_name, int $term_id ): string {
			if ( 'term_colors' !== $column_name ) {
				return $content;
			}

			$primary   = get_term_meta( $term_id, 'term_color', true );
			$secondary = get_term_meta( $term_id, 'term_color_secondary', true );

			$output  = '<span style="display:inline-flex;align-items:center;gap:6px;">';
			$output .= $this->render_color_swatch( $primary, __( 'Primary', 'gatherpress-taxonomy-colors' ) );
			$output .= $this->render_color_swatch( $secondary, __( 'Secondary', 'gatherpress-taxonomy-colors' ) );
			$output .= '</span>';

			return $output;
		}

		/**
		 * Renders a single circular color swatch.
		 *
		 * @since  0.1.0
		 * @param  string $hex_color Hex color value or empty string.
		 * @param  string $label     Accessible label (e.g. "Primary").
		 * @return string HTML for the swatch.
		 */
		private function render_color_swatch( string $hex_color, string $label ): string {
			$size = '24px';

			if ( $hex_color ) {
				return sprintf(
					'<span title="%s: %s" style="display:inline-block;width:%s;height:%s;border-radius:50%%;background:%s;box-shadow:inset 0 0 0 1px rgba(0,0,0,0.15);"></span>',
					esc_attr( $label ),
					esc_attr( $hex_color ),
					esc_attr( $size ),
					esc_attr( $size ),
					esc_attr( sanitize_hex_color( $hex_color ) )
				);
			}

			return sprintf(
				'<span title="%s: %s" style="display:inline-block;width:%s;height:%s;border-radius:50%%;background:#f0f0f0;box-shadow:inset 0 0 0 1px rgba(0,0,0,0.1);position:relative;overflow:hidden;">'
				. '<svg style="position:absolute;top:0;left:0;width:100%%;height:100%%;" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
				. '<line x1="4" y1="4" x2="20" y2="20" stroke="rgba(0,0,0,0.2)" stroke-width="2"/>'
				. '</svg>'
				. '</span>',
				esc_attr( $label ),
				esc_attr__( 'Not set', 'gatherpress-taxonomy-colors' ),
				esc_attr( $size ),
				esc_attr( $size )
			);
		}

		/**
		 * Enqueues wp-color-picker assets on term admin screens.
		 *
		 * @since  0.1.0
		 * @param  string $hook_suffix The current admin page hook suffix.
		 * @return void
		 */
		public function enqueue_term_color_assets( string $hook_suffix ): void {
			if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			wp_add_inline_script(
				'wp-color-picker',
				'jQuery( document ).ready( function( $ ) { $( ".gptc-color-field" ).wpColorPicker(); } );'
			);
		}
	}
}

/* ====================================================================
 * Class: Term_Color_Tokens
 *
 * Responsibility: Layer 2 — Design Token Slot Definitions and
 * theme.json Palette Integration.
 *
 * @since 0.1.0
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Tokens' ) ) {

	/**
	 * Singleton managing design token definitions and theme.json integration.
	 *
	 * @since 0.1.0
	 */
	final class Term_Color_Tokens {

		use Singleton;

		/**
		 * Private constructor — registers palette and CSS override hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_term_color_design_tokens' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'inject_preset_custom_property_overrides' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'inject_editor_preset_overrides' ) );
		}

		/**
		 * Returns the abstract term color slot definitions — one pair per taxonomy.
		 *
		 * @since  0.1.0
		 * @return array<int, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}>
		 */
		public function get_term_color_slots(): array {
			$taxonomies = Plugin::get_instance()->get_color_taxonomies();
			$slots      = array();

			$fallback_pairs = array(
				array( 'primary' => '#8b7e74', 'secondary' => '#b8aea6' ),
				array( 'primary' => '#6e7f8d', 'secondary' => '#a3b1bc' ),
				array( 'primary' => '#7b8471', 'secondary' => '#a9b2a1' ),
				array( 'primary' => '#8d7487', 'secondary' => '#b8a3b3' ),
				array( 'primary' => '#8a7d5a', 'secondary' => '#b5ac8e' ),
				array( 'primary' => '#5f7e8a', 'secondary' => '#96b1bc' ),
			);

			$roles = array(
				'primary'   => array( 'meta_key' => 'term_color' ),
				'secondary' => array( 'meta_key' => 'term_color_secondary' ),
			);

			foreach ( $taxonomies as $index => $taxonomy ) {
				$tax_object = get_taxonomy( $taxonomy );
				$tax_label  = $tax_object ? $tax_object->labels->singular_name : ucfirst( $taxonomy );

				if ( isset( $fallback_pairs[ $index ] ) ) {
					$fallbacks = $fallback_pairs[ $index ];
				} else {
					$hash     = abs( crc32( $taxonomy ) );
					$hue      = $hash % 360;
					$fallbacks = array(
						'primary'   => self::hsl_to_hex( $hue, 15, 50 ),
						'secondary' => self::hsl_to_hex( $hue, 12, 68 ),
					);
				}

				$normalized_tax = Helpers::normalize_taxonomy_slug( $taxonomy );

				foreach ( $roles as $role => $role_data ) {
					$slots[] = array(
						'slug'     => $normalized_tax . '-' . $role,
						'name'     => sprintf(
							/* translators: 1: taxonomy label, 2: role (Primary/Secondary) */
							__( '%1$s Color (%2$s)', 'gatherpress-taxonomy-colors' ),
							$tax_label,
							ucfirst( $role )
						),
						'property' => '--flavor--' . $normalized_tax . '-' . $role,
						'fallback' => $fallbacks[ $role ],
						'taxonomy' => $taxonomy,
						'meta_key' => $role_data['meta_key'],
					);
				}
			}
			/**
			 * Filters the term color design token slot definitions.
			 *
			 * @since 0.1.0
			 * @param array<int, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}> $slots
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
				// $prop_name  = '--wp--preset--color--' . esc_attr( sanitize_key( $slot['slug'] ) );
				$prop_name  = '--wp--preset--color--' . esc_attr( $slot['slug'] );
				$prop_value = sprintf(
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
}

/* ====================================================================
 * Class: Term_Color_Resolver
 *
 * Responsibility: Layers 3 & 4 — Contextual Color Resolution.
 *
 * @since 0.1.0
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Resolver' ) ) {

	/**
	 * Singleton managing contextual term color resolution and CSS injection.
	 *
	 * @since 0.1.0
	 */
	final class Term_Color_Resolver {

		use Singleton;

		/**
		 * Private constructor — registers frontend and editor hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
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

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; no state change.
			if ( ! empty( $_GET['post'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_GET['post'] );

				if ( $post_id > 0 ) {
					return $post_id;
				}
			}

			global $post;

			if ( $post instanceof \WP_Post && $post->ID > 0 ) {
				return (int) $post->ID;
			}

			return 0;
		}

		/**
		 * Resolves term colors for the current frontend context — per taxonomy.
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

				if ( $term instanceof \WP_Term ) {
					$normalized_tax = Helpers::normalize_taxonomy_slug( $term->taxonomy );
					return Helpers::resolve_colors_from_terms( array( $term ), $normalized_tax );
				}
			}

			return array();
		}

		/**
		 * Resolves term colors for a specific post ID — per taxonomy.
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
				// $properties[ '--flavor--' . esc_attr( ltrim( $slot, "-" ) ) ] = esc_attr( $hex );
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
		 * @param  \WP_Theme_JSON_Data $theme_json The theme.json data object.
		 * @return \WP_Theme_JSON_Data Modified theme.json data.
		 */
		public function inject_editor_term_color_tokens( \WP_Theme_JSON_Data $theme_json ): \WP_Theme_JSON_Data {
			$post_id = $this->get_editor_post_id();

			if ( ! $post_id ) {
				return $theme_json;
			}

			$colors = $this->resolve_term_colors_for_post( $post_id );
			$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();

			$new_items = array_map(
				function ( array $slot ) use ( $colors ): array {
					$slug = sanitize_key( $slot['slug'] );
					return array(
						'slug'  => $slug,
						'color' => isset( $colors[ $slug ] ) ? $colors[ $slug ] : sanitize_hex_color( $slot['fallback'] ),
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
}

/* ====================================================================
 * Class: Term_Color_Scoper
 *
 * Responsibility: Layer 5 — Query Loop Scoped Resolution.
 *
 * @since 0.1.0
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Scoper' ) ) {

	/**
	 * Singleton managing scoped per-post and per-term color injection.
	 *
	 * @since 0.1.0
	 */
	final class Term_Color_Scoper {

		use Singleton;

		/**
		 * Private constructor — registers render filters and block style.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_filter( 'render_block_core/post-template', array( $this, 'scope_term_colors_to_post_template' ), 10, 3 );
			add_action( 'init', array( $this, 'register_post_terms_block_style' ) );
			add_filter( 'render_block_core/post-terms', array( $this, 'inject_post_terms_color_properties' ), 10, 3 );
		}

		/**
		 * Builds scoped inline style declarations for a set of resolved colors.
		 *
		 * @since  0.1.0
		 * @param  array<string, string>                                           $colors      Resolved slot slug to hex map.
		 * @param  array<string, array{slug: string, property: string, fallback: string}> $slot_lookup Slot definitions keyed by slug.
		 * @return string Inline style declarations string.
		 */
		private function build_scoped_style_declarations( array $colors, array $slot_lookup ): string {
			$declarations = '';

			foreach ( $colors as $slot => $hex ) {
				$declarations .= sprintf(
					'--flavor--%s:%s;',
					esc_attr( $slot ),
					esc_attr( $hex )
				);

				if ( isset( $slot_lookup[ $slot ] ) ) {
					$declarations .= sprintf(
						'--wp--preset--color--%s:var(%s,%s);',
						esc_attr( sanitize_key( $slot ) ),
						$slot_lookup[ $slot ]['property'],
						esc_attr( $hex )
					);
				}
			}

			return $declarations;
		}

		/**
		 * Builds the slot lookup array keyed by slug from the token definitions.
		 *
		 * @since  0.1.0
		 * @return array<string, array{slug: string, property: string, fallback: string}>
		 */
		private function get_slot_lookup(): array {
			$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
			$lookup = array();

			foreach ( $slots as $slot_def ) {
				$lookup[ $slot_def['slug'] ] = $slot_def;
			}

			return $lookup;
		}

		/**
		 * Applies scoped style declarations to an HTML element via WP_HTML_Tag_Processor.
		 *
		 * @since  0.1.1
		 * @param  \WP_HTML_Tag_Processor $processor          Tag processor positioned on the target element.
		 * @param  string                 $style_declarations CSS custom property declarations to inject.
		 * @return void
		 */
		private function apply_scoped_styles( \WP_HTML_Tag_Processor $processor, string $style_declarations ): void {
			$current_style = $processor->get_attribute( 'style' );
			$new_style     = $current_style
				? $style_declarations . $current_style
				: $style_declarations;

			$processor->set_attribute( 'style', $new_style );
		}

		/**
		 * Injects scoped term color properties per post inside post-template.
		 *
		 * @since  0.1.0
		 * @param  string    $block_content Rendered post-template HTML.
		 * @param  array     $block         Parsed block array.
		 * @param  \WP_Block $instance      Block instance.
		 * @return string Modified block content.
		 */
		public function scope_term_colors_to_post_template( string $block_content, array $block, \WP_Block $instance ): string {
			$trimmed = trim( $block_content );
			if ( '' === $trimmed ) {
				return $block_content;
			}

			$resolver    = Term_Color_Resolver::get_instance();
			$slot_lookup = $this->get_slot_lookup();
			$processor   = new \WP_HTML_Tag_Processor( $block_content );

			while ( $processor->next_tag( array( 'tag_name' => 'LI', 'class_name' => 'wp-block-post' ) ) ) {
				$class_attr = $processor->get_attribute( 'class' );

				if ( ! $class_attr ) {
					continue;
				}

				$matches = array();
				if ( ! preg_match( '/\bpost-(\d+)\b/', $class_attr, $matches ) ) {
					continue;
				}

				$post_id = (int) $matches[1];

				if ( $post_id <= 0 ) {
					continue;
				}

				$colors = $resolver->resolve_term_colors_for_post( $post_id );

				if ( empty( $colors ) ) {
					continue;
				}

				$this->apply_scoped_styles(
					$processor,
					$this->build_scoped_style_declarations( $colors, $slot_lookup )
				);
			}

			return $processor->get_updated_html();
		}

		/**
		 * Registers the "Term Colors" block style for core/post-terms.
		 *
		 * @since  0.1.0
		 * @return void
		 */
		public function register_post_terms_block_style(): void {
			register_block_style(
				'core/post-terms',
				array(
					'name'         => 'term-colors',
					'label'        => __( 'Term Colors', 'gatherpress-taxonomy-colors' ),
					'inline_style' => '
.wp-block-post-terms.is-style-term-colors {
	/* Per-term --flavor-- and --wp--preset--color-- properties are injected per <a> via render filter. */
}',
				)
			);
		}

		/**
		 * Injects per-term color properties onto each <a> inside
		 * core/post-terms blocks using the "Term Colors" style.
		 *
		 * @since  0.1.0
		 * @param  string    $block_content Rendered block HTML.
		 * @param  array     $block         Parsed block array.
		 * @param  \WP_Block $instance      Block instance.
		 * @return string Modified block content.
		 */
		public function inject_post_terms_color_properties( string $block_content, array $block, \WP_Block $instance ): string {
			$class_name = $block['attrs']['className'] ?? '';

			if ( false === strpos( $class_name, 'is-style-term-colors' ) ) {
				return $block_content;
			}

			$taxonomy = $block['attrs']['term'] ?? 'category';
			$post_id  = $instance->context['postId'] ?? get_the_ID();

			if ( ! $post_id ) {
				return $block_content;
			}

			$post_terms = get_the_terms( $post_id, $taxonomy );

			if ( ! is_array( $post_terms ) || empty( $post_terms ) ) {
				return $block_content;
			}

			$normalized_tax = Helpers::normalize_taxonomy_slug( $taxonomy );
			$slot_lookup    = $this->get_slot_lookup();

			$term_color_map = array();

			foreach ( $post_terms as $term ) {
				$term_link = get_term_link( $term );

				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$primary   = get_term_meta( $term->term_id, 'term_color', true );
				$secondary = get_term_meta( $term->term_id, 'term_color_secondary', true );

				if ( ! $primary && ! $secondary ) {
					continue;
				}

				$parsed_path = wp_parse_url( $term_link, PHP_URL_PATH );
				$normal_key  = $parsed_path ? untrailingslashit( $parsed_path ) : untrailingslashit( $term_link );

				$term_color_map[ $normal_key ] = array(
					'primary'   => $primary ? sanitize_hex_color( $primary ) : '',
					'secondary' => $secondary ? sanitize_hex_color( $secondary ) : '',
				);
			}

			if ( empty( $term_color_map ) ) {
				return $block_content;
			}

			$processor = new \WP_HTML_Tag_Processor( $block_content );

			while ( $processor->next_tag( 'A' ) ) {
				$href = $processor->get_attribute( 'href' );

				if ( ! $href ) {
					continue;
				}

				$href_path  = wp_parse_url( $href, PHP_URL_PATH );
				$normal_key = $href_path ? untrailingslashit( $href_path ) : untrailingslashit( $href );

				if ( ! isset( $term_color_map[ $normal_key ] ) ) {
					continue;
				}

				$colors = $term_color_map[ $normal_key ];

				$resolved = array();

				if ( $colors['primary'] ) {
					$resolved[ $normalized_tax . '-primary' ] = $colors['primary'];
				}

				if ( $colors['secondary'] ) {
					$resolved[ $normalized_tax . '-secondary' ] = $colors['secondary'];
				}

				if ( empty( $resolved ) ) {
					continue;
				}

				$this->apply_scoped_styles(
					$processor,
					$this->build_scoped_style_declarations( $resolved, $slot_lookup )
				);
			}

			return $processor->get_updated_html();
		}
	}
}

/* ====================================================================
 * Class: Shadow_Taxonomy_Support
 *
 * Responsibility: Layer 6 — Shadow Taxonomy Support for Post Types.
 *
 * Detects shadow taxonomies from the color taxonomy filter, provides
 * helpers for resolving shadow terms, adds color picker metaboxes to
 * shadow-source post editors, and admin columns to their list tables.
 *
 * @since 0.1.2
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Shadow_Taxonomy_Support' ) ) {

	/**
	 * Singleton managing shadow taxonomy detection, admin UI, and resolution helpers.
	 *
	 * @since 0.1.2
	 */
	final class Shadow_Taxonomy_Support {

		use Singleton;

		/**
		 * Confirmed shadow-source post type slugs (e.g. 'venue', 'topic').
		 *
		 * @since 0.1.2
		 * @var array<string, string> Map of post type slug => shadow taxonomy slug.
		 */
		private array $shadow_source_post_types = array();

		/**
		 * Whether detection has run.
		 *
		 * @since 0.1.2
		 * @var bool
		 */
		private bool $detected = false;

		/**
		 * Private constructor — registers hooks.
		 *
		 * @since 0.1.2
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'detect_shadow_taxonomies' ), 25 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_shadow_config_script' ) );
			add_action( 'admin_init', array( $this, 'register_shadow_admin_columns' ) );
		}

		/**
		 * Detects shadow taxonomies from the color taxonomy filter.
		 *
		 * Iterates the filter return. For each slug starting with '_',
		 * derives the candidate post type slug and checks for existence
		 * and gatherpress-shadow-source support.
		 *
		 * @since  0.1.2
		 * @return void
		 */
		public function detect_shadow_taxonomies(): void {
			if ( $this->detected ) {
				return;
			}

			$this->detected = true;
			$taxonomies     = Plugin::get_instance()->get_color_taxonomies();

			foreach ( $taxonomies as $slug ) {
				if ( 0 !== strpos( $slug, '_' ) ) {
					continue;
				}

				$candidate_post_type = ltrim( $slug, '_' );

				if ( ! post_type_exists( $candidate_post_type ) ) {
					continue;
				}

				if ( ! post_type_supports( $candidate_post_type, 'gatherpress-shadow-source' ) ) {
					continue;
				}

				// Optional cross-check with GatherPress canonical helper.
				if (
					class_exists( '\\GatherPress\\Core\\Shadow_Source' ) &&
					method_exists( '\\GatherPress\\Core\\Shadow_Source', 'get_instance' )
				) {
					$shadow_source = \GatherPress\Core\Shadow_Source::get_instance();

					if ( method_exists( $shadow_source, 'is_shadow_term_slug' ) && ! $shadow_source->is_shadow_term_slug( $slug ) ) {
						continue;
					}
				}

				$this->shadow_source_post_types[ $candidate_post_type ] = $slug;
			}
		}

		/**
		 * Returns the map of confirmed shadow-source post type slugs to taxonomy slugs.
		 *
		 * @since  0.1.2
		 * @return array<string, string>
		 */
		public function get_shadow_source_post_types(): array {
			if ( ! $this->detected ) {
				$this->detect_shadow_taxonomies();
			}

			return $this->shadow_source_post_types;
		}

		/**
		 * Checks whether a post type is a confirmed shadow-source.
		 *
		 * @since  0.1.2
		 * @param  string $post_type The post type slug.
		 * @return bool
		 */
		public function is_shadow_source_post_type( string $post_type ): bool {
			return isset( $this->get_shadow_source_post_types()[ $post_type ] );
		}

		/**
		 * Returns the shadow taxonomy slug for a post type, or empty string.
		 *
		 * @since  0.1.2
		 * @param  string $post_type The post type slug.
		 * @return string Shadow taxonomy slug or empty string.
		 */
		public function get_shadow_taxonomy_for_post_type( string $post_type ): string {
			$map = $this->get_shadow_source_post_types();
			return $map[ $post_type ] ?? '';
		}

		/**
		 * Resolves the shadow WP_Term for a given post.
		 *
		 * Uses GatherPress's helper to derive the term slug from post name,
		 * then fetches the term object. Returns null if GatherPress is not
		 * active or the term doesn't exist.
		 *
		 * @since  0.1.2
		 * @param  \WP_Post $post             The source post.
		 * @param  string   $shadow_taxonomy  The shadow taxonomy slug.
		 * @return \WP_Term|null The shadow term, or null.
		 */
		public function resolve_shadow_term( \WP_Post $post, string $shadow_taxonomy ): ?\WP_Term {
			if (
				! class_exists( '\\GatherPress\\Core\\Shadow_Source' ) ||
				! method_exists( '\\GatherPress\\Core\\Shadow_Source', 'get_instance' )
			) {
				return null;
			}

			$shadow_source = \GatherPress\Core\Shadow_Source::get_instance();

			if ( ! method_exists( $shadow_source, 'term_slug_from_post_name' ) ) {
				return null;
			}

			$term_slug = $shadow_source->term_slug_from_post_name( $post->post_name );

			if ( empty( $term_slug ) ) {
				return null;
			}

			$term = get_term_by( 'slug', $term_slug, $shadow_taxonomy );

			return ( $term instanceof \WP_Term ) ? $term : null;
		}

		/**
		 * Resolves term colors for a shadow-source post.
		 *
		 * @since  0.1.2
		 * @param  int $post_id The post ID.
		 * @return array<string, string> Slot slug to hex color map (may be empty).
		 */
		public function resolve_shadow_colors_for_post( int $post_id ): array {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				return array();
			}

			$post_type       = $post->post_type;
			$shadow_taxonomy = $this->get_shadow_taxonomy_for_post_type( $post_type );

			if ( empty( $shadow_taxonomy ) ) {
				return array();
			}

			$term = $this->resolve_shadow_term( $post, $shadow_taxonomy );

			if ( ! $term ) {
				return array();
			}

			$normalized_tax = Helpers::normalize_taxonomy_slug( $shadow_taxonomy );
			return Helpers::resolve_colors_from_terms( array( $term ), $normalized_tax );
		}

		/**
		 * Enqueues the shadow taxonomy config as an inline script
		 * so the JS sidebar panel knows which post types are shadow sources.
		 *
		 * @since  0.1.3
		 * @return void
		 */
		public function enqueue_shadow_config_script(): void {
			$map = $this->get_shadow_source_post_types();

			if ( empty( $map ) ) {
				return;
			}

			// The editor script handle is generated from the block name.
			$handle = 'gatherpress-taxonomy-colors-editor-script';

			// Build a safe JSON map of post_type => taxonomy_slug.
			$json = wp_json_encode( $map );

			if ( false === $json ) {
				return;
			}

			wp_add_inline_script(
				$handle,
				sprintf( 'window.gptcShadowConfig = %s;', $json ),
				'before'
			);
		}

		/**
		 * Registers admin column hooks for shadow-source post types.
		 *
		 * @since  0.1.2
		 * @return void
		 */
		public function register_shadow_admin_columns(): void {
			foreach ( $this->get_shadow_source_post_types() as $post_type => $taxonomy ) {
				add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_shadow_color_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_shadow_color_column' ), 10, 2 );
			}
		}

		/**
		 * Adds a "Term Colors" column to shadow-source post type list tables.
		 *
		 * @since  0.1.2
		 * @param  array<string, string> $columns Existing columns.
		 * @return array<string, string> Modified columns.
		 */
		public function add_shadow_color_column( array $columns ): array {
			$new_columns = array();

			foreach ( $columns as $key => $label ) {
				$new_columns[ $key ] = $label;

				if ( 'title' === $key ) {
					$new_columns['gptc_term_colors'] = __( 'Term Colors', 'gatherpress-taxonomy-colors' );
				}
			}

			if ( ! isset( $new_columns['gptc_term_colors'] ) ) {
				$new_columns['gptc_term_colors'] = __( 'Term Colors', 'gatherpress-taxonomy-colors' );
			}

			return $new_columns;
		}

		/**
		 * Renders color swatches in the shadow-source post type list table column.
		 *
		 * @since  0.1.2
		 * @param  string $column_name The column slug.
		 * @param  int    $post_id     The post ID for the current row.
		 * @return void
		 */
		public function render_shadow_color_column( string $column_name, int $post_id ): void {
			if ( 'gptc_term_colors' !== $column_name ) {
				return;
			}

			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				return;
			}

			$shadow_taxonomy = $this->get_shadow_taxonomy_for_post_type( $post->post_type );

			if ( empty( $shadow_taxonomy ) ) {
				return;
			}

			$term = $this->resolve_shadow_term( $post, $shadow_taxonomy );

			$primary   = $term ? get_term_meta( $term->term_id, 'term_color', true ) : '';
			$secondary = $term ? get_term_meta( $term->term_id, 'term_color_secondary', true ) : '';

			$size = '20px';

			echo '<span style="display:inline-flex;align-items:center;gap:4px;">';
			echo $this->render_admin_swatch( $primary, __( 'Primary', 'gatherpress-taxonomy-colors' ), $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_admin_swatch.
			echo $this->render_admin_swatch( $secondary, __( 'Secondary', 'gatherpress-taxonomy-colors' ), $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_admin_swatch.
			echo '</span>';
		}

		/**
		 * Renders a single circular color swatch for admin columns.
		 *
		 * @since  0.1.2
		 * @param  string $hex_color Hex color or empty.
		 * @param  string $label     Accessible label.
		 * @param  string $size      CSS size value.
		 * @return string HTML swatch.
		 */
		private function render_admin_swatch( string $hex_color, string $label, string $size ): string {
			if ( $hex_color ) {
				return sprintf(
					'<span title="%s: %s" style="display:inline-block;width:%s;height:%s;border-radius:50%%;background:%s;box-shadow:inset 0 0 0 1px rgba(0,0,0,0.15);"></span>',
					esc_attr( $label ),
					esc_attr( $hex_color ),
					esc_attr( $size ),
					esc_attr( $size ),
					esc_attr( sanitize_hex_color( $hex_color ) )
				);
			}

			return sprintf(
				'<span title="%s: %s" style="display:inline-block;width:%s;height:%s;border-radius:50%%;background:#f0f0f0;box-shadow:inset 0 0 0 1px rgba(0,0,0,0.1);"></span>',
				esc_attr( $label ),
				esc_attr__( 'Not set', 'gatherpress-taxonomy-colors' ),
				esc_attr( $size ),
				esc_attr( $size )
			);
		}
	}
}

/* ====================================================================
 * Class: Plugin
 *
 * Responsibility: Slim orchestrator — registers the Gutenberg block
 * and bootstraps all sub-singletons.
 *
 * @since 0.1.0
 * ==================================================================== */

if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Main plugin orchestrator — Singleton.
	 *
	 * @since 0.1.0
	 */
	final class Plugin {

		use Singleton;

		/**
		 * Private constructor — registers the block and bootstraps
		 * all sub-singletons.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
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
			 * Filters the taxonomies that support term color meta.
			 *
			 * @since 0.1.0
			 * @param array<int, string> $taxonomies Default: category, post_tag.
			 */
			return (array) apply_filters(
				'gptc_term_color_taxonomies',
				array( '_gatherpress_play', 'post_tag' )
			);
		}
	}

	// Bootstrap the plugin.
	Plugin::get_instance();
}
