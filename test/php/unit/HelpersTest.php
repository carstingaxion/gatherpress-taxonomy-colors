<?php
/**
 * Unit tests for the Helpers class.
 *
 * Tests static utility methods: taxonomy slug normalization, CSS block
 * generation, color role retrieval, HSL-to-hex conversion, and palette
 * merging. All methods are pure functions requiring no database state.
 *
 * @package GatherpressTaxonomyColors\Tests\Unit
 * @since   0.1.0
 */

use GatherpressTaxonomyColors\Helpers;
use GatherpressTaxonomyColors\Term_Color_Tokens;

/**
 * Class HelpersTest.
 *
 * @since 0.1.0
 * @coversDefaultClass GatherpressTaxonomyColors\Helpers
 */
class HelpersTest extends \WP_UnitTestCase {

	// ── normalize_taxonomy_slug ───────────────────────────────────────────

	/**
	 * Tests that underscores are replaced with hyphens.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::normalize_taxonomy_slug
	 * @return void
	 */
	public function test_normalize_replaces_underscores_with_hyphens(): void {
		$this->assertSame( 'post-tag', Helpers::normalize_taxonomy_slug( 'post_tag' ) );
	}

	/**
	 * Tests that a leading underscore (shadow taxonomy convention) is stripped.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::normalize_taxonomy_slug
	 * @return void
	 */
	public function test_normalize_strips_leading_underscore(): void {
		$this->assertSame( 'gatherpress-venue', Helpers::normalize_taxonomy_slug( '_gatherpress_venue' ) );
	}

	/**
	 * Tests that a plain slug without underscores passes through unchanged.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::normalize_taxonomy_slug
	 * @return void
	 */
	public function test_normalize_plain_slug_unchanged(): void {
		$this->assertSame( 'category', Helpers::normalize_taxonomy_slug( 'category' ) );
	}

	/**
	 * Tests that uppercase letters are lowercased.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::normalize_taxonomy_slug
	 * @return void
	 */
	public function test_normalize_lowercases(): void {
		$this->assertSame( 'my-taxonomy', Helpers::normalize_taxonomy_slug( 'My_Taxonomy' ) );
	}

	// ── build_css_block ───────────────────────────────────────────────────

	/**
	 * Tests that an empty property map returns an empty string.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::build_css_block
	 * @return void
	 */
	public function test_build_css_block_empty_properties_returns_empty_string(): void {
		$this->assertSame( '', Helpers::build_css_block( ':root', array() ) );
	}

	/**
	 * Tests that the output contains the selector.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::build_css_block
	 * @return void
	 */
	public function test_build_css_block_contains_selector(): void {
		$css = Helpers::build_css_block( ':root', array( '--foo' => 'bar' ) );
		$this->assertStringContainsString( ':root', $css );
	}

	/**
	 * Tests that the output contains the property and value.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::build_css_block
	 * @return void
	 */
	public function test_build_css_block_contains_property_and_value(): void {
		$css = Helpers::build_css_block( 'body', array( '--flavor--category-primary' => '#ff0000' ) );
		$this->assertStringContainsString( '--flavor--category-primary', $css );
		$this->assertStringContainsString( '#ff0000', $css );
	}

	/**
	 * Tests that multiple properties are all included.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::build_css_block
	 * @return void
	 */
	public function test_build_css_block_multiple_properties(): void {
		$css = Helpers::build_css_block(
			':root',
			array(
				'--a' => 'red',
				'--b' => 'blue',
			)
		);
		$this->assertStringContainsString( '--a', $css );
		$this->assertStringContainsString( '--b', $css );
	}

	// ── get_color_roles ───────────────────────────────────────────────────

	/**
	 * Tests that the default color roles are returned as a non-empty array.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_roles
	 * @return void
	 */
	public function test_get_color_roles_returns_array(): void {
		$roles = Helpers::get_color_roles();
		$this->assertIsArray( $roles );
		$this->assertNotEmpty( $roles );
	}

	/**
	 * Tests that each role has the required slug, label, and meta_key keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_roles
	 * @return void
	 */
	public function test_get_color_roles_each_has_required_keys(): void {
		foreach ( Helpers::get_color_roles() as $role ) {
			$this->assertArrayHasKey( 'slug', $role );
			$this->assertArrayHasKey( 'label', $role );
			$this->assertArrayHasKey( 'meta_key', $role );
		}
	}

	/**
	 * Tests that the default roles include 'primary' and 'secondary'.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_roles
	 * @return void
	 */
	public function test_get_color_roles_default_slugs(): void {
		$slugs = array_column( Helpers::get_color_roles(), 'slug' );
		$this->assertContains( 'primary', $slugs );
		$this->assertContains( 'secondary', $slugs );
	}

	/**
	 * Tests that a custom role added via filter is validated and returned.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_roles
	 * @return void
	 */
	public function test_get_color_roles_filter_adds_role(): void {
		// Reset the static cache so the filter is picked up.
		$reflection = new \ReflectionClass( Helpers::class );
		$prop       = $reflection->getProperty( 'color_roles_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		add_filter(
			'gptc_term_color_roles',
			function ( array $roles ): array {
				$roles[] = array(
					'slug'     => 'accent',
					'label'    => 'Accent',
					'meta_key' => 'term_color_accent',
				);
				return $roles;
			}
		);

		$slugs = array_column( Helpers::get_color_roles(), 'slug' );
		$this->assertContains( 'accent', $slugs );

		// Cleanup: reset cache and remove filter.
		remove_all_filters( 'gptc_term_color_roles' );
		$prop->setValue( null, null );
	}

	/**
	 * Tests that roles missing required keys are silently dropped.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_roles
	 * @return void
	 */
	public function test_get_color_roles_invalid_entries_dropped(): void {
		$reflection = new \ReflectionClass( Helpers::class );
		$prop       = $reflection->getProperty( 'color_roles_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		add_filter(
			'gptc_term_color_roles',
			function (): array {
				return array(
					array( 'slug' => 'missing-label-and-meta' ),
					array(
						'slug'     => 'valid',
						'label'    => 'Valid',
						'meta_key' => 'term_color_valid',
					),
				);
			}
		);

		$roles = Helpers::get_color_roles();
		$this->assertCount( 1, $roles );
		$this->assertSame( 'valid', $roles[0]['slug'] );

		remove_all_filters( 'gptc_term_color_roles' );
		$prop->setValue( null, null );
	}

	// ── get_color_meta_keys ───────────────────────────────────────────────

	/**
	 * Tests that get_color_meta_keys() returns the meta_key column from roles.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_color_meta_keys
	 * @return void
	 */
	public function test_get_color_meta_keys_returns_meta_keys(): void {
		$keys = Helpers::get_color_meta_keys();
		$this->assertContains( 'term_color', $keys );
		$this->assertContains( 'term_color_secondary', $keys );
	}

	// ── hsl_to_hex (via Term_Color_Tokens) ───────────────────────────────

	/**
	 * Tests HSL(0, 100, 50) → pure red.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_red(): void {
		$this->assertSame( '#ff0000', Term_Color_Tokens::hsl_to_hex( 0, 100, 50 ) );
	}

	/**
	 * Tests HSL(120, 100, 50) → pure green.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_green(): void {
		$this->assertSame( '#00ff00', Term_Color_Tokens::hsl_to_hex( 120, 100, 50 ) );
	}

	/**
	 * Tests HSL(240, 100, 50) → pure blue.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_blue(): void {
		$this->assertSame( '#0000ff', Term_Color_Tokens::hsl_to_hex( 240, 100, 50 ) );
	}

	/**
	 * Tests HSL(0, 0, 0) → black.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_black(): void {
		$this->assertSame( '#000000', Term_Color_Tokens::hsl_to_hex( 0, 0, 0 ) );
	}

	/**
	 * Tests HSL(0, 0, 100) → white.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_white(): void {
		$this->assertSame( '#ffffff', Term_Color_Tokens::hsl_to_hex( 0, 0, 100 ) );
	}

	/**
	 * Tests that the return value is always a 7-char hex string.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_hsl_to_hex_returns_7_char_string(): void {
		$hex = Term_Color_Tokens::hsl_to_hex( 30, 20, 60 );
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $hex );
	}
}
