<?php
/**
 * Plugin Name:       GatherPress Taxonomy Colors
 * Description:       Assign colors to taxonomy terms and use them as native design tokens in the block editor — resolved contextually per post, per archive, and per Query Loop item.
 * Version:           0.1.2
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

/*
====================================================================
 * Trait: Singleton
 *
 * Eliminates repeated $instance / get_instance / __clone / __wakeup
 * boilerplate across every class in the plugin.
 *
 * @since 0.1.1
 * ====================================================================
 */

if ( ! trait_exists( __NAMESPACE__ . '\\Singleton' ) ) {

	/**
	 * Reusable singleton boilerplate.
	 *
	 * @since 0.1.1
	 */
	trait Singleton {

		/**
		 * The singleton instance.
		 *
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
		final public static function get_instance(): static {
			if ( null === static::$instance ) {
				static::$instance = new static();
			}
			return static::$instance;
		}

		/**
		 * Protected constructor to prevent direct instantiation.
		 *
		 * @since 0.1.1
		 */
		private function __clone() {}

		/**
		 * Prevents unserialization of the singleton instance.
		 *
		 * @since  0.1.1
		 *
		 * @throws \RuntimeException Always.
		 * @return void
		 */
		final public function __wakeup(): void {
			throw new \RuntimeException(
				esc_html__( 'Cannot unserialize a Singleton.', 'gatherpress-taxonomy-colors' )
			);
		}
	}
}

/*
====================================================================
 * Class: Helpers
 *
 * Shared utility methods used across multiple layers: taxonomy slug
 * normalization, CSS block generation, palette merging, color role
 * retrieval, and per-taxonomy term color resolution.
 *
 * @since 0.1.1
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Helpers' ) ) {

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
		 * @var array|null
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
		 * @param  \WP_Theme_JSON_Data                                          $theme_json  Theme JSON data.
		 * @param  array<int, array{slug: string, color: string, name: string}> $new_items   Palette entries to merge.
		 * @return \WP_Theme_JSON_Data Modified theme JSON data.
		 */
		public static function merge_palette_into_theme_json( \WP_Theme_JSON_Data $theme_json, array $new_items ): \WP_Theme_JSON_Data {
			if ( empty( $new_items ) ) {
				return $theme_json;
			}

			$existing_data = $theme_json->get_data();
			// @todo Depending if custom palettes are enabled.
			$palette          = ( 1 === 2 ) ? 'custom' : 'theme';
			$existing_palette = $existing_data['settings']['color']['palette'][ $palette ] ?? array();
			$indexed          = array();
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
							'palette' => array(
								$palette => array_values( $indexed ),
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
		 * @param  \WP_Term[] $terms          Array of term objects.
		 * @param  string     $normalized_tax Normalized taxonomy slug.
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
						$colors[ $normalized_tax . '-' . $role['slug'] ] = sanitize_hex_color( $value );
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

/*
====================================================================
 * Class: Term_Color_Meta
 *
 * Responsibility: Layer 1 — Term Meta for Color Storage.
 *
 * @since 0.1.0
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Meta' ) ) {

	/**
	 * Singleton managing term color meta registration and admin UI.
	 *
	 * @since 0.1.0
	 */
	class Term_Color_Meta {

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
		 * Registers color meta keys for each supported taxonomy,
		 * derived from the color roles filter.
		 *
		 * @since  0.1.0
		 * @return void
		 */
		public function register_term_color_meta(): void {
			$taxonomies = Plugin::get_instance()->get_color_taxonomies();
			$meta_keys  = Helpers::get_color_meta_keys();

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
			register_post_meta(
				'',
				'_gptc_colors_updated',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		/**
		 * Renders the color picker fields on the "Add New Term" form.
		 *
		 * @since  0.1.0
		 * @return void
		 */
		public function render_add_term_color_field(): void {
			wp_nonce_field( 'gptc_save_term_color', 'gptc_term_color_nonce' );
			$roles  = Helpers::get_color_roles();
			$values = array_fill_keys( array_column( $roles, 'meta_key' ), '' );
			$this->render_color_fields( 'div', $values );
		}

		/**
		 * Renders the color picker fields on the "Edit Term" form.
		 *
		 * @since  0.1.0
		 * @param  \WP_Term $term The term being edited.
		 * @return void
		 */
		public function render_edit_term_color_field( \WP_Term $term ): void {
			wp_nonce_field( 'gptc_save_term_color', 'gptc_term_color_nonce' );
			$roles  = Helpers::get_color_roles();
			$values = array();
			foreach ( $roles as $role ) {
				$values[ $role['meta_key'] ] = get_term_meta( $term->term_id, $role['meta_key'], true );
			}
			$this->render_color_fields( 'tr', $values );
		}

		/**
		 * Renders color picker fields for all registered color roles.
		 *
		 * Adapts the wrapper markup to either the "Add New" form (div)
		 * or the "Edit" form (table row).
		 *
		 * @since  0.2.0
		 * @param  string                $wrapper 'div' for add-form, 'tr' for edit-form.
		 * @param  array<string, string> $values  Map of meta_key => current value.
		 * @return void
		 */
		private function render_color_fields( string $wrapper, array $values ): void {
			$roles = Helpers::get_color_roles();

			foreach ( $roles as $role ) {
				$field_id = 'gptc-term-color-' . $role['slug'];
				$name     = $role['meta_key'];
				$label    = sprintf(
					/* translators: %s: role label, e.g. "Primary" */
					__( 'Term Color (%s)', 'gatherpress-taxonomy-colors' ),
					$role['label']
				);
				$desc = sprintf(
					/* translators: %s: role label, e.g. "Primary" */
					__( '%s color for this term. Available as a design token in the block editor.', 'gatherpress-taxonomy-colors' ),
					$role['label']
				);
				$value = $values[ $name ] ?? '';

				if ( 'tr' === $wrapper ) {
					?>
					<tr class="form-field">
						<th scope="row">
							<label for="<?php echo esc_attr( $field_id ); ?>">
								<?php echo esc_html( $label ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $name ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								class="gptc-color-field"
								data-default-color=""
							/>
							<p class="description"><?php echo esc_html( $desc ); ?></p>
						</td>
					</tr>
					<?php
				} else {
					?>
					<div class="form-field">
						<label for="<?php echo esc_attr( $field_id ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							class="gptc-color-field"
							data-default-color=""
						/>
						<p class="description"><?php echo esc_html( $desc ); ?></p>
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

			$meta_keys = Helpers::get_color_meta_keys();

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

			$roles  = Helpers::get_color_roles();
			$output = '<span style="display:inline-flex;align-items:center;gap:6px;">';

			foreach ( $roles as $role ) {
				$color   = get_term_meta( $term_id, $role['meta_key'], true );
				$output .= $this->render_color_swatch( $color, $role['label'] );
			}

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

/*
====================================================================
 * Class: Term_Color_Tokens
 *
 * Responsibility: Layer 2 — Design Token Slot Definitions and
 * theme.json Palette Integration.
 *
 * @since 0.1.0
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Tokens' ) ) {

	/**
	 * Singleton managing design token definitions and theme.json integration.
	 *
	 * @since 0.1.0
	 */
	class Term_Color_Tokens {

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
}

/*
====================================================================
 * Class: Term_Color_Resolver
 *
 * Responsibility: Layers 3 & 4 — Contextual Color Resolution.
 *
 * @since 0.1.0
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Resolver' ) ) {

	/**
	 * Singleton managing contextual term color resolution and CSS injection.
	 *
	 * @since 0.1.0
	 */
	class Term_Color_Resolver {

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

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

				if ( $term instanceof \WP_Term ) {
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
				function ( array $slot ) use ( $colors ): array { // phpcs:ignore Universal.FunctionDeclarations.NoLongClosures.ExceedsRecommended
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

/*
====================================================================
 * Class: Term_Color_Scoper
 *
 * Responsibility: Layer 5 — Query Loop Scoped Resolution.
 *
 * @since 0.1.0
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Term_Color_Scoper' ) ) {

	/**
	 * Singleton managing scoped per-post and per-term color injection.
	 *
	 * @since 0.1.0
	 */
	class Term_Color_Scoper {

		use Singleton;

		/**
		 * Private constructor — registers render filters and block style.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_filter( 'render_block_core/post-template', array( $this, 'scope_term_colors_to_post_template' ) );
			add_action( 'init', array( $this, 'register_post_terms_block_style' ) );
			add_filter( 'render_block_core/post-terms', array( $this, 'inject_post_terms_color_properties' ), 10, 3 );
		}

		/**
		 * Builds scoped inline style declarations for a set of resolved colors.
		 *
		 * @since  0.1.0
		 * @param  array<string, string>                                                  $colors      Resolved slot slug to hex map.
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
		 * @param  string $block_content Rendered post-template HTML.
		 * @return string Modified block content.
		 */
		public function scope_term_colors_to_post_template( string $block_content ): string {
			$trimmed = trim( $block_content );
			if ( '' === $trimmed ) {
				return $block_content;
			}

			$resolver    = Term_Color_Resolver::get_instance();
			$slot_lookup = $this->get_slot_lookup();
			$processor   = new \WP_HTML_Tag_Processor( $block_content );

			while ( $processor->next_tag(
				array(
					'tag_name'   => 'LI',
					'class_name' => 'wp-block-post',
				)
			) ) {
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
			$roles          = Helpers::get_color_roles();

			$term_color_map = array();

			foreach ( $post_terms as $term ) {
				$term_link = get_term_link( $term );

				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$has_any_color = false;
				$term_colors   = array();

				foreach ( $roles as $role ) {
					$color = get_term_meta( $term->term_id, $role['meta_key'], true );
					if ( $color ) {
						$term_colors[ $role['slug'] ] = sanitize_hex_color( $color );
						$has_any_color                = true;
					}
				}

				if ( ! $has_any_color ) {
					continue;
				}

				$parsed_path = wp_parse_url( $term_link, PHP_URL_PATH );
				$normal_key  = $parsed_path ? untrailingslashit( $parsed_path ) : untrailingslashit( $term_link );

				$term_color_map[ $normal_key ] = $term_colors;
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

				$colors   = $term_color_map[ $normal_key ];
				$resolved = array();

				foreach ( $colors as $role_slug => $hex ) {
					$resolved[ $normalized_tax . '-' . $role_slug ] = $hex;
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

/*
====================================================================
 * Class: Shadow_Taxonomy_Support
 *
 * Responsibility: Layer 6 — Shadow Taxonomy Support for Post Types.
 *
 * Detects shadow taxonomies from the color taxonomy filter, provides
 * helpers for resolving shadow terms, adds color picker metaboxes to
 * shadow-source post editors, and admin columns to their list tables.
 *
 * @since 0.1.2
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Shadow_Taxonomy_Support' ) ) {

	/**
	 * Singleton managing shadow taxonomy detection, admin UI, and resolution helpers.
	 *
	 * @since 0.1.2
	 */
	class Shadow_Taxonomy_Support {

		use Singleton;

		/**
		 * Confirmed shadow-source post type slugs.
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
		 * Enqueues the shadow taxonomy config and color roles as inline scripts
		 * so the JS sidebar panel knows which post types are shadow sources
		 * and which color roles are available.
		 *
		 * @since  0.1.3
		 * @return void
		 */
		public function enqueue_shadow_config_script(): void {
			$handle = 'gatherpress-taxonomy-colors-editor-script';

			// Always provide color roles to the editor.
			$roles_json = wp_json_encode( Helpers::get_color_roles() );

			if ( false !== $roles_json ) {
				wp_add_inline_script(
					$handle,
					sprintf( 'window.gptcColorRoles = %s;', $roles_json ),
					'before'
				);
			}

			// Shadow config is only needed when shadow taxonomies exist.
			$map = $this->get_shadow_source_post_types();

			if ( ! empty( $map ) ) {
				$json = wp_json_encode( $map );

				if ( false !== $json ) {
					wp_add_inline_script(
						$handle,
						sprintf( 'window.gptcShadowConfig = %s;', $json ),
						'before'
					);
				}
			}
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

			$term  = $this->resolve_shadow_term( $post, $shadow_taxonomy );
			$roles = Helpers::get_color_roles();
			$size  = '20px';

			echo '<span style="display:inline-flex;align-items:center;gap:4px;">';
			foreach ( $roles as $role ) {
				$color = $term ? get_term_meta( $term->term_id, $role['meta_key'], true ) : '';
				echo $this->render_admin_swatch( $color, $role['label'], $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
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

/*
====================================================================
 * Class: Plugin
 *
 * Responsibility: Slim orchestrator — registers the Gutenberg block
 * and bootstraps all sub-singletons.
 *
 * @since 0.1.0
 * ====================================================================
 */

if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Main plugin orchestrator — Singleton.
	 *
	 * @since 0.1.0
	 */
	class Plugin {

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
					'category'
				)
			);
		}
	}

	// Bootstrap the plugin.
	Plugin::get_instance();
}
