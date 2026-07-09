<?php
/**
 * Layer 5 — Query Loop Scoped Resolution.
 *
 * Injects scoped --flavor--* and --wp--preset--color--* CSS custom
 * properties onto each post item inside a Query Loop (core/post-template),
 * and per term-link inside a core/post-terms block using the "Term Colors"
 * block style.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherpressTaxonomyColors;

use GatherPress\Core;
use WP_Block;
use WP_HTML_Tag_Processor;
use WP_Term;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Singleton managing scoped per-post and per-term color injection.
 *
 * @since 0.1.0
 */
class Term_Color_Scoper {

	use Core\Traits\Singleton;


		/**
		 * Private constructor — registers render filters and block style.
		 *
		 * @since 0.1.0
		 */
	protected function __construct() {
		$this->setup_hooks();
	}

		/**
		 * Register hooks.
		 *
		 * @since 0.1.0
		 * @return void
		 */
	protected function setup_hooks(): void {
		add_filter( 'render_block_core/post-template', array( $this, 'scope_term_colors_to_post_template' ) );
		add_action( 'init', array( $this, 'register_post_terms_block_style' ) );
		add_filter( 'render_block_core/post-terms', array( $this, 'inject_post_terms_color_properties' ), 10, 3 );
	}

		/**
		 * Builds scoped inline style declarations for a set of resolved colors.
		 *
		 * @since  0.1.0
		 * @param  array<string, string>                                                                                                    $colors      Resolved slot slug to hex map.
		 * @param  array<string, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}> $slot_lookup Slot definitions keyed by slug.
		 * @return string Inline style declarations string.
		 */
	private function build_scoped_style_declarations( array $colors, array $slot_lookup ): string {
		$declarations = '';

		foreach ( $colors as $slot => $hex ) {
			$declarations .= sprintf(
				'--flavor--%s:%s;',
				esc_attr( $slot ),
				esc_attr( $hex )
			);

			if ( isset( $slot_lookup[ $slot ] ) ) {
				$declarations .= sprintf(
					'--wp--preset--color--%s:var(%s,%s);',
					esc_attr( sanitize_key( $slot ) ),
					$slot_lookup[ $slot ]['property'],
					esc_attr( $hex )
				);
			}
		}

		return $declarations;
	}

		/**
		 * Builds the slot lookup array keyed by slug from the token definitions.
		 *
		 * @since  0.1.0
		 * @return array<string, array{slug: string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}>
		 */
	private function get_slot_lookup(): array {
		$slots  = Term_Color_Tokens::get_instance()->get_term_color_slots();
		$lookup = array();

		foreach ( $slots as $slot_def ) {
			$lookup[ $slot_def['slug'] ] = $slot_def;
		}

		return $lookup;
	}

		/**
		 * Applies scoped style declarations to an HTML element via WP_HTML_Tag_Processor.
		 *
		 * @since  0.1.1
		 * @param  WP_HTML_Tag_Processor $processor          Tag processor positioned on the target element.
		 * @param  string                $style_declarations CSS custom property declarations to inject.
		 * @return void
		 */
	private function apply_scoped_styles( WP_HTML_Tag_Processor $processor, string $style_declarations ): void {
		$current_style = $processor->get_attribute( 'style' );
		$new_style     = $current_style
			? $style_declarations . $current_style
			: $style_declarations;

		$processor->set_attribute( 'style', $new_style );
	}

		/**
		 * Injects scoped term color properties per post inside post-template.
		 *
		 * @since  0.1.0
		 * @param  string $block_content Rendered post-template HTML.
		 * @return string Modified block content.
		 */
	public function scope_term_colors_to_post_template( string $block_content ): string {
		$trimmed = trim( $block_content );
		if ( '' === $trimmed ) {
			return $block_content;
		}

		$resolver    = Term_Color_Resolver::get_instance();
		$slot_lookup = $this->get_slot_lookup();
		$processor   = new WP_HTML_Tag_Processor( $block_content );

		while ( $processor->next_tag(
			array(
				'tag_name'   => 'LI',
				'class_name' => 'wp-block-post',
			)
		) ) {
			$class_attr = $processor->get_attribute( 'class' );

			if ( ! is_string( $class_attr ) ) {
				continue;
			}

			$matches = array();
			if ( ! preg_match( '/\bpost-(\d+)\b/', $class_attr, $matches ) ) {
				continue;
			}

			$post_id = (int) $matches[1];

			if ( $post_id <= 0 ) {
				continue;
			}

			$colors = $resolver->resolve_term_colors_for_post( $post_id );

			if ( empty( $colors ) ) {
				continue;
			}

			$this->apply_scoped_styles(
				$processor,
				$this->build_scoped_style_declarations( $colors, $slot_lookup )
			);
		}

		return $processor->get_updated_html();
	}

		/**
		 * Registers the "Term Colors" block style for core/post-terms.
		 *
		 * @since  0.1.0
		 * @return void
		 */
	public function register_post_terms_block_style(): void {
		register_block_style(
			'core/post-terms',
			array(
				'name'         => 'term-colors',
				'label'        => __( 'Term Colors', 'gatherpress-taxonomy-colors' ),
				'inline_style' => '
.wp-block-post-terms.is-style-term-colors {
	/* Per-term --flavor-- and --wp--preset--color-- properties are injected per <a> via render filter. */
}',
			)
		);
	}

		/**
		 * Injects per-term color properties onto each <a> inside
		 * core/post-terms blocks using the "Term Colors" style.
		 *
		 * @since  0.1.0
		 * @param  string               $block_content Rendered block HTML.
		 * @param  array<string, mixed> $block         Parsed block array.
		 * @param  WP_Block             $instance      Block instance.
		 * @return string Modified block content.
		 */
	public function inject_post_terms_color_properties( string $block_content, array $block, WP_Block $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$class_name = is_string( $attrs['className'] ?? null ) ? $attrs['className'] : '';

		if ( false === strpos( $class_name, 'is-style-term-colors' ) ) {
			return $block_content;
		}

		$taxonomy = is_string( $attrs['term'] ?? null ) ? $attrs['term'] : 'category';
		$post_id  = $this->resolve_post_id( $instance );

		if ( $post_id <= 0 ) {
			return $block_content;
		}

		$post_terms = get_the_terms( $post_id, $taxonomy );

		if ( ! is_array( $post_terms ) || empty( $post_terms ) ) {
			return $block_content;
		}

		$normalized_tax = Helpers::normalize_taxonomy_slug( $taxonomy );
		$term_color_map = $this->build_term_color_map( $post_terms );

		if ( empty( $term_color_map ) ) {
			return $block_content;
		}

		return $this->apply_term_colors_to_links(
			$block_content,
			$term_color_map,
			$normalized_tax
		);
	}

	/**
	 * Resolves the current post ID from block context or the global post.
	 *
	 * @since  0.3.0
	 * @param  WP_Block $instance Block instance.
	 * @return int Post ID, or 0 if not determinable.
	 */
	private function resolve_post_id( WP_Block $instance ): int {
		$raw = $instance->context['postId'] ?? get_the_ID();
		return is_int( $raw ) ? $raw : ( is_numeric( $raw ) ? (int) $raw : 0 );
	}

	/**
	 * Builds a URL-path-keyed map of role-slug => hex color
	 * for a set of terms, resolved from term meta.
	 *
	 * @since  0.3.0
	 * @param  WP_Term[] $terms Terms to resolve colors for.
	 * @return array<string, array<string, string>> Path-keyed map.
	 */
	private function build_term_color_map( array $terms ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$roles = Helpers::get_color_roles();
		$map   = array();

		foreach ( $terms as $term ) {
			$term_link = get_term_link( $term );

			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			$term_colors = $this->resolve_term_role_colors( $term, $roles );

			if ( empty( $term_colors ) ) {
				continue;
			}

			$parsed_path        = wp_parse_url( $term_link, PHP_URL_PATH );
			$normal_key         = is_string( $parsed_path ) ? untrailingslashit( $parsed_path ) : untrailingslashit( $term_link );
			$map[ $normal_key ] = $term_colors;
		}

		return $map;
	}

	/**
	 * Resolves hex colors for all roles of a single term.
	 *
	 * @since  0.3.0
	 * @param  WP_Term                                                                                                                     $term  Term to resolve.
	 * @param  array<int, array{slug: string, label: string, meta_key: string}>|list<array{slug: string, label: string, meta_key: string}> $roles Color role definitions.
	 * @return array<string, string> Role-slug => hex map (empty when no color set).
	 */
	private function resolve_term_role_colors( WP_Term $term, array $roles ): array {
		$colors = array();

		foreach ( $roles as $role ) {
			$raw = get_term_meta( $term->term_id, $role['meta_key'], true );
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$sanitized = sanitize_hex_color( $raw );
			if ( null !== $sanitized ) {
				$colors[ $role['slug'] ] = $sanitized;
			}
		}

		return $colors;
	}

	/**
	 * Walks all <a> tags in the block HTML and injects scoped color
	 * custom properties onto those whose href matches a term in the map.
	 *
	 * @since  0.3.0
	 * @param  string                               $block_content  Rendered block HTML.
	 * @param  array<string, array<string, string>> $term_color_map Path-keyed color map.
	 * @param  string                               $normalized_tax Normalized taxonomy slug.
	 * @return string Modified block HTML.
	 */
	private function apply_term_colors_to_links( string $block_content, array $term_color_map, string $normalized_tax ): string {
		$slot_lookup = $this->get_slot_lookup();
		$processor   = new WP_HTML_Tag_Processor( $block_content );

		while ( $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			$href = $processor->get_attribute( 'href' );

			if ( ! is_string( $href ) ) {
				continue;
			}

			$href_path  = wp_parse_url( $href, PHP_URL_PATH );
			$normal_key = is_string( $href_path ) ? untrailingslashit( $href_path ) : untrailingslashit( $href );

			if ( ! isset( $term_color_map[ $normal_key ] ) ) {
				continue;
			}

			$resolved = array();
			foreach ( $term_color_map[ $normal_key ] as $role_slug => $hex ) {
				$resolved[ $normalized_tax . '-' . $role_slug ] = $hex;
			}

			if ( ! empty( $resolved ) ) {
				$this->apply_scoped_styles(
					$processor,
					$this->build_scoped_style_declarations( $resolved, $slot_lookup )
				);
			}
		}

		return $processor->get_updated_html();
	}
}
