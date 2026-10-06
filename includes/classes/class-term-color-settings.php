<?php
/**
 * Settings screen for user-defined fallback colors.
 *
 * Adds a page under Settings → Taxonomy Colors where administrators can
 * define a fallback color per design token slot. Stored values replace
 * the auto-generated slot fallbacks before the gptc_term_color_slots
 * filter runs, so developer filters keep the final word.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.4.0
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Singleton providing the fallback color settings screen.
 *
 * @since 0.4.0
 */
class Term_Color_Settings {

	use Core\Traits\Singleton;

	/**
	 * Option name for the user-defined fallback colors map.
	 *
	 * @since 0.4.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'gptc_fallback_colors';

	/**
	 * Menu slug of the settings page.
	 *
	 * @since 0.4.0
	 *
	 * @var string
	 */
	const MENU_SLUG = 'gatherpress-taxonomy-colors';

		/**
		 * Private constructor — registers settings hooks.
		 *
		 * @since 0.4.0
		 */
	protected function __construct() {
		$this->setup_hooks();
	}

		/**
		 * Register hooks.
		 *
		 * @since 0.4.0
		 * @return void
		 */
	protected function setup_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
	}

		/**
		 * Registers the settings page under the Settings menu.
		 *
		 * @since  0.4.0
		 * @return void
		 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Taxonomy Colors', 'gatherpress-taxonomy-colors' ),
			__( 'Taxonomy Colors', 'gatherpress-taxonomy-colors' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

		/**
		 * Registers the fallback colors option.
		 *
		 * @since  0.4.0
		 * @return void
		 */
	public function register_settings(): void {
		register_setting(
			'gatherpress_taxonomy_colors',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_fallback_colors' ),
			)
		);
	}

		/**
		 * Enqueues wp-color-picker assets on the settings screen.
		 *
		 * @since  0.4.0
		 * @param  string $hook_suffix The current admin page hook suffix.
		 * @return void
		 */
	public function enqueue_settings_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_add_inline_script(
			'wp-color-picker',
			'jQuery( document ).ready( function( $ ) { $( ".gptc-color-field" ).wpColorPicker(); } );'
		);
	}

		/**
		 * Returns the user-defined fallback colors.
		 *
		 * Values are re-sanitized on read so entries written outside the
		 * settings form (WP-CLI, imports) cannot inject invalid output.
		 *
		 * @since  0.4.0
		 * @return array<string, string> Map of slot slug to hex color.
		 */
	public function get_stored_fallback_colors(): array {
		return $this->sanitize_fallback_colors( get_option( self::OPTION_NAME, array() ) );
	}

		/**
		 * Sanitizes a raw fallback colors map.
		 *
		 * Entries with an empty or invalid color are dropped, so the slot
		 * falls back to its auto-generated color.
		 *
		 * @since  0.4.0
		 * @param  mixed $value Raw fallback colors map (settings form or option).
		 * @return array<string, string> Map of slot slug to hex color.
		 */
	public function sanitize_fallback_colors( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $slug => $color ) {
			if ( ! is_string( $slug ) || ! is_string( $color ) ) {
				continue;
			}

			$hex = sanitize_hex_color( $color );

			if ( $hex ) {
				$clean[ sanitize_key( $slug ) ] = $hex;
			}
		}

		return $clean;
	}

		/**
		 * Renders the fallback colors settings screen.
		 *
		 * @since  0.4.0
		 * @return void
		 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$stored = $this->get_stored_fallback_colors();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Fallback colors apply when a term has no color assigned, for example in templates without post context.', 'gatherpress-taxonomy-colors' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'gatherpress_taxonomy_colors' ); ?>

				<?php if ( empty( $slots ) ) { ?>
					<p><?php esc_html_e( 'No color-enabled taxonomies found.', 'gatherpress-taxonomy-colors' ); ?></p>
				<?php } else { ?>
					<table class="form-table" role="presentation">
						<?php foreach ( $slots as $slot ) { ?>
							<tr>
								<th scope="row">
									<label for="gptc-fallback-<?php echo esc_attr( $slot['slug'] ); ?>">
										<?php echo esc_html( $slot['name'] ); ?>
									</label>
								</th>
								<td>
									<input
										type="text"
										id="gptc-fallback-<?php echo esc_attr( $slot['slug'] ); ?>"
										name="<?php echo esc_attr( self::OPTION_NAME . '[' . $slot['slug'] . ']' ); ?>"
										value="<?php echo esc_attr( $stored[ $slot['slug'] ] ?? '' ); ?>"
										placeholder="<?php echo esc_attr( $slot['fallback'] ); ?>"
										class="gptc-color-field"
										data-default-color="<?php echo esc_attr( $slot['fallback'] ); ?>"
									/>
								</td>
							</tr>
						<?php } ?>
					</table>
					<p class="description">
						<?php esc_html_e( 'Leave a field empty to use the auto-generated default color. The placeholder shows the currently active default.', 'gatherpress-taxonomy-colors' ); ?>
					</p>
					<?php submit_button(); ?>
				<?php } ?>
			</form>
		</div>
		<?php
	}
}
