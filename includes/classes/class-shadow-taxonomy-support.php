<?php
/**
 * Layer 6 — Shadow Taxonomy Support for Post Types.
 *
 * Detects shadow taxonomies from the color taxonomy filter, provides
 * helpers for resolving shadow terms via GatherPress's Shadow_Source,
 * adds color picker metaboxes to shadow-source post editors, and
 * manages admin columns on their list tables.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.2
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Singleton managing shadow taxonomy detection, admin UI, and resolution helpers.
 *
 * @since 0.1.2
 */
class Shadow_Taxonomy_Support {

	use Core\Traits\Singleton;


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
		protected function __construct() {
			$this->setup_hooks();
		}

		/**
		 * Register hooks.
		 *
		 * @since 0.1.2
		 * @return void
		 */
		protected function setup_hooks(): void {
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
					$new_columns['gptc_term_colors'] = __( 'Colors', 'gatherpress-taxonomy-colors' );
				}
			}

			if ( ! isset( $new_columns['gptc_term_colors'] ) ) {
				$new_columns['gptc_term_colors'] = __( 'Colors', 'gatherpress-taxonomy-colors' );
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
