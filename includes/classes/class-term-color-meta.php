<?php
/**
 * Layer 1 — Term Meta for Color Storage.
 *
 * Registers term color meta keys for each supported taxonomy, provides
 * admin color picker UI on term add/edit screens, and manages list table
 * color swatch columns.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;
use WP_Term;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Singleton managing term color meta registration and admin UI.
 *
 * @since 0.1.0
 */
class Term_Color_Meta {

	use Core\Traits\Singleton;


		/**
		 * Private constructor — registers all admin-side hooks.
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
		 * @param  WP_Term $term The term being edited.
		 * @return void
		 */
	public function render_edit_term_color_field( WP_Term $term ): void {
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
