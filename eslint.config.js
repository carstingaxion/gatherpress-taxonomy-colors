/**
 * ESLint flat config for gatherpress-taxonomy-colors.
 *
 * Extends the @wordpress/scripts defaults and turns off import-resolution
 * rules for @wordpress/* packages. These are WordPress externals provided
 * by the block editor at runtime — they are intentionally absent from
 * node_modules and cannot be resolved statically.
 */

// @ts-check
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			'import/no-extraneous-dependencies': 'off',
		},
	},
];
