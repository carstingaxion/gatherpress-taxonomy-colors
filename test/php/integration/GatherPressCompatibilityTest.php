<?php
/**
 * Compatibility tests for required GatherPress API and registration state.
 *
 * Verifies that the GatherPress structures relied upon by this plugin
 * are still present after each GatherPress upgrade. These tests act as
 * an early-warning system: if a future release removes a post type,
 * shadow taxonomy, or method this plugin depends on, the suite fails
 * before any runtime error can occur.
 *
 * @package GatherpressTaxonomyColors\Tests\Integration
 * @since   0.1.0
 */

namespace GatherpressTaxonomyColors\Tests\Integration;

use GatherpressTaxonomyColors\Plugin;

/**
 * Class GatherPressCompatibilityTest.
 *
 * @since 0.1.0
 * @group gatherpress-compat
 */
class GatherPressCompatibilityTest extends TestCase {

	// ── plugin bootstrapping ──────────────────────────────────────────────

	/**
	 * Tests that Plugin::get_instance() returns a Plugin singleton.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_plugin_singleton(): void {
		$a = Plugin::get_instance();
		$b = Plugin::get_instance();
		$this->assertSame( $a, $b );
		$this->assertInstanceOf( Plugin::class, $a );
	}

	/**
	 * Tests that get_color_taxonomies() returns a non-empty array of strings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_color_taxonomies_returns_strings(): void {
		$taxonomies = Plugin::get_instance()->get_color_taxonomies();
		$this->assertIsArray( $taxonomies );
		$this->assertNotEmpty( $taxonomies );
		foreach ( $taxonomies as $slug ) {
			$this->assertIsString( $slug );
		}
	}

	/**
	 * Tests that the gptc_term_color_taxonomies filter can add a taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_gptc_term_color_taxonomies_filter(): void {
		add_filter(
			'gptc_term_color_taxonomies',
			function ( array $taxonomies ): array {
				$taxonomies[] = 'post_tag';
				return $taxonomies;
			}
		);

		$taxonomies = Plugin::get_instance()->get_color_taxonomies();
		$this->assertContains( 'post_tag', $taxonomies );

		remove_all_filters( 'gptc_term_color_taxonomies' );
	}

	// ── GatherPress API surface ───────────────────────────────────────────

	/**
	 * Tests that the GatherPress Core\Traits\Singleton trait is available.
	 *
	 * This plugin uses the trait in all its singleton classes.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_gatherpress_singleton_trait_exists(): void {
		$this->assertTrue(
			trait_exists( '\GatherPress\Core\Traits\Singleton' ),
			'\GatherPress\Core\Traits\Singleton must be available.'
		);
	}

	/**
	 * Tests that the gatherpress_event post type is registered.
	 *
	 * This plugin registers color meta on the taxonomies attached to
	 * gatherpress_event. The post type must exist for those taxonomies
	 * to be fully functional.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_gatherpress_event_post_type_exists(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertTrue(
			post_type_exists( 'gatherpress_event' ),
			'gatherpress_event post type must be registered by GatherPress.'
		);
	}

	/**
	 * Tests that the _gatherpress_venue shadow taxonomy is registered.
	 *
	 * The default taxonomy list includes '_gatherpress_venue'. It must be
	 * registered by GatherPress before our plugin registers color meta on it.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_gatherpress_venue_shadow_taxonomy_exists(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertTrue(
			taxonomy_exists( '_gatherpress_venue' ),
			'_gatherpress_venue shadow taxonomy must be registered by GatherPress.'
		);
	}

	/**
	 * Tests that the GatherPress Shadow_Source class and its key methods exist.
	 *
	 * Shadow_Taxonomy_Support relies on Shadow_Source::get_instance() and
	 * term_slug_from_post_name() to resolve shadow terms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_shadow_source_class_and_methods_exist(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! class_exists( '\GatherPress\Core\Shadow_Source' ) ) {
			$this->markTestSkipped(
				'\GatherPress\Core\Shadow_Source is not available in this GatherPress version. '
				. 'Shadow_Taxonomy_Support gracefully skips shadow resolution when the class is absent.'
			);
		}

		$this->assertTrue(
			method_exists( '\GatherPress\Core\Shadow_Source', 'get_instance' ),
			'\GatherPress\Core\Shadow_Source must have get_instance().'
		);

		$shadow_source = \GatherPress\Core\Shadow_Source::get_instance();

		$this->assertTrue(
			method_exists( $shadow_source, 'term_slug_from_post_name' ),
			'\GatherPress\Core\Shadow_Source must have term_slug_from_post_name().'
		);
	}

	// ── hook registration ─────────────────────────────────────────────────

	/**
	 * Tests that wp_theme_json_data_theme is hooked by Term_Color_Tokens.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_wp_theme_json_data_theme_filter_hooked(): void {
		$this->assertGreaterThan(
			0,
			has_filter( 'wp_theme_json_data_theme' ),
			'wp_theme_json_data_theme filter must be registered by Term_Color_Tokens.'
		);
	}

	/**
	 * Tests that render_block_core/post-template is hooked by Term_Color_Scoper.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_render_block_post_template_filter_hooked(): void {
		$this->assertGreaterThan(
			0,
			has_filter( 'render_block_core/post-template' ),
			'render_block_core/post-template filter must be registered by Term_Color_Scoper.'
		);
	}

	/**
	 * Tests that render_block_core/post-terms is hooked by Term_Color_Scoper.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_render_block_post_terms_filter_hooked(): void {
		$this->assertGreaterThan(
			0,
			has_filter( 'render_block_core/post-terms' ),
			'render_block_core/post-terms filter must be registered by Term_Color_Scoper.'
		);
	}
}
