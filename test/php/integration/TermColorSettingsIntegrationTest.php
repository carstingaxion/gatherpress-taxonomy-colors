<?php
/**
 * Integration tests for the fallback color settings screen.
 *
 * Verifies that the fallback colors option is registered with the
 * Settings API, that stored colors replace the generated slot fallbacks
 * in the theme.json palette, and that the gptc_term_color_slots filter
 * still takes precedence over stored values.
 *
 * @package GatherpressTaxonomyColors\Tests\Integration
 * @since   0.4.0
 */

namespace GatherpressTaxonomyColors\Tests\Integration;

use GatherpressTaxonomyColors\Term_Color_Settings;
use GatherpressTaxonomyColors\Term_Color_Tokens;

/**
 * Class TermColorSettingsIntegrationTest.
 *
 * @since 0.4.0
 * @group term-color-settings
 */
class TermColorSettingsIntegrationTest extends TestCase {

	/**
	 * Cleans up the option and filter so tests stay independent.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	protected function tearDown(): void {
		delete_option( Term_Color_Settings::OPTION_NAME );
		remove_all_filters( 'gptc_term_color_slots' );
		parent::tearDown();
	}

	// ── settings registration ─────────────────────────────────────────────

	/**
	 * Tests that the fallback colors option is registered with the
	 * Settings API and uses the sanitize callback.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_fallback_colors_option_registered(): void {
		Term_Color_Settings::get_instance()->register_settings();

		$registered = get_registered_settings();

		$this->assertArrayHasKey( Term_Color_Settings::OPTION_NAME, $registered );
		$this->assertSame( 'array', $registered[ Term_Color_Settings::OPTION_NAME ]['type'] );
		$this->assertSame(
			array( Term_Color_Settings::get_instance(), 'sanitize_fallback_colors' ),
			$registered[ Term_Color_Settings::OPTION_NAME ]['sanitize_callback']
		);
	}

	// ── slot fallbacks reflect stored values ──────────────────────────────

	/**
	 * Tests that a stored color replaces the generated slot fallback.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_stored_fallback_replaces_generated(): void {
		$slug = Term_Color_Tokens::get_instance()->get_term_color_slots()[0]['slug'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );

		$this->assertSame( '#abcdef', $map[ $slug ]['fallback'] );
	}

	/**
	 * Tests that deleting the option restores the generated fallbacks.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_deleting_option_restores_generated(): void {
		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$before = array_column( $slots, 'fallback', 'slug' );
		$slug   = $slots[0]['slug'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );
		$this->assertSame( '#abcdef', $map[ $slug ]['fallback'] );

		delete_option( Term_Color_Settings::OPTION_NAME );

		$after = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), 'fallback', 'slug' );

		$this->assertSame( $before, $after );
	}

	/**
	 * Tests that a stored color for an unknown slug changes nothing.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_stored_fallback_for_unknown_slug_is_ignored(): void {
		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$before = array_column( $slots, 'fallback', 'slug' );

		update_option( Term_Color_Settings::OPTION_NAME, array( 'brand-unknown' => '#abcdef' ) );

		$after = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), 'fallback', 'slug' );

		$this->assertSame( $before, $after );
	}

	// ── theme.json palette ────────────────────────────────────────────────

	/**
	 * Tests that a stored fallback color reaches the theme.json palette.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_stored_fallback_reaches_theme_json_palette(): void {
		$slug = Term_Color_Tokens::get_instance()->get_term_color_slots()[0]['slug'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		$theme_json = new \WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' );
		$result     = Term_Color_Tokens::get_instance()->inject_term_color_design_tokens( $theme_json );

		$data    = $result->get_data();
		$palette = $data['settings']['color']['palette']['theme'] ?? array();
		$indexed = array_column( $palette, null, 'slug' );

		$this->assertSame( '#abcdef', $indexed[ $slug ]['color'] );
	}

	// ── filter precedence ─────────────────────────────────────────────────

	/**
	 * Tests that the gptc_term_color_slots filter still wins over
	 * stored settings values.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function test_filter_overrides_stored_fallback(): void {
		$slug = Term_Color_Tokens::get_instance()->get_term_color_slots()[0]['slug'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		add_filter(
			'gptc_term_color_slots',
			function ( array $slots ) use ( $slug ): array {
				foreach ( $slots as &$slot ) {
					if ( $slug === $slot['slug'] ) {
						$slot['fallback'] = '#ff0000';
					}
				}
				return $slots;
			}
		);

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );

		$this->assertSame( '#ff0000', $map[ $slug ]['fallback'] );
	}
}
