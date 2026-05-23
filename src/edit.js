/**
 * Edit Component: Taxonomy Color Roadmap
 *
 * @since 0.1.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	ColorPicker,
} from '@wordpress/components';

import './editor.scss';

/**
 * The Edit function.
 *
 * @param {Object}   props                           Block props.
 * @param {Object}   props.attributes                Current attribute values.
 * @param {boolean}  props.attributes.showCodeExamples Whether to display code snippets.
 * @param {boolean}  props.attributes.defaultExpanded  Whether phases start expanded.
 * @param {string}   props.attributes.accentColor      Hex color for accent elements.
 * @param {Function} props.setAttributes              Setter to update block attributes.
 *
 * @return {Element} The editor element tree.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { showCodeExamples, defaultExpanded, accentColor } = attributes;

	const blockProps = useBlockProps( {
		className: 'taxonomy-color-roadmap__editor',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Roadmap Display', 'gatherpress-taxonomy-colors' ) }
					initialOpen={ true }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Code Examples', 'gatherpress-taxonomy-colors' ) }
						help={
							showCodeExamples
								? __( 'Code snippets are visible in the roadmap.', 'gatherpress-taxonomy-colors' )
								: __( 'Code snippets are hidden for a cleaner read.', 'gatherpress-taxonomy-colors' )
						}
						checked={ showCodeExamples }
						onChange={ ( value ) =>
							setAttributes( { showCodeExamples: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Expand All Phases', 'gatherpress-taxonomy-colors' ) }
						help={
							defaultExpanded
								? __( 'All phases start expanded on the frontend.', 'gatherpress-taxonomy-colors' )
								: __( 'Phases start collapsed; visitors click to expand.', 'gatherpress-taxonomy-colors' )
						}
						checked={ defaultExpanded }
						onChange={ ( value ) =>
							setAttributes( { defaultExpanded: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Accent Color', 'gatherpress-taxonomy-colors' ) }
					initialOpen={ false }
				>
					<ColorPicker
						color={ accentColor }
						onChange={ ( value ) =>
							setAttributes( { accentColor: value } )
						}
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="taxonomy-color-roadmap__editor-placeholder">
					<svg
						className="taxonomy-color-roadmap__editor-icon"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						width="48"
						height="48"
						aria-hidden="true"
						focusable="false"
					>
						<path
							fill={ accentColor }
							d="M3.5 18.5h17v-1H3.5v1zm0-4h10v-1h-10v1zm0-4h17v-1H3.5v1zm0-4h10v-1h-10v1z"
						/>
					</svg>
					<div className="taxonomy-color-roadmap__editor-text">
						<strong className="taxonomy-color-roadmap__editor-title">
						{ __( 'Taxonomy Color Roadmap', 'gatherpress-taxonomy-colors' ) }
					</strong>
					<span className="taxonomy-color-roadmap__editor-description">
						{ __(
							'A 6-layer architectural guide for per-taxonomy color-coding in WordPress using design tokens via theme.json + dynamic CSS custom properties — including shadow taxonomy support for post types. Preview the page to see the full roadmap.',
							'gatherpress-taxonomy-colors'
						) }
					</span>
					</div>
				</div>
				<div className="taxonomy-color-roadmap__editor-meta">
					<span
						className="taxonomy-color-roadmap__editor-badge"
						style={ { backgroundColor: accentColor } }
					>
						{ __( '6 Layers', 'gatherpress-taxonomy-colors' ) }
					</span>
					<span className="taxonomy-color-roadmap__editor-badge taxonomy-color-roadmap__editor-badge--secondary">
						{ showCodeExamples
							? __( 'Code examples: ON', 'gatherpress-taxonomy-colors' )
							: __( 'Code examples: OFF', 'gatherpress-taxonomy-colors' ) }
					</span>
					<span className="taxonomy-color-roadmap__editor-badge taxonomy-color-roadmap__editor-badge--secondary">
						{ defaultExpanded
							? __( 'Expanded by default', 'gatherpress-taxonomy-colors' )
							: __( 'Collapsed by default', 'gatherpress-taxonomy-colors' ) }
					</span>
				</div>
			</div>
		</>
	);
}
