/**
 * Block Registration: Taxonomy Color Roadmap
 *
 * @since 0.1.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { registerPlugin } from '@wordpress/plugins';

import './style.scss';

import Edit from './edit';
import metadata from './block.json';
import ShadowColorsPanel from './shadow-colors-panel';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );

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
