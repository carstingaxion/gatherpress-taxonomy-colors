/**
 * View Script: Taxonomy Color Roadmap — Frontend Interactivity
 *
 * @since 0.1.0
 */

/**
 * Initializes expand/collapse behavior for all roadmap block instances.
 *
 * @return {void}
 */
function initRoadmapBlocks() {
	const blocks = document.querySelectorAll(
		'.wp-block-gatherpress-taxonomy-colors'
	);

	blocks.forEach( function initBlock( block ) {
		const headers = block.querySelectorAll(
			'.taxonomy-color-roadmap__phase-header'
		);

		headers.forEach( function bindToggle( header ) {
			header.addEventListener( 'click', function handlePhaseToggle() {
				const phase = header.closest(
					'.taxonomy-color-roadmap__phase'
				);

				if ( ! phase ) {
					return;
				}

				const isNowExpanded = phase.classList.toggle(
					'taxonomy-color-roadmap__phase--expanded'
				);

				header.setAttribute(
					'aria-expanded',
					isNowExpanded ? 'true' : 'false'
				);
			} );
		} );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initRoadmapBlocks );
} else {
	initRoadmapBlocks();
}
