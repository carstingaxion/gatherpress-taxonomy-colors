/**
 * Shadow Term Colors — Editor Sidebar Panel
 *
 * Provides primary and secondary color pickers for shadow-source post types
 * directly in the block editor sidebar. Styled to match core's Color panel.
 *
 * @since 0.1.3
 * @package GatherpressTaxonomyColors
 */

import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	PanelRow,
	ColorPalette,
	Dropdown,
	Button,
	Spinner,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	ColorIndicator,
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';

/**
 * A single color row that mimics core's Color panel item
 * (swatch + label, click to toggle a dropdown with ColorPalette).
 *
 * @param {Object}   props
 * @param {string}   props.label    Display label.
 * @param {string}   props.value    Current hex value or empty.
 * @param {Function} props.onChange Callback with new hex or undefined to clear.
 * @param {Array}    props.colors   Theme palette colors for the picker.
 * @return {Element} Color row element.
 */
function ColorRow( { label, value, onChange, colors } ) {
	const hasValue = !! value;

	return (
		<Dropdown
			popoverProps={ { placement: 'left-start', offset: 36 } }
			className="gptc-color-row-dropdown"
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					onClick={ onToggle }
					aria-expanded={ isOpen }
					className="gptc-color-row-button"
					style={ {
						display: 'flex',
						alignItems: 'center',
						gap: '8px',
						width: '100%',
						padding: '8px 0',
						justifyContent: 'flex-start',
						height: 'auto',
						background: 'transparent',
						boxShadow: 'none',
					} }
					variant="tertiary"
				>
					{ hasValue ? (
						<span
							style={ {
								display: 'inline-block',
								width: '24px',
								height: '24px',
								borderRadius: '50%',
								background: value,
								border: '1px solid rgba(0, 0, 0, 0.1)',
								flexShrink: 0,
							} }
						/>
					) : (
						<span
							style={ {
								display: 'inline-flex',
								alignItems: 'center',
								justifyContent: 'center',
								width: '24px',
								height: '24px',
								borderRadius: '50%',
								border: '1px solid rgba(0, 0, 0, 0.1)',
								background: 'transparent',
								flexShrink: 0,
								overflow: 'hidden',
								position: 'relative',
							} }
						>
							<svg
								xmlns="http://www.w3.org/2000/svg"
								viewBox="0 0 24 24"
								width="24"
								height="24"
								style={ {
									position: 'absolute',
									top: 0,
									left: 0,
								} }
								aria-hidden="true"
								focusable="false"
							>
								<line
									x1="5"
									y1="5"
									x2="19"
									y2="19"
									stroke="rgba(207, 0, 0, 0.7)"
									strokeWidth="2"
								/>
							</svg>
						</span>
					) }
					<span style={ { fontSize: '13px', color: '#1e1e1e' } }>
						{ label }
					</span>
				</Button>
			) }
			renderContent={ () => (
				<div style={ { padding: '16px', minWidth: '260px' } }>
					<ColorPalette
						colors={ colors }
						value={ value || undefined }
						onChange={ ( newValue ) => onChange( newValue || '' ) }
						clearable={ true }
					/>
				</div>
			) }
		/>
	);
}

/**
 * Shadow Term Colors sidebar panel.
 *
 * @return {Element|null} The sidebar panel element or null.
 */
export default function ShadowColorsPanel() {
	const { postType, postId, postSlug, postTypeLabel, themeColors } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const currentPostType = editor.getCurrentPostType();

		let singularLabel = '';
		if ( currentPostType ) {
			const typeObj = select( 'core' ).getPostType( currentPostType );
			singularLabel = typeObj?.labels?.singular_name || currentPostType;
		}

		// Grab the theme palette so the picker shows theme colors.
		const settings = select( 'core/block-editor' ).getSettings();
		const palette = settings?.colors || [];

		return {
			postType: currentPostType,
			postId: editor.getCurrentPostId(),
			postSlug: editor.getEditedPostAttribute( 'slug' ),
			postTypeLabel: singularLabel,
			themeColors: palette,
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	const config = window.gptcShadowConfig || {};
	const taxonomySlug = config[ postType ] || '';

	const [ termId, setTermId ] = useState( null );
	const [ primaryColor, setPrimaryColor ] = useState( '' );
	const [ secondaryColor, setSecondaryColor ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	// Refs for debounced save.
	const saveTimerRef = useRef( null );
	const colorsRef = useRef( { primary: '', secondary: '' } );

	const fetchTermColors = useCallback( () => {
		if ( ! taxonomySlug || ! postSlug ) {
			setLoading( false );
			return;
		}

		setLoading( true );

		const termSlug = '_' + postSlug;

		apiFetch( {
			path: `/wp/v2/${ encodeURIComponent( taxonomySlug ) }?slug=${ encodeURIComponent( termSlug ) }&per_page=1`,
		} )
			.then( ( terms ) => {
				if ( terms && terms.length > 0 ) {
					const term = terms[ 0 ];
					setTermId( term.id );
					const p = term.meta?.term_color || '';
					const s = term.meta?.term_color_secondary || '';
					setPrimaryColor( p );
					setSecondaryColor( s );
					colorsRef.current = { primary: p, secondary: s };
				} else {
					setTermId( null );
				}
			} )
			.catch( () => {
				setTermId( null );
			} )
			.finally( () => {
				setLoading( false );
			} );
	}, [ taxonomySlug, postSlug ] );

	useEffect( () => {
		fetchTermColors();
	}, [ fetchTermColors ] );

	/**
	 * Debounced auto-save — fires 600ms after last change.
	 *
	 * After persisting term meta via the REST API, marks the post
	 * as dirty so the editor's "Save" button becomes active. The
	 * actual color data lives on the term, but the post needs a
	 * save cycle to regenerate server-side palette resolution
	 * (Layer 4 theme.json filter runs during editor bootstrap).
	 */
	const scheduleSave = useCallback( () => {
		if ( ! termId || ! taxonomySlug ) {
			return;
		}

		if ( saveTimerRef.current ) {
			clearTimeout( saveTimerRef.current );
		}

		saveTimerRef.current = setTimeout( () => {
			apiFetch( {
				path: `/wp/v2/${ encodeURIComponent( taxonomySlug ) }/${ termId }`,
				method: 'POST',
				data: {
					meta: {
						term_color: colorsRef.current.primary,
						term_color_secondary: colorsRef.current.secondary,
					},
				},
			} )
				.then( () => {
					// Mark the post as dirty so the editor enables the
					// Save button. We write a timestamp into post meta
					// that the server can ignore — it only needs to
					// trigger a post save so the editor re-bootstraps
					// with fresh palette resolution.
					editPost( {
						meta: {
							_gptc_colors_updated: Date.now().toString(),
						},
					} );
				} )
				.catch( () => {
					// Silent failure — the panel is non-critical UI.
				} );
		}, 600 );
	}, [ termId, taxonomySlug, editPost ] );

	const handlePrimaryChange = useCallback(
		( value ) => {
			const hex = value || '';
			setPrimaryColor( hex );
			colorsRef.current.primary = hex;
			scheduleSave();
		},
		[ scheduleSave ]
	);

	const handleSecondaryChange = useCallback(
		( value ) => {
			const hex = value || '';
			setSecondaryColor( hex );
			colorsRef.current.secondary = hex;
			scheduleSave();
		},
		[ scheduleSave ]
	);

	// Cleanup timer on unmount.
	useEffect( () => {
		return () => {
			if ( saveTimerRef.current ) {
				clearTimeout( saveTimerRef.current );
			}
		};
	}, [] );

	if ( ! taxonomySlug ) {
		return null;
	}

	/* translators: %s: post type singular label, e.g. "Venue" */
	const panelTitle = postTypeLabel
		? postTypeLabel + ' ' + __( 'Color', 'gatherpress-taxonomy-colors' )
		: __( 'Term Colors', 'gatherpress-taxonomy-colors' );

	return (
		<PluginDocumentSettingPanel
			name="gptc-shadow-term-colors"
			title={ panelTitle }
			className="gptc-shadow-colors-panel"
		>
			{ loading && (
				<PanelRow>
					<Spinner />
				</PanelRow>
			) }

			{ ! loading && ! termId && (
				<p className="description">
					{ __(
						'Shadow term not found. Publish the post first so the shadow term is created.',
						'gatherpress-taxonomy-colors'
					) }
				</p>
			) }

			{ ! loading && termId && (
				<VStack spacing={ 0 }>
					<ColorRow
						label={ __( 'Primary', 'gatherpress-taxonomy-colors' ) }
						value={ primaryColor }
						onChange={ handlePrimaryChange }
						colors={ themeColors }
					/>
					<ColorRow
						label={ __( 'Secondary', 'gatherpress-taxonomy-colors' ) }
						value={ secondaryColor }
						onChange={ handleSecondaryChange }
						colors={ themeColors }
					/>
				</VStack>
			) }
		</PluginDocumentSettingPanel>
	);
}
