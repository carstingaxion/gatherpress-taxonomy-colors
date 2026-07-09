<?php
/**
 * Plugin Name:       GatherPress Taxonomy Colors
 * Plugin URI:        https://github.com/carstingaxion/gatherpress-taxonomy-colors
 * Description:       Assign colors to taxonomy terms and use them as native design tokens in the block editor — resolved contextually per post, per archive, and per Query Loop item.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires plugins:  gatherpress
 * Author:            carstenbach
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-taxonomy-colors
 * Domain Path:       /languages
 *
 * @package GatherpressTaxonomyColors
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_TAXONOMY_COLORS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_TAXONOMY_COLORS_CORE_PATH', __DIR__ );

/**
 * Adds the GatherpressTaxonomyColors namespace to the GatherPress autoloader.
 *
 * @param array<string, string> $namespaces An associative array of namespaces and their paths.
 * @return array<string, string> Modified array of namespaces and their paths.
 */
function gatherpress_taxonomy_colors_autoloader( array $namespaces ): array {
	$namespaces['GatherpressTaxonomyColors'] = GATHERPRESS_TAXONOMY_COLORS_CORE_PATH;

	return $namespaces;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_taxonomy_colors_autoloader' );

/**
 * Initialize the plugin once GatherPress core is loaded.
 *
 * @since 0.1.0
 * @return void
 */
function gatherpress_taxonomy_colors_setup(): void {
	if ( defined( 'GATHERPRESS_VERSION' ) ) {
		\GatherpressTaxonomyColors\Plugin::get_instance();
	}
}
add_action( 'plugins_loaded', 'gatherpress_taxonomy_colors_setup' );
