<?php
/**
 * PHPUnit bootstrap file for the GatherPress Taxonomy Colors plugin.
 *
 * Supports both wp-env and local WordPress test environments.
 * Requires GatherPress to be installed and activated in the test environment.
 *
 * Usage with wp-env:
 *   npx wp-env run tests-cli --env-cwd='wp-content/plugins/gatherpress-taxonomy-colors' \
 *     bash -c 'WP_TESTS_DIR=/wordpress-phpunit vendor/bin/phpunit'
 *
 * @package GatherpressTaxonomyColors\Tests
 * @since   0.1.0
 */

// Composer autoloader for test dependencies.
$autoloader = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

// Determine the WordPress test suite location.
// Priority: WP_TESTS_DIR env var > wp-env default > local fallback.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at: {$wp_tests_dir}" . PHP_EOL;
	echo PHP_EOL;
	echo 'Set the WP_TESTS_DIR environment variable to point to your WordPress test suite.' . PHP_EOL;
	echo 'When using wp-env, run:' . PHP_EOL;
	echo '  npx wp-env run tests-cli --env-cwd="wp-content/plugins/gatherpress-taxonomy-colors" bash -c "WP_TESTS_DIR=/wordpress-phpunit vendor/bin/phpunit"' . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load GatherPress and this plugin before tests run.
 *
 * GatherPress must load first because our plugin depends on it
 * (uses Core\Traits\Singleton, gatherpress_event post type, etc.).
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		// Load GatherPress first — our plugin depends on it.
		$gatherpress_path = WP_PLUGIN_DIR . '/gatherpress/gatherpress.php';
		if ( file_exists( $gatherpress_path ) ) {
			require $gatherpress_path;
		} else {
			echo 'GatherPress plugin not found at: ' . $gatherpress_path . PHP_EOL;
			echo 'Ensure GatherPress is installed in the test environment.' . PHP_EOL;
			echo 'The .wp-env.json should include GatherPress in the plugins list.' . PHP_EOL;
			exit( 1 );
		}

		// Load our plugin.
		require dirname( __DIR__, 2 ) . '/plugin.php';
	}
);

// Start up the WP testing environment.
require $wp_tests_dir . '/includes/bootstrap.php';

// Load the base test case class for integration tests.
$integration_test_case = __DIR__ . '/integration/TestCase.php';
if ( file_exists( $integration_test_case ) ) {
	require_once $integration_test_case;
}
