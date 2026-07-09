<?php
/**
 * Integration tests for term color meta registration and resolution.
 *
 * Verifies that color meta keys are registered for each taxonomy, that
 * saving and reading hex values round-trips correctly, that the
 * get_inherited_term_color() method walks the ancestor chain, and that
 * resolve_colors_from_terms() produces the expected slot-keyed map.
 *
 * @package GatherpressTaxonomyColors\Tests\Integration
 * @since   0.1.0
 */

namespace GatherpressTaxonomyColors\Tests\Integration;

use GatherpressTaxonomyColors\Helpers;
use GatherpressTaxonomyColors\Term_Color_Meta;

/**
 * Class TermColorMetaIntegrationTest.
 *
 * @since 0.1.0
 * @group term-color-meta
 */
class TermColorMetaIntegrationTest extends TestCase {

	/**
	 * Re-registers term color meta before each test.
	 *
	 * WP_UnitTestCase::tearDown() calls unregister_all_meta_keys(), which
	 * wipes $wp_meta_keys completely. The plugin's init hook only fires
	 * once during bootstrap and is not re-triggered between tests, so meta
	 * registered at bootstrap is gone by the time a later test runs.
	 * Calling register_term_color_meta() directly here restores it.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Term_Color_Meta::get_instance()->register_term_color_meta();
	}

	// ── meta registration ─────────────────────────────────────────────────

	/**
	 * Tests that 'term_color' meta is registered for the 'category' taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_term_color_meta_registered_for_category(): void {
		$registered = get_registered_meta_keys( 'term', 'category' );
		$this->assertArrayHasKey( 'term_color', $registered );
	}

	/**
	 * Tests that 'term_color_secondary' meta is registered for 'category'.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_term_color_secondary_meta_registered_for_category(): void {
		$registered = get_registered_meta_keys( 'term', 'category' );
		$this->assertArrayHasKey( 'term_color_secondary', $registered );
	}

	/**
	 * Tests that all color meta keys from get_color_meta_keys() are registered
	 * for the 'category' taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_all_color_meta_keys_registered_for_category(): void {
		$registered = get_registered_meta_keys( 'term', 'category' );
		foreach ( Helpers::get_color_meta_keys() as $key ) {
			$this->assertArrayHasKey(
				$key,
				$registered,
				"Expected meta key '{$key}' to be registered for 'category'."
			);
		}
	}

	/**
	 * Tests that meta is registered with show_in_rest = true (REST-accessible).
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_term_color_meta_show_in_rest(): void {
		$registered = get_registered_meta_keys( 'term', 'category' );
		$this->assertTrue(
			(bool) ( $registered['term_color']['show_in_rest'] ?? false ),
			"'term_color' meta for 'category' should have show_in_rest = true."
		);
	}

	// ── save and read ─────────────────────────────────────────────────────

	/**
	 * Tests that a hex color saved as term meta is returned unchanged.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_save_and_read_term_color(): void {
		$term_id = $this->create_term( 'Test Category', 'category' );
		$this->set_term_color( $term_id, '#1a2b3c' );

		$this->assertSame( '#1a2b3c', get_term_meta( $term_id, 'term_color', true ) );
	}

	/**
	 * Tests that get_inherited_term_color returns the direct term's color.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_inherited_term_color_direct(): void {
		$term_id = $this->create_term( 'Direct Color', 'category' );
		$this->set_term_color( $term_id, '#abcdef' );

		$term  = get_term( $term_id, 'category' );
		$color = Helpers::get_inherited_term_color( $term, 'term_color' );

		$this->assertSame( '#abcdef', $color );
	}

	/**
	 * Tests that get_inherited_term_color walks up to the parent when
	 * the child term has no color set.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_inherited_term_color_inherits_from_parent(): void {
		$parent_id = $this->create_term( 'Parent Category', 'category' );
		$child_id  = $this->create_term( 'Child Category', 'category', $parent_id );

		// Set color on parent, not on child.
		$this->set_term_color( $parent_id, '#ff0000' );

		$child = get_term( $child_id, 'category' );
		$color = Helpers::get_inherited_term_color( $child, 'term_color' );

		$this->assertSame( '#ff0000', $color );
	}

	/**
	 * Tests that get_inherited_term_color returns empty string when no
	 * color is set on the term or any ancestor.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_inherited_term_color_returns_empty_when_none_set(): void {
		$term_id = $this->create_term( 'Uncolored', 'category' );
		$term    = get_term( $term_id, 'category' );
		$color   = Helpers::get_inherited_term_color( $term, 'term_color' );

		$this->assertSame( '', $color );
	}

	/**
	 * Tests that get_inherited_term_color prefers the child's own color
	 * over a parent's color when both are set.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_inherited_term_color_child_overrides_parent(): void {
		$parent_id = $this->create_term( 'Parent Override', 'category' );
		$child_id  = $this->create_term( 'Child Override', 'category', $parent_id );

		$this->set_term_color( $parent_id, '#ff0000' );
		$this->set_term_color( $child_id, '#00ff00' );

		$child = get_term( $child_id, 'category' );
		$color = Helpers::get_inherited_term_color( $child, 'term_color' );

		$this->assertSame( '#00ff00', $color );
	}

	// ── resolve_colors_from_terms ─────────────────────────────────────────

	/**
	 * Tests that resolve_colors_from_terms returns a keyed map for a term
	 * with a primary color.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_resolve_colors_from_terms_returns_keyed_map(): void {
		$term_id = $this->create_term( 'Colored Category', 'category' );
		$this->set_term_color( $term_id, '#123456' );

		$term   = get_term( $term_id, 'category' );
		$colors = Helpers::resolve_colors_from_terms( array( $term ), 'category' );

		$this->assertArrayHasKey( 'category-primary', $colors );
		$this->assertSame( '#123456', $colors['category-primary'] );
	}

	/**
	 * Tests that resolve_colors_from_terms returns an empty array when
	 * no term has a primary color.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_resolve_colors_from_terms_returns_empty_when_no_primary(): void {
		$term_id = $this->create_term( 'Uncolored Category', 'category' );
		$term    = get_term( $term_id, 'category' );
		$colors  = Helpers::resolve_colors_from_terms( array( $term ), 'category' );

		$this->assertEmpty( $colors );
	}

	/**
	 * Tests that the first term with a primary color wins when multiple
	 * terms are passed.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_resolve_colors_from_terms_first_primary_wins(): void {
		$term_a_id = $this->create_term( 'Cat A', 'category' );
		$term_b_id = $this->create_term( 'Cat B', 'category' );

		$this->set_term_color( $term_a_id, '#aaaaaa' );
		$this->set_term_color( $term_b_id, '#bbbbbb' );

		$term_a = get_term( $term_a_id, 'category' );
		$term_b = get_term( $term_b_id, 'category' );
		$colors = Helpers::resolve_colors_from_terms( array( $term_a, $term_b ), 'category' );

		$this->assertSame( '#aaaaaa', $colors['category-primary'] );
	}

	/**
	 * Tests that secondary color is included in the resolved map when set.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_resolve_colors_from_terms_includes_secondary(): void {
		$term_id = $this->create_term( 'Full Color', 'category' );
		$this->set_term_color( $term_id, '#111111', 'term_color' );
		$this->set_term_color( $term_id, '#222222', 'term_color_secondary' );

		$term   = get_term( $term_id, 'category' );
		$colors = Helpers::resolve_colors_from_terms( array( $term ), 'category' );

		$this->assertArrayHasKey( 'category-secondary', $colors );
		$this->assertSame( '#222222', $colors['category-secondary'] );
	}
}
