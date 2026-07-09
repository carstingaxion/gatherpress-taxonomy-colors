/**
 * WordPress dependencies.
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies.
 */
import ShadowColorsPanel from './shadow-colors-panel';

/**
 * Registers the shadow term colors sidebar panel plugin.
 *
 * The panel is rendered for all post types but self-hides when the
 * current post type is not a confirmed shadow-source. The config
 * map is provided by the server via wp_add_inline_script.
 *
 * @since 0.1.3
 */
registerPlugin( 'gptc-shadow-colors', {
	render: ShadowColorsPanel,
	icon: 'art',
} );
