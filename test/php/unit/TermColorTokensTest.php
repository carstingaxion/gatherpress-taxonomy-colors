<?php
/**
 * Unit tests for the Term_Color_Tokens class.
 *
 * Tests design token slot generation: correct slot count, slug format,
 * property naming convention, fallback color format, and filter
 * extensibility. Also covers palette merging via merge_palette_into_theme_json().
 *
 * @package GatherpressTaxonomyColors\Tests\Unit
 * @since   0.1.0
 */

use GatherpressTaxonomyColors\Term_Color_Tokens;
use GatherpressTaxonomyColors\Helpers;

/**
 * Class TermColorTokensTest.
 *
 * @since 0.1.0
 * @coversDefaultClass GatherpressTaxonomyColors\Term_Color_Tokens
 */
class TermColorTokensTest extends \WP_UnitTestCase {

	/**
	 * The singleton instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var Term_Color_Tokens
	 */
	private Term_Color_Tokens $tokens;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->tokens = Term_Color_Tokens::get_instance();
	}

	// ── get_term_color_slots ──────────────────────────────────────────────

	/**
	 * Tests that slots is a non-empty array.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_get_term_color_slots_returns_array(): void {
		$slots = $this->tokens->get_term_color_slots();
		$this->assertIsArray( $slots );
		$this->assertNotEmpty( $slots );
	}

	/**
	 * Tests that each slot has all required keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_each_slot_has_required_keys(): void {
		foreach ( $this->tokens->get_term_color_slots() as $slot ) {
			$this->assertArrayHasKey( 'slug', $slot );
			$this->assertArrayHasKey( 'name', $slot );
			$this->assertArrayHasKey( 'property', $slot );
			$this->assertArrayHasKey( 'fallback', $slot );
			$this->assertArrayHasKey( 'taxonomy', $slot );
			$this->assertArrayHasKey( 'meta_key', $slot );
		}
	}

	/**
	 * Tests that slot slugs follow the {taxonomy}-{role} format.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_slot_slug_format(): void {
		foreach ( $this->tokens->get_term_color_slots() as $slot ) {
			$this->assertMatchesRegularExpression(
				'/^[a-z0-9-]+-[a-z0-9-]+$/',
				$slot['slug'],
				"Slot slug '{$slot['slug']}' does not match {taxonomy}-{role} format."
			);
		}
	}

	/**
	 * Tests that property names follow the --flavor--{slug} convention.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_slot_property_naming_convention(): void {
		foreach ( $this->tokens->get_term_color_slots() as $slot ) {
			$this->assertStringStartsWith(
				'--flavor--',
				$slot['property'],
				"Slot property '{$slot['property']}' does not start with --flavor--."
			);
		}
	}

	/**
	 * Tests that fallback values are valid hex colors.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_slot_fallback_is_valid_hex(): void {
		foreach ( $this->tokens->get_term_color_slots() as $slot ) {
			$this->assertMatchesRegularExpression(
				'/^#[0-9a-fA-F]{6}$/',
				$slot['fallback'],
				"Slot fallback '{$slot['fallback']}' for slug '{$slot['slug']}' is not a valid hex color."
			);
		}
	}

	/**
	 * Tests that the number of slots equals taxonomies × roles.
	 *
	 * Derives the expected count from the plugin's own get_color_taxonomies(),
	 * filtered to those actually registered, mirroring what get_term_color_slots()
	 * does internally.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_slot_count_equals_taxonomies_times_roles(): void {
		$taxonomies = array_filter(
			\GatherpressTaxonomyColors\Plugin::get_instance()->get_color_taxonomies(),
			fn( $t ) => (bool) get_taxonomy( $t )
		);
		$roles    = Helpers::get_color_roles();
		$expected = count( $taxonomies ) * count( $roles );

		$this->assertCount( $expected, $this->tokens->get_term_color_slots() );
	}

	/**
	 * Tests that the gptc_term_color_slots filter can add a custom slot.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_term_color_slots
	 * @return void
	 */
	public function test_gptc_term_color_slots_filter_adds_slot(): void {
		add_filter(
			'gptc_term_color_slots',
			function ( array $slots ): array {
				$slots[] = array(
					'slug'     => 'brand-highlight',
					'name'     => 'Brand Highlight',
					'property' => '--flavor--brand-highlight',
					'fallback' => '#ff6600',
					'taxonomy' => '',
					'meta_key' => '',
				);
				return $slots;
			}
		);

		$slugs = array_column( $this->tokens->get_term_color_slots(), 'slug' );
		$this->assertContains( 'brand-highlight', $slugs );

		remove_all_filters( 'gptc_term_color_slots' );
	}

	// ── merge_palette_into_theme_json ─────────────────────────────────────

	/**
	 * Tests that merge_palette_into_theme_json returns a WP_Theme_JSON_Data object.
	 *
	 * @since 0.1.0
	 *
	 * @covers GatherpressTaxonomyColors\Helpers::merge_palette_into_theme_json
	 * @return void
	 */
	public function test_merge_palette_returns_theme_json_data(): void {
		$theme_json = new \WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' );
		$result     = Helpers::merge_palette_into_theme_json(
			$theme_json,
			array(
				array(
					'slug'  => 'test-color',
					'color' => '#aabbcc',
					'name'  => 'Test Color',
				),
			)
		);
		$this->assertInstanceOf( \WP_Theme_JSON_Data::class, $result );
	}

	/**
	 * Tests that a new palette entry is included in the merged data.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_merge_palette_includes_new_entry(): void {
		$theme_json = new \WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' );
		$result     = Helpers::merge_palette_into_theme_json(
			$theme_json,
			array(
				array(
					'slug'  => 'my-slot',
					'color' => '#112233',
					'name'  => 'My Slot',
				),
			)
		);

		$data    = $result->get_data();
		$palette = $data['settings']['color']['palette']['theme'] ?? array();
		$slugs   = array_column( $palette, 'slug' );

		$this->assertContains( 'my-slot', $slugs );
	}

	/**
	 * Tests that an existing palette entry with the same slug is overwritten.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_merge_palette_overwrites_existing_slug(): void {
		$initial = new \WP_Theme_JSON_Data(
			array(
				'version'  => 3,
				'settings' => array(
					'color' => array(
						'palette' => array(
							'theme' => array(
								array(
									'slug'  => 'existing',
									'color' => '#000000',
									'name'  => 'Old',
								),
							),
						),
					),
				),
			),
			'theme'
		);

		$result = Helpers::merge_palette_into_theme_json(
			$initial,
			array(
				array(
					'slug'  => 'existing',
					'color' => '#ffffff',
					'name'  => 'New',
				),
			)
		);

		$data    = $result->get_data();
		$palette = $data['settings']['color']['palette']['theme'] ?? array();
		$indexed = array_column( $palette, null, 'slug' );

		$this->assertSame( '#ffffff', $indexed['existing']['color'] );
		$this->assertSame( 'New', $indexed['existing']['name'] );
	}

	/**
	 * Tests that merging an empty items array returns the original object.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_merge_palette_with_empty_items_returns_original(): void {
		$theme_json = new \WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' );
		$result     = Helpers::merge_palette_into_theme_json( $theme_json, array() );
		$this->assertSame( $theme_json, $result );
	}
}
