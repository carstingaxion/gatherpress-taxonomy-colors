<?php
/**
 * Unit tests for the Term_Color_Settings class.
 *
 * Tests the fallback colors option handling: sanitize callback behavior,
 * stored value retrieval, and that stored values replace the generated
 * slot fallbacks.
 *
 * @package GatherpressTaxonomyColors\Tests\Unit
 * @since   0.4.0
 */

use GatherpressTaxonomyColors\Term_Color_Settings;
use GatherpressTaxonomyColors\Term_Color_Tokens;

/**
 * Class TermColorSettingsTest.
 *
 * @since 0.4.0
 * @coversDefaultClass GatherpressTaxonomyColors\Term_Color_Settings
 */
class TermColorSettingsTest extends \WP_UnitTestCase {

	/**
	 * The singleton instance under test.
	 *
	 * @since 0.4.0
	 *
	 * @var Term_Color_Settings
	 */
	private Term_Color_Settings $settings;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->settings = Term_Color_Settings::get_instance();
	}

	/**
	 * Cleans up the option so tests stay independent.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		delete_option( Term_Color_Settings::OPTION_NAME );
		parent::tearDown();
	}

	// ── sanitize_fallback_colors ──────────────────────────────────────────

	/**
	 * Tests that non-array input is rejected entirely.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::sanitize_fallback_colors
	 * @return void
	 */
	public function test_sanitize_rejects_non_array_input(): void {
		$this->assertSame( array(), $this->settings->sanitize_fallback_colors( null ) );
		$this->assertSame( array(), $this->settings->sanitize_fallback_colors( 'bogus' ) );
		$this->assertSame( array(), $this->settings->sanitize_fallback_colors( false ) );
		$this->assertSame( array(), $this->settings->sanitize_fallback_colors( 123 ) );
	}

	/**
	 * Tests that a valid slug and hex color round-trips unchanged.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::sanitize_fallback_colors
	 * @return void
	 */
	public function test_sanitize_keeps_valid_hex(): void {
		$input = array( 'category-primary' => '#aabbcc' );

		$this->assertSame( $input, $this->settings->sanitize_fallback_colors( $input ) );
	}

	/**
	 * Tests that invalid and empty colors are dropped.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::sanitize_fallback_colors
	 * @return void
	 */
	public function test_sanitize_drops_invalid_colors(): void {
		$clean = $this->settings->sanitize_fallback_colors(
			array(
				'category-primary'  => 'not-a-color',
				'category-secondary' => '',
				'post_tag-primary'  => '#123456',
			)
		);

		$this->assertSame( array( 'post_tag-primary' => '#123456' ), $clean );
	}

	/**
	 * Tests that slugs are normalized with sanitize_key.
	 *
	 * sanitize_key strips disallowed characters, so spaces and special
	 * characters are removed rather than replaced.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::sanitize_fallback_colors
	 * @return void
	 */
	public function test_sanitize_normalizes_slug(): void {
		$clean = $this->settings->sanitize_fallback_colors(
			array( 'Category Primary!' => '#aabbcc' )
		);

		$this->assertArrayHasKey( 'categoryprimary', $clean );
		$this->assertArrayNotHasKey( 'Category Primary!', $clean );
	}

	/**
	 * Tests that non-string slugs and colors are dropped.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::sanitize_fallback_colors
	 * @return void
	 */
	public function test_sanitize_drops_non_string_entries(): void {
		$clean = $this->settings->sanitize_fallback_colors(
			array(
				0                  => '#aabbcc',
				'category-primary' => array( '#aabbcc' ),
				'category-secondary' => 123,
			)
		);

		$this->assertSame( array(), $clean );
	}

	// ── get_stored_fallback_colors ────────────────────────────────────────

	/**
	 * Tests that the stored colors default to an empty array.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_get_stored_defaults_to_empty(): void {
		$this->assertSame( array(), $this->settings->get_stored_fallback_colors() );
	}

	/**
	 * Tests that stored colors round-trip through the option.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_get_stored_round_trip(): void {
		$colors = array( 'category-primary' => '#abcdef' );
		update_option( Term_Color_Settings::OPTION_NAME, $colors );

		$this->assertSame( $colors, $this->settings->get_stored_fallback_colors() );
	}

	/**
	 * Tests that a non-array option value is treated as empty.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_get_stored_ignores_non_array_option(): void {
		update_option( Term_Color_Settings::OPTION_NAME, 'bogus' );

		$this->assertSame( array(), $this->settings->get_stored_fallback_colors() );
	}

	/**
	 * Tests that invalid values are dropped on read.
	 *
	 * update_option() already runs the sanitize callback for registered
	 * settings, so this simulates values written outside the settings
	 * form (WP-CLI, imports) by filtering the option read path.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_get_stored_drops_invalid_values_from_raw_option(): void {
		// Store a valid value so the option exists and the read path is used.
		update_option( Term_Color_Settings::OPTION_NAME, array( 'category-secondary' => '#123456' ) );

		// Simulate a raw option write that bypassed the sanitize callback.
		add_filter(
			'option_' . Term_Color_Settings::OPTION_NAME,
			function () {
				return array(
					'category-primary'  => 'not-a-color',
					'category-secondary' => '#123456',
				);
			}
		);

		$this->assertSame(
			array( 'category-secondary' => '#123456' ),
			$this->settings->get_stored_fallback_colors()
		);

		remove_all_filters( 'option_' . Term_Color_Settings::OPTION_NAME );
	}

	// ── slot fallback override ────────────────────────────────────────────

	/**
	 * Tests that a stored color replaces the generated slot fallback.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_stored_fallback_replaces_generated(): void {
		$slots = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$slug  = $slots[0]['slug'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );

		$this->assertSame( '#abcdef', $map[ $slug ]['fallback'] );
	}

	/**
	 * Tests that deleting the option restores the generated fallback.
	 *
	 * @since 0.4.0
	 *
	 * @covers ::get_stored_fallback_colors
	 * @return void
	 */
	public function test_deleting_option_restores_generated(): void {
		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$slug   = $slots[0]['slug'];
		$before = $slots[0]['fallback'];

		update_option( Term_Color_Settings::OPTION_NAME, array( $slug => '#abcdef' ) );

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );
		$this->assertSame( '#abcdef', $map[ $slug ]['fallback'] );

		delete_option( Term_Color_Settings::OPTION_NAME );

		$map = array_column( Term_Color_Tokens::get_instance()->get_term_color_slots(), null, 'slug' );
		$this->assertSame( $before, $map[ $slug ]['fallback'] );
	}
}
