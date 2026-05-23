<?php
/**
 * Server-side rendering for the Taxonomy Color Roadmap block.
 *
 * @since   0.1.0
 * @package GatherpressTaxonomyColors
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      The WP_Block instance.
 */

namespace GatherpressTaxonomyColors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Renderer' ) ) {

	/**
	 * Singleton renderer for the Taxonomy Color Roadmap block.
	 *
	 * @since 0.1.0
	 */
	final class Renderer {

		/**
		 * @since 0.1.0
		 * @var Renderer|null
		 */
		private static ?Renderer $instance = null;

		/**
		 * @since 0.1.0
		 * @var array<int, array{number: int, title: string, summary: string, content: string, code: string, code_label: string}>
		 */
		private array $phases = array();

		/**
		 * @since  0.1.0
		 * @return Renderer
		 */
		public static function get_instance(): Renderer {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * @since 0.1.0
		 */
		private function __construct() {
			$this->phases = $this->build_phases();
		}

		/** @since 0.1.0 */
		private function __clone() {}

		/**
		 * @since  0.1.0
		 * @throws \RuntimeException Always.
		 * @return void
		 */
		public function __wakeup(): void {
			throw new \RuntimeException(
				esc_html__( 'Cannot unserialize a Singleton.', 'gatherpress-taxonomy-colors' )
			);
		}

		/**
		 * Renders the complete roadmap HTML.
		 *
		 * @since  0.1.0
		 * @param  array{showCodeExamples: bool, defaultExpanded: bool, accentColor: string} $attributes Block attributes.
		 * @return string The complete roadmap HTML.
		 */
		public function render( array $attributes ): string {
			$show_code        = ! empty( $attributes['showCodeExamples'] );
			$default_expanded = ! empty( $attributes['defaultExpanded'] );
			$accent_color     = ! empty( $attributes['accentColor'] )
				? sanitize_hex_color( $attributes['accentColor'] )
				: '#0073aa';

			$wrapper_attrs = get_block_wrapper_attributes(
				array(
					'class' => 'taxonomy-color-roadmap',
					'style' => '--roadmap-accent: ' . esc_attr( $accent_color ) . ';',
				)
			);

			$output  = '<div ' . $wrapper_attrs . '>';
			$output .= $this->render_header();
			$output .= $this->render_problem();
			$output .= $this->render_architecture_intro( $show_code );
			$output .= $this->render_shadow_taxonomy_intro();
			$output .= '<div class="taxonomy-color-roadmap__phases">';

			foreach ( $this->phases as $phase ) {
				$output .= $this->render_phase( $phase, $show_code, $default_expanded );
			}

			$output .= '</div>';
			$output .= $this->render_footer();
			$output .= '</div>';

			return $output;
		}

		/**
		 * @since  0.1.0
		 * @return string
		 */
		private function render_header(): string {
			$title    = esc_html__( 'Taxonomy Color Roadmap', 'gatherpress-taxonomy-colors' );
			$subtitle = esc_html__( 'Design Tokens via theme.json + Dynamic CSS Custom Properties', 'gatherpress-taxonomy-colors' );

			return '<div class="taxonomy-color-roadmap__header">'
				. '<h2 class="taxonomy-color-roadmap__title">' . $title . '</h2>'
				. '<p class="taxonomy-color-roadmap__subtitle">' . $subtitle . '</p>'
				. '</div>';
		}

		/**
		 * @since  0.1.0
		 * @return string
		 */
		private function render_problem(): string {
			$label = esc_html__( 'The Core Problem', 'gatherpress-taxonomy-colors' );

			$goals  = '<ol>';
			$goals .= '<li>' . __( 'An editor assigns a color to each term (e.g., category "Technology" &rarr; blue).', 'gatherpress-taxonomy-colors' ) . '</li>';
			$goals .= '<li>' . __( 'In the block/site editor, that color appears in the native color picker alongside theme palette colors.', 'gatherpress-taxonomy-colors' ) . '</li>';
			$goals .= '<li>' . __( 'The color is contextual &mdash; it resolves based on which term is relevant to the current post/template.', 'gatherpress-taxonomy-colors' ) . '</li>';
			$goals .= '<li>' . __( 'In the editor without context, the picker shows "Term Color" as an abstract slot (like a design token). With context (e.g., editing a post tagged "Technology"), it resolves to the actual hex value.', 'gatherpress-taxonomy-colors' ) . '</li>';
			$goals .= '<li>' . __( 'On the frontend, it always resolves correctly.', 'gatherpress-taxonomy-colors' ) . '</li>';
			$goals .= '</ol>';

			return '<div class="taxonomy-color-roadmap__intro">'
				. '<span class="taxonomy-color-roadmap__intro-label">' . $label . '</span>'
				. '<p>' . __( 'You want:', 'gatherpress-taxonomy-colors' ) . '</p>'
				. $goals
				. '</div>';
		}

		/**
		 * @since  0.1.0
		 * @param  bool $show_code Whether to include the conceptual model code.
		 * @return string
		 */
		private function render_architecture_intro( bool $show_code ): string {
			$label = esc_html__( 'The Architecture', 'gatherpress-taxonomy-colors' );

			$output  = '<div class="taxonomy-color-roadmap__intro">';
			$output .= '<span class="taxonomy-color-roadmap__intro-label">' . $label . '</span>';
			$output .= '<p>' . __( 'The solution that feels closest to core is to treat term colors not as literal colors, but as <strong>semantic design tokens</strong> &mdash; exactly how <code>theme.json</code> presets already work.', 'gatherpress-taxonomy-colors' ) . '</p>';
			$output .= '<p>' . __( 'This mirrors how WordPress already handles colors. The editor sees "Term Primary" in the color picker. The actual value is injected dynamically.', 'gatherpress-taxonomy-colors' ) . '</p>';

			if ( $show_code ) {
				$model = "theme.json palette (per taxonomy):\n"
					. "  \"category-primary\"   ->  var(--wp--preset--color--category-primary)\n"
					. "  \"category-secondary\" ->  var(--wp--preset--color--category-secondary)\n"
					. "  \"post_tag-primary\"   ->  var(--wp--preset--color--post_tag-primary)\n"
					. "  \"post_tag-secondary\" ->  var(--wp--preset--color--post_tag-secondary)\n\n"
					. "Term meta:\n"
					. "  Category \"Technology\"  ->  #2563eb (primary), #93c5fd (secondary)\n"
					. "  Tag \"Breaking\"         ->  #dc2626 (primary)\n\n"
					. "Frontend/Editor resolution (on a post in \"Technology\" + \"Breaking\"):\n"
					. "  --flavor--category-primary:   #2563eb;\n"
					. "  --flavor--category-secondary:  #93c5fd;\n"
					. "  --flavor--post_tag-primary:    #dc2626;";

				$output .= '<span class="taxonomy-color-roadmap__code-label">' . esc_html__( 'Conceptual Model', 'gatherpress-taxonomy-colors' ) . '</span>';
				$output .= '<pre class="taxonomy-color-roadmap__code"><code>' . esc_html( $model ) . '</code></pre>';
			}

			$output .= '</div>';

			return $output;
		}

		/**
		 * @since  0.1.0
		 * @return string
		 */
		private function render_shadow_taxonomy_intro(): string {
			$label = esc_html__( 'Extended Scope: Shadow Taxonomies', 'gatherpress-taxonomy-colors' );

			return '<div class="taxonomy-color-roadmap__intro">'
				. '<span class="taxonomy-color-roadmap__intro-label">' . $label . '</span>'
				. '<p>' . __( 'Some architectures use a <strong>shadow taxonomy</strong> pattern: a hidden taxonomy where each published post of a specific type is mirrored by a term. Consumers (events, sessions, etc.) tag themselves with that term to model relationships. This extends the color system to work with these post-type-as-taxonomy structures &mdash; moving the admin UI to the post editor and using specialised helpers for term resolution.', 'gatherpress-taxonomy-colors' ) . '</p>'
				. '</div>';
		}

		/**
		 * @since  0.1.0
		 * @param  array{number: int, title: string, summary: string, content: string, code: string, code_label: string} $phase Layer data.
		 * @param  bool  $show_code        Whether to include the code example.
		 * @param  bool  $default_expanded  Whether the layer starts expanded.
		 * @return string
		 */
		private function render_phase( array $phase, bool $show_code, bool $default_expanded ): string {
			$expanded_class = $default_expanded ? ' taxonomy-color-roadmap__phase--expanded' : '';
			$aria_expanded  = $default_expanded ? 'true' : 'false';

			$output = '<div class="taxonomy-color-roadmap__phase' . esc_attr( $expanded_class ) . '">';

			$output .= '<button class="taxonomy-color-roadmap__phase-header" aria-expanded="' . esc_attr( $aria_expanded ) . '">';
			$output .= '<span class="taxonomy-color-roadmap__phase-number">' . esc_html( (string) $phase['number'] ) . '</span>';
			$output .= '<span class="taxonomy-color-roadmap__phase-info">';
			$output .= '<span class="taxonomy-color-roadmap__phase-title">' . esc_html( $phase['title'] ) . '</span>';
			$output .= '<span class="taxonomy-color-roadmap__phase-summary">' . esc_html( $phase['summary'] ) . '</span>';
			$output .= '</span>';

			$output .= '<svg class="taxonomy-color-roadmap__phase-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"></polyline></svg>';

			$output .= '</button>';

			$output .= '<div class="taxonomy-color-roadmap__phase-content">';
			$output .= wp_kses_post( $phase['content'] );

			if ( $show_code && ! empty( $phase['code'] ) ) {
				$output .= '<span class="taxonomy-color-roadmap__code-label">' . esc_html( $phase['code_label'] ) . '</span>';
				$output .= '<pre class="taxonomy-color-roadmap__code"><code>' . esc_html( $phase['code'] ) . '</code></pre>';
			}

			$output .= '</div>';
			$output .= '</div>';

			return $output;
		}

		/**
		 * @since  0.1.0
		 * @return string
		 */
		private function render_footer(): string {
			$summary = esc_html__( 'This architecture uses only public WordPress APIs. No core patches, no fragile hacks — just term meta, a theme.json filter with CSS custom property indirection, contextual resolution, scoped per-post properties for multi-post contexts, and shadow taxonomy awareness for post types acting as quasi-taxonomies. Term colors become first-class palette citizens that any block can consume.', 'gatherpress-taxonomy-colors' );
			$tagline = esc_html__( 'Built to feel like core. Designed to scale.', 'gatherpress-taxonomy-colors' );

			return '<div class="taxonomy-color-roadmap__footer">'
				. '<p>' . $summary . '</p>'
				. '<p class="taxonomy-color-roadmap__footer-tagline">' . $tagline . '</p>'
				. '</div>';
		}

		/**
		 * @since  0.1.0
		 * @return array<int, array{number: int, title: string, summary: string, content: string, code: string, code_label: string}>
		 */
		private function build_phases(): array {
			return array(
				$this->build_layer_1(),
				$this->build_layer_2(),
				$this->build_layer_3(),
				$this->build_layer_4(),
				$this->build_layer_5(),
				$this->build_layer_6(),
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_1(): array {
			return array(
				'number'     => 1,
				'title'      => __( 'Term Meta for Color Storage', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'The data layer. Each term gets a primary and secondary color stored as term meta.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'This is the data layer. Each term gets a color stored as term meta. WordPress has supported term meta since version 4.4 via <code>add_term_meta()</code>, <code>get_term_meta()</code>, and <code>update_term_meta()</code>. This is the canonical, core-blessed way to store additional data on taxonomy terms.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'For each taxonomy you want to color-code, register two meta keys &mdash; <code>term_color</code> (primary) and <code>term_color_secondary</code> &mdash; using <code>register_term_meta()</code>. This function accepts a schema definition and, critically, a <code>show_in_rest</code> flag that exposes the value to the block editor via the REST API.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why register_term_meta with show_in_rest?', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'This is the same pattern core uses for post meta. The REST visibility is essential for the editor integration &mdash; it allows the block editor to read term colors without custom endpoints.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; <code>register_term_meta()</code> for both <code>term_color</code> and <code>term_color_secondary</code> with <code>show_in_rest</code>, <code>sanitize_hex_color</code> callback, and <code>single =&gt; true</code> for all configurable taxonomies.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Filterable taxonomy list via <code>gptc_term_color_taxonomies</code> filter (defaults to <code>category</code> and <code>post_tag</code>).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Primary and secondary color picker fields on both the "Add New Term" and "Edit Term" admin forms.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Save handlers for term creation and edits with nonce verification and capability checks.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Color swatch column in the taxonomy list table showing both primary and secondary colors.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>wp-color-picker</code> assets enqueued only on relevant admin screens.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Layer 2: Per-taxonomy abstract design token slots in <code>theme.json</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Layer 3: Per-taxonomy frontend CSS custom property injection.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Layer 4: Per-taxonomy editor integration via <code>wp_theme_json_data_theme</code> and editor CSS injection.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Layer 5: Scoped per-post resolution for Query Loop and archive contexts &mdash; injected onto existing elements, no wrapper divs.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Layer 6: Shadow taxonomy support for post types with <code>gatherpress-shadow-source</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_2(): array {
			return array(
				'number'     => 2,
				'title'      => __( 'The Design Token in theme.json — Per Taxonomy', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'Define abstract color slot pairs per taxonomy in theme.json that reference CSS custom properties, not literal hex values.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'Here is where the architecture becomes interesting. For each color-enabled taxonomy, you define a pair of abstract color slots in <code>theme.json</code>. The palette entries use <strong>literal fallback hex values</strong> &mdash; not <code>var()</code> references &mdash; because WordPress core\'s <code>wp_get_global_stylesheet()</code> sanitizes palette <code>color</code> fields and strips CSS function references. Slots are generated dynamically from the taxonomy list, e.g.:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ul>'
					. '<li>' . __( '<code>category-primary</code> &rarr; <code>#8b7e74</code> (fallback hex) &mdash; labelled "Category Color (Primary)"', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<code>category-secondary</code> &rarr; <code>#b8aea6</code> (fallback hex) &mdash; labelled "Category Color (Secondary)"', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<code>post_tag-primary</code> &rarr; <code>#6e7f8d</code> (fallback hex) &mdash; labelled "Tag Color (Primary)"', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<code>post_tag-secondary</code> &rarr; <code>#a3b1bc</code> (fallback hex) &mdash; labelled "Tag Color (Secondary)"', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<p>' . __( 'The <code>var()</code> indirection is then restored via a separate CSS override that re-declares each <code>--wp--preset--color--{slug}</code> property:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ul>'
					. '<li>' . __( '<code>--wp--preset--color--category-primary: var(--flavor--category-primary, #8b7e74);</code>', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<code>--wp--preset--color--post_tag-primary: var(--flavor--post_tag-primary, #6e7f8d);</code>', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<p>' . __( 'The resolution chain per slot is: <code>--wp--preset--color--category-primary</code> (CSS override) &rarr; <code>var(--flavor--category-primary, #8b7e74)</code> &rarr; resolved by the injected custom property or the fallback.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why literal hex in theme.json + CSS override?', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'WordPress core\'s <code>wp_get_global_stylesheet()</code> processes palette entries through a sanitizer that expects literal color values (hex, rgb, hsl). Passing <code>var(--flavor--..., #888888)</code> as the palette <code>color</code> results in WordPress stripping the <code>var()</code> and emitting only the fallback. The architectural pivot: register the fallback hex in theme.json (satisfying the sanitizer and making swatches visible), then override the generated <code>--wp--preset--color--*</code> CSS custom property with a <code>var()</code> reference in a later stylesheet. CSS custom properties on <code>:root</code> in a later source order always win &mdash; same specificity, later declaration takes precedence.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why per-taxonomy slots?', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'A post may belong to multiple taxonomies simultaneously. Per-taxonomy slots let a post resolve <code>category-primary: #2563eb</code> AND <code>post_tag-primary: #dc2626</code> independently &mdash; enabling multi-taxonomy color schemes without slot collisions.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p><strong>' . __( 'Why Two Layers of Custom Properties?', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'You might ask: why not inject <code>--wp--preset--color--category-primary</code> directly? Because WordPress generates that property from <code>theme.json</code>. You would be fighting the system. Instead, you define an intermediate custom property (<code>--flavor--category-primary</code>) and use a CSS override to make the preset reference it. You control the intermediate property; WordPress controls its own preset generation. Clean separation.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; <code>get_term_color_slots()</code> dynamically generates slot pairs from <code>get_color_taxonomies()</code>, using each taxonomy\'s registered label for human-readable names.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Each slot carries <code>taxonomy</code> and <code>meta_key</code> fields for resolution traceability.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Filterable slot definitions via <code>gptc_term_color_slots</code> filter &mdash; themes/plugins can add tertiary slots, change fallbacks, or remove a taxonomy\'s slots entirely.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>wp_theme_json_data_theme</code> filter merges all slots into the theme-origin palette via <code>update_with()</code> using literal fallback hex values.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Separate CSS override (<code>inject_preset_custom_property_overrides()</code>) re-declares each <code>--wp--preset--color--{slug}</code> with <code>var(--flavor--{slug}, {fallback})</code> on both frontend and editor.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Architectural pivot: works around WordPress core\'s palette sanitizer stripping <code>var()</code> from theme.json color values.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Slots sit at theme priority &mdash; overridable by user Global Styles, takes precedence over core defaults.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Adding a new taxonomy to the filter automatically generates new palette entries &mdash; zero additional code needed.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_3(): array {
			return array(
				'number'     => 3,
				'title'      => __( 'Dynamic Resolution — Per-Taxonomy CSS Custom Property Injection', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'Inject per-taxonomy color values on the frontend based on post/archive context.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'This is the critical piece. Each taxonomy resolves its own slot pair independently based on context. On the frontend, you know which post is being rendered, so you know its terms across all taxonomies.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'The approach uses <code>wp_add_inline_style()</code> hooked to <code>wp_enqueue_scripts</code> to inject CSS custom properties onto <code>:root</code>. The <code>resolve_contextual_term_colors()</code> method iterates over each color-enabled taxonomy and resolves independently:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ul>'
					. '<li>' . __( '<strong>Singular posts</strong>: For each taxonomy, finds the first term with a primary color. That term\'s colors populate <code>{taxonomy}-primary</code> and <code>{taxonomy}-secondary</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Archive pages</strong>: The queried term\'s colors populate the slots for its own taxonomy only.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Example resolution:', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'A post in category "Technology" (#2563eb) and tagged "Breaking" (#dc2626) produces: <code>--flavor--category-primary: #2563eb; --flavor--post_tag-primary: #dc2626;</code>. Every block using "Category Color (Primary)" renders blue, while "Tag Color (Primary)" renders red &mdash; independently.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; <code>resolve_contextual_term_colors()</code> iterates each taxonomy independently, producing <code>{taxonomy}-primary</code> and <code>{taxonomy}-secondary</code> slot values.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Singular post support: first term with a primary color wins per taxonomy.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Taxonomy archive support: queried term resolves its own taxonomy\'s slots.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>inject_frontend_term_color_properties()</code> outputs all resolved <code>--flavor--{taxonomy}-{role}</code> custom properties on <code>:root</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Each <code>--flavor--{slot}</code> property overrides the fallback from the Layer 2 palette entry.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; All values pass through <code>sanitize_hex_color()</code> and <code>sanitize_key()</code> for defense-in-depth.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_4(): array {
			return array(
				'number'     => 4,
				'title'      => __( 'Editor Integration — Per-Taxonomy Resolution', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'Make per-taxonomy term colors resolve contextually inside the block editor.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'The frontend is straightforward. The editor is where it gets nuanced. When an editor picks "Category Color (Primary)" for a heading, the color picker needs to show something. There are three scenarios:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ol>'
					. '<li>' . __( '<strong>Editing a specific post</strong> &mdash; context exists, all taxonomy slot pairs can resolve from the post\'s terms.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Editing a template</strong> &mdash; no specific post context; fallback colors from Layer 2 are shown.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Editing a template part / pattern</strong> &mdash; same as templates.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ol>'
					. '<p><strong>' . __( 'Approach: wp_theme_json_data_theme Filter', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'The Layer 4 filter runs at priority 20 (after Layer 2 at priority 10) and replaces abstract <code>var(--flavor--...)</code> palette entries with concrete hex values when a post context is available. Each taxonomy\'s slots are resolved independently from the post\'s terms via <code>resolve_term_colors_for_post()</code>.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Editor-Side CSS Custom Properties:', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'The <code>enqueue_block_editor_assets</code> hook injects <code>--flavor--{taxonomy}-primary</code> / <code>--flavor--{taxonomy}-secondary</code> as inline CSS on <code>body</code> inside the editor iframe. This means: when editing a post in category "Technology" and tagged "Breaking", the editor sees the correct colors for BOTH taxonomy slot pairs simultaneously.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p>' . __( '<code>update_with()</code> merges palette entries by slug. Each per-taxonomy slot has a unique slug (<code>category-primary</code>, <code>post_tag-primary</code>, etc.), so there are no collisions. If no post context is available, the filter returns unchanged &mdash; all abstract fallbacks remain in effect.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; <code>resolve_term_colors_for_post( int $post_id )</code> resolves each taxonomy independently into <code>{taxonomy}-primary</code> / <code>{taxonomy}-secondary</code> slots.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>inject_editor_term_color_tokens()</code> at priority 20 replaces abstract palette entries with resolved hex values per taxonomy.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Post editor: all taxonomy slot pairs resolve from the edited post\'s terms.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Site editor / templates: no post context &rarr; all abstract fallbacks remain.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>inject_editor_term_color_styles()</code> injects all resolved <code>--flavor--{taxonomy}-{role}</code> properties on <code>body</code> via <code>wp_add_inline_style( \'wp-edit-blocks\', ... )</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; CSS custom properties scoped to <code>body</code> for correct cascade inside the editor iframe.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Both hooks guard against missing post context &mdash; graceful fallback.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; All values sanitized via <code>sanitize_hex_color()</code>, <code>sanitize_key()</code>, and <code>esc_attr()</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_5(): array {
			return array(
				'number'     => 5,
				'title'      => __( 'Query Loop & Multi-Post Scoped Resolution', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'Scope term color properties per post when multiple posts render on the same page — without wrapper divs.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'Layers 3 and 4 inject <code>--flavor--{taxonomy}-{role}</code> as global properties on <code>:root</code> (frontend) or <code>body</code> (editor). This is perfect when a single context dominates the page: a singular post, or a taxonomy archive where one term defines the colour. But what happens on an archive page or a Query Loop block where <strong>multiple posts</strong> render side-by-side, each with different term assignments?', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'The answer: <strong>the global custom property holds one value, so the last post to set it wins</strong>. Every post on the page would display the same color &mdash; the one from the last-resolved post. This is the main architectural limitation of the global approach.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p><strong>' . __( 'The Solution: Scoped Custom Properties Without Wrapper Divs', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'Instead of injecting on <code>:root</code>, scope the <code>--flavor--</code> properties to each post in the loop. The key architectural decision: <strong>inject directly onto the existing root HTML element</strong> of the first block rendered for each post, rather than wrapping in an extra <code>&lt;div&gt;</code>. This preserves grid/flex layouts and avoids non-semantic markup.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'The hook is <code>render_block</code>, which receives the block content, parsed block, and the <code>WP_Block</code> instance. When <code>$instance-&gt;context[\'postId\']</code> is set, we know we are inside a <code>core/post-template</code> iteration:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ul>'
					. '<li>' . __( '<strong>Step 1</strong>: Detect <code>core/post-template</code> context via <code>$instance-&gt;context[\'postId\']</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Step 2</strong>: Use a static tracker to ensure only the <strong>first</strong> block per post gets the injection &mdash; subsequent blocks in the same loop iteration inherit via CSS cascade.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Step 3</strong>: Call <code>resolve_term_colors_for_post( $post_id )</code> to get the per-taxonomy slot &rarr; hex map.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Step 4</strong>: Parse the first HTML opening tag of the block content. If it already has a <code>style</code> attribute, prepend our <code>--flavor--</code> declarations. If not, add a new <code>style</code> attribute. No wrapper div.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why inject on the existing element instead of wrapping?', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'Adding a <code>&lt;div&gt;</code> wrapper breaks CSS Grid and Flexbox layouts inside Query Loop blocks. A post grid using <code>display: grid</code> on the post-template container expects direct children to be the post elements &mdash; an extra wrapper becomes an unwanted grid item. Injecting onto the existing element is layout-transparent.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p><strong>' . __( 'Global vs. Scoped: When to Use Which', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul>'
					. '<li>' . __( '<strong>Global (<code>:root</code>)</strong>: Singular posts, taxonomy archives, anywhere a single context dominates. Simpler, no modifications to block output.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Scoped (inline on first block)</strong>: Query Loop blocks, archive templates with post grids, any template where multiple posts render. Required for correct per-post color resolution.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<p>' . __( 'Both coexist naturally. The global injection (Layer 3) sets the "page-level" context. The scoped injection overrides it locally per post. CSS custom property inheritance means the scoped value takes precedence inside the element, while the global value remains available outside it.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Cascade consideration:', 'gatherpress-taxonomy-colors' ) . '</strong> ' . __( 'Blocks using <code>color-mix(in srgb, var(--flavor--category-primary) 12%, transparent)</code> or similar derived values automatically pick up the scoped property &mdash; <code>color-mix()</code> resolves at computed-value time, not at parse time. No extra work needed for derived styles.', 'gatherpress-taxonomy-colors' ) . '</div>'
					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; Block-specific <code>render_block_core/post-template</code> filter &mdash; fires only once for the entire post-template output, not per inner block.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Uses <code>WP_HTML_Tag_Processor</code> to iterate over each <code>&lt;li class="wp-block-post"&gt;</code> in a single pass.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Extracts the post ID from the <code>post-{id}</code> CSS class (added by WordPress core\'s <code>post_class()</code>).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Resolves per-post term colors using existing <code>resolve_term_colors_for_post()</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Injects <code>--flavor--</code> custom properties directly onto each <code>&lt;li&gt;</code> element via <code>WP_HTML_Tag_Processor</code> &mdash; no wrapper div, no regex, no static tracker.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; The <code>&lt;li&gt;</code> is the natural scoping container &mdash; all inner blocks inherit custom properties via CSS cascade.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; Global (Layer 3) and scoped (Layer 5) injection coexist &mdash; scoped overrides global within the element subtree.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <code>color-mix()</code> and other derived values resolve correctly in scoped context.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'
					. '<p><strong>' . __( 'Ideas & Future Enhancements', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#11036; <strong>Editor Query Loop preview</strong>: Extend Layer 4 to detect Query Loop blocks in the site editor and inject scoped styles per preview post.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>REST API endpoint</strong>: Expose a <code>/wp-json/gptc/v1/term-colors/{post_id}</code> endpoint so JavaScript-driven UIs (e.g., AJAX pagination, Infinite Scroll) can fetch resolved colors per post without a full page reload.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Tertiary / accent slots</strong>: Allow more than two color roles per taxonomy via the <code>gptc_term_color_slots</code> filter &mdash; e.g., a "tertiary" or "accent" slot for richer color schemes.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Color inheritance</strong>: When a child term has no color, inherit from its parent term. Walks up the ancestor chain for hierarchical taxonomies (categories) so a "Web Development" subcategory inherits from "Technology". Non-hierarchical taxonomies are unaffected. Depth-limited to 10 levels.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Gutenberg sidebar panel</strong>: A custom sidebar panel in the post editor showing which term colors are currently active, with quick links to edit the source terms.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>WooCommerce integration</strong>: Extend <code>gptc_term_color_taxonomies</code> to include <code>product_cat</code> and <code>product_tag</code>, making product category colors available as design tokens in shop templates.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Block Bindings API</strong>: When WordPress stabilises the Block Bindings API, use it to bind block color attributes directly to term meta &mdash; eliminating the need for CSS custom property indirection entirely.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Dark mode awareness</strong>: Store a light and dark variant per term color, and switch based on the active theme variation or a <code>prefers-color-scheme</code> media query.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}

		/**
		 * @since  0.1.0
		 * @return array{number: int, title: string, summary: string, content: string, code: string, code_label: string}
		 */
		private function build_layer_6(): array {
			return array(
				'number'     => 6,
				'title'      => __( 'Shadow Taxonomy Support for Post Types', 'gatherpress-taxonomy-colors' ),
				'summary'    => __( 'Extend the color system to post types that use hidden "shadow taxonomies" — where each published post is mirrored by a term.', 'gatherpress-taxonomy-colors' ),
				'code_label' => '',
				'content'    =>
					'<p>' . __( 'Some WordPress architectures use a pattern called a <strong>shadow taxonomy</strong>: a hidden taxonomy (registered with <code>show_ui =&gt; false</code>) where one term is kept in lockstep with each published post of a specific post type. The term mirrors the post\'s slug and title. Consumers (events, sessions, productions, etc.) tag themselves with that term to model a relationship to the source post &mdash; effectively turning a post type into something that behaves like a taxonomy for querying and filtering purposes.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'In the GatherPress ecosystem, this is signalled by a post type declaring support for <code>gatherpress-shadow-source</code>. When a post type has this support, a hidden <code>_&lt;post_type&gt;</code> taxonomy is automatically registered, and one term per published post is maintained by the shadow system.', 'gatherpress-taxonomy-colors' ) . '</p>'

					. '<p><strong>' . __( 'Taxonomy Registration: The Filter as Single Source of Truth', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'The <code>gptc_term_color_taxonomies</code> filter is the first and single source of truth for which taxonomies participate in the color system. Shadow taxonomy support is derived entirely from this filter\'s return value &mdash; there is no separate detection scan of registered post types.', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<p>' . __( 'The algorithm:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ol>'
					. '<li>' . __( 'Read the filter return (an array of taxonomy slugs).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( 'For each slug that starts with <code>_</code> (underscore), check whether a post type exists with that name <strong>minus the leading underscore</strong>. For example, if the filter returns <code>_venue</code>, check if a <code>venue</code> post type exists.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( 'If such a post type exists <strong>and</strong> it declares <code>post_type_supports( \'venue\', \'gatherpress-shadow-source\' )</code>, the taxonomy is confirmed as a shadow taxonomy. GatherPress also provides a canonical check: <code>GatherPress\\Core\\Shadow_Source::get_instance()-&gt;is_shadow_term_slug( $slug )</code> returns <code>true</code> when the slug starts with <code>_</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( 'Confirmed shadow taxonomies are stored in a separate internal list (e.g., a class property <code>$shadow_taxonomies</code>) for reuse by the admin UI, frontend resolution, and editor resolution methods &mdash; avoiding repeated detection logic.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ol>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why no separate detection step?', 'gatherpress-taxonomy-colors' ) . '</strong> '
					. __( 'Scanning all registered post types for <code>gatherpress-shadow-source</code> support would be an implicit, magical side-effect. By relying on the filter as the explicit entry point, site developers retain full control over which taxonomies &mdash; shadow or regular &mdash; participate in the color system. A GatherPress integration snippet simply adds <code>_venue</code> or <code>_topic</code> to the filter; the system handles the rest.', 'gatherpress-taxonomy-colors' ) . '</div>'

					. '<p><strong>' . __( 'The Challenge: Inverting the Admin Surface', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'For regular taxonomies (Layers 1&ndash;5), the color picker lives on the term edit screen. But shadow taxonomies have <code>show_ui = false</code> &mdash; there is no term edit screen. The architectural pivot:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ul>'
					. '<li>' . __( '<strong>Color picker on the post editor</strong>: Instead of hooking into <code>{taxonomy}_edit_form_fields</code>, add a metabox or a custom sidebar panel to the shadow-source post type\'s edit screen. The picker reads and writes to the shadow term\'s <code>term_color</code> and <code>term_color_secondary</code> meta &mdash; the same meta keys used by regular taxonomies. The shadow term for the current post is resolved via GatherPress\'s helper: <code>GatherPress\\Core\\Shadow_Source::get_instance()-&gt;term_slug_from_post_name( $post-&gt;post_name )</code>, which returns the term slug that mirrors the post.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Admin columns on the post list</strong>: Since there is no taxonomy list table (no UI), the color swatch column is added to the <strong>post type\'s list table</strong> instead. Hook into <code>manage_{post_type}_posts_columns</code> and <code>manage_{post_type}_posts_custom_column</code> to display the primary and secondary color circles for each post\'s shadow term.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'

					. '<p><strong>' . __( 'Frontend Resolution: Two Paths', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'The frontend resolution in <code>resolve_contextual_term_colors()</code> and <code>resolve_term_colors_for_post()</code> needs two distinct code paths, determined by whether the current context involves a shadow taxonomy:', 'gatherpress-taxonomy-colors' ) . '</p>'
					. '<ol>'
					. '<li>' . __( '<strong>Context is a post that supports <code>gatherpress-shadow-source</code></strong> (i.e., the post IS a shadow source &mdash; e.g., rendering a venue): Use GatherPress\'s helper to map the currently rendered post back to its shadow term: <code>GatherPress\\Core\\Shadow_Source::get_instance()-&gt;term_slug_from_post_name( $post_name )</code>. This returns the term slug. Then use <code>get_term_by( \'slug\', $term_slug, $shadow_taxonomy )</code> to get the term object and read its <code>term_color</code> / <code>term_color_secondary</code> meta.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Context is any other post</strong> (a regular post, or a consumer post like an event): Use the existing <code>get_the_terms()</code> path from Layers 3 and 5. This handles both regular taxonomies and the consumer side of shadow taxonomies (where an event is tagged with a shadow term like <code>_venue:madison-square-garden</code>).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ol>'
					. '<div class="taxonomy-color-roadmap__note"><strong>' . __( 'Why two paths instead of always using get_the_terms()?', 'gatherpress-taxonomy-colors' ) . '</strong> '
					. __( 'Shadow taxonomies maintain a 1:1 relationship between post and term. On a <strong>consumer</strong> post (event), <code>get_the_terms( $event_id, \'_venue\' )</code> correctly returns the shadow terms it\'s tagged with. But on the <strong>source</strong> post (venue) itself, the relationship is inverted: the post IS the term. <code>get_the_terms( $venue_id, \'_venue\' )</code> would return nothing &mdash; the venue isn\'t tagged with itself. GatherPress\'s <code>term_slug_from_post_name()</code> bridges this inversion by deriving the shadow term slug directly from the post\'s slug, ensuring the correct term is always found regardless of which side of the relationship you\'re on.', 'gatherpress-taxonomy-colors' ) . '</div>'

					. '<p>' . __( 'In the Layer 5 <code>scope_term_colors_to_post_template()</code> method, the same branching applies per post in the Query Loop: check if the post\'s post type is in the <code>$shadow_taxonomies</code> list (by checking its corresponding post type), and if so, use the GatherPress helper. Otherwise, use <code>get_the_terms()</code> as before.', 'gatherpress-taxonomy-colors' ) . '</p>'

					. '<p><strong>' . __( 'Design Token Registration', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<p>' . __( 'Shadow taxonomies participate in the same <code>get_term_color_slots()</code> pipeline as regular taxonomies. Once the hidden taxonomy slug is present in the <code>gptc_term_color_taxonomies</code> filter return, Layers 2&ndash;5 automatically generate palette entries, CSS overrides, and scoped properties for the shadow taxonomy &mdash; e.g., <code>--flavor--venue-primary</code>, <code>--wp--preset--color--venue-primary</code>. No changes to Layers 2&ndash;5 are needed; only the taxonomy registration logic, admin UI, and resolution helper are new.', 'gatherpress-taxonomy-colors' ) . '</p>'

					. '<p><strong>' . __( 'Architecture Summary', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul>'
					. '<li>' . __( '<strong>Single source of truth</strong>: The <code>gptc_term_color_taxonomies</code> filter. Shadow taxonomies are detected by checking underscore-prefixed slugs against registered post types with <code>gatherpress-shadow-source</code> support. Validated post type slugs are cached in <code>$shadow_source_post_types</code> for reuse across admin column registration, sidebar panel decisions, and resolution branching.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Data layer</strong>: Same <code>term_color</code> / <code>term_color_secondary</code> meta on the shadow term. Unchanged.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Admin UI</strong>: Moved from taxonomy screens to post edit screens for shadow-source post types. Reads/writes shadow term meta transparently.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Design tokens</strong>: Automatic &mdash; shadow taxonomy joins the filterable taxonomy list and gets its own slot pair.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Frontend resolution</strong>: Two paths &mdash; shadow-source posts use <code>term_slug_from_post_name()</code>; all other posts use <code>get_the_terms()</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '<strong>Editor resolution</strong>: Same Layer 4 filter &mdash; <code>resolve_term_colors_for_post()</code> extended with the two-path branching for shadow taxonomies.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'

					. '<p><strong>' . __( 'Implementation Checklist', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#9989; <strong>Shadow taxonomy identification</strong>: In <code>Shadow_Taxonomy_Support::detect_shadow_taxonomies()</code>, iterates the filter return. For each slug starting with <code>_</code>, derives the candidate post type slug (<code>ltrim( $slug, \'_\' )</code>) and checks <code>post_type_exists()</code> and <code>post_type_supports( ..., \'gatherpress-shadow-source\' )</code>. Cross-checks with <code>GatherPress\\Core\\Shadow_Source::get_instance()-&gt;is_shadow_term_slug()</code> when available.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Cached shadow-source post type list</strong>: Confirmed post type slugs stored in <code>$shadow_source_post_types</code> class property (map of post type slug &rarr; taxonomy slug). Drives admin column registration, metabox decisions, and resolution branching.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Shadow term resolver</strong>: <code>resolve_shadow_term()</code> wraps <code>GatherPress\\Core\\Shadow_Source::get_instance()-&gt;term_slug_from_post_name()</code> with <code>class_exists()</code> and <code>method_exists()</code> guards. Returns <code>null</code> if GatherPress is not active.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Post editor metabox</strong>: Color pickers for primary and secondary colors on shadow-source post types via <code>add_meta_box()</code>. Reads/writes shadow term meta via the resolver. Nonce verification and capability checks included.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Post list table columns</strong>: Color swatch column on shadow-source post type admin list screens driven by <code>$shadow_source_post_types</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Frontend resolution: shadow-source path</strong>: <code>Helpers::resolve_all_taxonomy_colors_for_post()</code> detects when the current post IS the shadow source and uses <code>resolve_shadow_term()</code> instead of <code>get_the_terms()</code>.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Frontend resolution: consumer path</strong>: Standard <code>get_the_terms()</code> path handles consumer-side shadow term assignments (e.g., an event tagged with a venue\'s shadow term).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Layer 5 scoped resolution</strong>: <code>scope_term_colors_to_post_template()</code> inherits shadow-aware resolution via <code>Helpers::resolve_all_taxonomy_colors_for_post()</code> &mdash; no separate branching needed.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Graceful degradation</strong>: All GatherPress calls guarded by <code>class_exists()</code> and <code>method_exists()</code>. Shadow taxonomy identification silently skips unconfirmed slugs.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#9989; <strong>Term color block style</strong>: <code>inject_post_terms_color_properties()</code> uses <code>get_the_terms()</code> which works for consumer-side shadow taxonomy assignments without modification.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>'

					. '<p><strong>' . __( 'Ideas & Future Enhancements', 'gatherpress-taxonomy-colors' ) . '</strong></p>'
					. '<ul class="taxonomy-color-roadmap__checklist">'
					. '<li>' . __( '&#11036; <strong>Block editor sidebar panel</strong>: A dedicated "Term Colors" panel in the post editor sidebar that shows all active shadow term colors with live preview swatches, regardless of which block is selected.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Bidirectional resolution</strong>: When viewing a consumer post (e.g., an event tagged with venue "Madison Square Garden"), resolve the venue\'s shadow term colors into <code>--flavor--venue-primary</code> &mdash; enabling event pages to inherit venue branding automatically.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Bulk color assignment</strong>: A bulk action on the post list table to assign a color to multiple shadow-source posts at once.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Color preview in post editor</strong>: Show the resolved color inline next to the post title or in the publish metabox, so editors see the visual identity at a glance without opening the color panel.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Generic shadow taxonomy detection</strong>: Beyond GatherPress, detect any post type with a <code>_&lt;post_type&gt;</code> hidden taxonomy pattern, making the system work with other plugins that implement shadow taxonomies (e.g., The Events Calendar, custom implementations).', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '<li>' . __( '&#11036; <strong>Auto-filter registration</strong>: Provide a convenience function or a secondary filter that automatically adds all <code>_&lt;post_type&gt;</code> shadow taxonomy slugs for detected <code>gatherpress-shadow-source</code> post types, so site developers don\'t need to manually add each one to the filter.', 'gatherpress-taxonomy-colors' ) . '</li>'
					. '</ul>',
				'code'       => '',
			);
		}
	}
}

echo Renderer::get_instance()->render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All output is escaped inside the render() method chain.
