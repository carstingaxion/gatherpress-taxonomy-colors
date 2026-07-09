<?php
/**
 * Base test case for integration tests.
 *
 * Extends WP_UnitTestCase with helper methods for creating events,
 * taxonomy terms, and asserting term meta state.
 *
 * @package GatherpressTaxonomyColors\Tests\Integration
 * @since   0.1.0
 */

namespace GatherpressTaxonomyColors\Tests\Integration;

/**
 * Base test case providing shared helpers for integration tests.
 *
 * @since 0.1.0
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Checks whether GatherPress is active and its core classes are available.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if GatherPress is available.
	 */
	protected function is_gatherpress_active(): bool {
		return class_exists( '\GatherPress\Core\Event' );
	}

	/**
	 * Creates a published gatherpress_event post.
	 *
	 * @since 0.1.0
	 *
	 * @param string $title The event title.
	 * @return int The post ID.
	 */
	protected function create_event( string $title ): int {
		return $this->factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Creates a term in the given taxonomy and returns its term_id.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $parent   Optional parent term ID.
	 * @return int The term ID.
	 */
	protected function create_term( string $name, string $taxonomy, int $parent = 0 ): int {
		$args   = $parent ? array( 'parent' => $parent ) : array();
		$result = $this->factory()->term->create(
			array_merge(
				array(
					'name'     => $name,
					'taxonomy' => $taxonomy,
				),
				$args
			)
		);
		return is_array( $result ) ? (int) $result['term_id'] : (int) $result;
	}

	/**
	 * Sets a hex color on a term via term meta.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $hex      Hex color value, e.g. '#ff0000'.
	 * @param string $meta_key Term meta key. Defaults to 'term_color'.
	 * @return void
	 */
	protected function set_term_color( int $term_id, string $hex, string $meta_key = 'term_color' ): void {
		update_term_meta( $term_id, $meta_key, $hex );
	}
}
