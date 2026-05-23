<?php
/**
 * Demo data seeder for WordPress Playground blueprint.
 *
 * Creates demo categories and tags with colors assigned,
 * sample posts with terms, and a tutorial "Getting Started" post.
 *
 * @package GatherpressTaxonomyColors
 * @since   0.1.3
 */

require_once '/wordpress/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * 1. Seed categories with colors
 * ------------------------------------------------------------------ */

$demo_categories = array(
	array(
		'name'            => 'Technology',
		'slug'            => 'technology',
		'description'     => 'Posts about software, hardware, and digital innovation.',
		'primary_color'   => '#2563eb',
		'secondary_color' => '#93c5fd',
	),
	array(
		'name'            => 'Design',
		'slug'            => 'design',
		'description'     => 'Visual design, typography, and creative direction.',
		'primary_color'   => '#9333ea',
		'secondary_color' => '#c4b5fd',
	),
	array(
		'name'            => 'Community',
		'slug'            => 'community',
		'description'     => 'Events, meetups, and open-source collaboration.',
		'primary_color'   => '#059669',
		'secondary_color' => '#6ee7b7',
	),
	array(
		'name'            => 'Business',
		'slug'            => 'business',
		'description'     => 'Strategy, growth, and entrepreneurship.',
		'primary_color'   => '#d97706',
		'secondary_color' => '#fcd34d',
	),
);

$category_ids = array();

foreach ( $demo_categories as $cat ) {
	$existing = term_exists( $cat['slug'], 'category' );

	if ( $existing ) {
		$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
	} else {
		$result = wp_insert_term( $cat['name'], 'category', array(
			'slug'        => $cat['slug'],
			'description' => $cat['description'],
		) );

		if ( is_wp_error( $result ) ) {
			continue;
		}

		$term_id = (int) $result['term_id'];
	}

	update_term_meta( $term_id, 'term_color', sanitize_hex_color( $cat['primary_color'] ) );
	update_term_meta( $term_id, 'term_color_secondary', sanitize_hex_color( $cat['secondary_color'] ) );

	$category_ids[ $cat['slug'] ] = $term_id;
}

/* ------------------------------------------------------------------
 * 2. Seed tags with colors
 * ------------------------------------------------------------------ */

$demo_tags = array(
	array(
		'name'            => 'Breaking',
		'slug'            => 'breaking',
		'primary_color'   => '#dc2626',
		'secondary_color' => '#fca5a5',
	),
	array(
		'name'            => 'Tutorial',
		'slug'            => 'tutorial',
		'primary_color'   => '#0891b2',
		'secondary_color' => '#67e8f9',
	),
	array(
		'name'            => 'Opinion',
		'slug'            => 'opinion',
		'primary_color'   => '#7c3aed',
		'secondary_color' => '#c4b5fd',
	),
	array(
		'name'            => 'Release',
		'slug'            => 'release',
		'primary_color'   => '#16a34a',
		'secondary_color' => '#86efac',
	),
);

$tag_ids = array();

foreach ( $demo_tags as $tag ) {
	$existing = term_exists( $tag['slug'], 'post_tag' );

	if ( $existing ) {
		$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
	} else {
		$result = wp_insert_term( $tag['name'], 'post_tag', array(
			'slug' => $tag['slug'],
		) );

		if ( is_wp_error( $result ) ) {
			continue;
		}

		$term_id = (int) $result['term_id'];
	}

	update_term_meta( $term_id, 'term_color', sanitize_hex_color( $tag['primary_color'] ) );
	update_term_meta( $term_id, 'term_color_secondary', sanitize_hex_color( $tag['secondary_color'] ) );

	$tag_ids[ $tag['slug'] ] = $term_id;
}

/* ------------------------------------------------------------------
 * 3. Seed child category (to demonstrate color inheritance)
 * ------------------------------------------------------------------ */

$web_dev_existing = term_exists( 'web-development', 'category' );

if ( $web_dev_existing ) {
	$web_dev_id = is_array( $web_dev_existing ) ? (int) $web_dev_existing['term_id'] : (int) $web_dev_existing;
} else {
	$parent_id  = isset( $category_ids['technology'] ) ? $category_ids['technology'] : 0;
	$web_dev    = wp_insert_term( 'Web Development', 'category', array(
		'slug'        => 'web-development',
		'description' => 'A subcategory of Technology — inherits the parent color when no color is set.',
		'parent'      => $parent_id,
	) );

	$web_dev_id = is_wp_error( $web_dev ) ? 0 : (int) $web_dev['term_id'];
}

// Deliberately no color set — demonstrates Layer 5 color inheritance from "Technology".

/* ------------------------------------------------------------------
 * 4. Seed sample posts with terms assigned
 * ------------------------------------------------------------------ */

$demo_posts = array(
	array(
		'slug'       => 'building-accessible-block-themes',
		'title'      => 'Building Accessible Block Themes',
		'content'    => '<!-- wp:paragraph -->
<p>Accessibility is not an afterthought — it is a design constraint that makes everything better. When building block themes, semantic HTML, sufficient color contrast, and keyboard navigation should be considered from the first line of theme.json.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Why This Matters for Term Colors</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The GatherPress Taxonomy Colors plugin injects term colors as CSS custom properties. This means you can use <code>color-mix()</code> to derive accessible contrast ratios automatically — the scoped property resolves at computed-value time.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Try selecting this paragraph and changing its color to "Category Color (Primary)" in the block editor. You should see the blue from the Technology category.</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'technology' ),
		'tags'       => array( 'tutorial' ),
	),
	array(
		'slug'       => 'color-theory-for-wordpress-developers',
		'title'      => 'Color Theory for WordPress Developers',
		'content'    => '<!-- wp:paragraph -->
<p>Understanding color relationships helps you build more cohesive designs. With taxonomy colors as design tokens, each section of your site can carry the visual identity of its content category — automatically.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Primary and Secondary Pairs</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Each term can have a primary and secondary color. Use the primary for headings and accents, the secondary for backgrounds and borders. The plugin makes both available as separate design tokens in the editor palette.</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'design' ),
		'tags'       => array( 'tutorial' ),
	),
	array(
		'slug'       => 'wordpress-6-8-release-highlights',
		'title'      => 'WordPress 6.8 Release Highlights',
		'content'    => '<!-- wp:paragraph -->
<p>The latest WordPress release brings significant improvements to the block editor, including enhanced Query Loop patterns and better theme.json support. These enhancements pair well with taxonomy color design tokens.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Notice how this post resolves both a category color (Technology → blue) and a tag color (Release → green) independently. Each taxonomy gets its own design token slot.</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'technology' ),
		'tags'       => array( 'release', 'breaking' ),
	),
	array(
		'slug'       => 'organizing-your-first-wordpress-meetup',
		'title'      => 'Organizing Your First WordPress Meetup',
		'content'    => '<!-- wp:paragraph -->
<p>Community events are the heartbeat of the WordPress ecosystem. Whether you are planning a casual coffee chat or a full-day workshop, the key is starting small and being consistent.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>This post is categorized under "Community" — its green color cascades through any block that uses the "Category Color (Primary)" design token.</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'community' ),
		'tags'       => array( 'opinion' ),
	),
	array(
		'slug'       => 'scaling-a-plugin-business',
		'title'      => 'Scaling a Plugin Business in 2025',
		'content'    => '<!-- wp:paragraph -->
<p>The WordPress plugin ecosystem continues to grow. Sustainability comes from solving real problems, maintaining backwards compatibility, and investing in documentation as much as code.</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'business' ),
		'tags'       => array( 'opinion' ),
	),
	array(
		'slug'       => 'modern-css-in-wordpress-themes',
		'title'      => 'Modern CSS in WordPress Themes',
		'content'    => '<!-- wp:paragraph -->
<p>CSS custom properties, <code>color-mix()</code>, container queries, and cascade layers are reshaping how we style WordPress themes. The taxonomy color system leverages custom properties at its core — making term colors composable with modern CSS techniques.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>This post belongs to the "Web Development" subcategory, which has no color assigned. Thanks to color inheritance (Layer 5), it inherits the blue from its parent category "Technology".</p>
<!-- /wp:paragraph -->',
		'categories' => array( 'web-development' ),
		'tags'       => array( 'tutorial' ),
	),
);

foreach ( $demo_posts as $post_data ) {
	$existing = get_page_by_path( $post_data['slug'], OBJECT, 'post' );

	if ( $existing ) {
		continue;
	}

	$cat_ids = array();
	foreach ( $post_data['categories'] as $cat_slug ) {
		if ( isset( $category_ids[ $cat_slug ] ) ) {
			$cat_ids[] = $category_ids[ $cat_slug ];
		} elseif ( 'web-development' === $cat_slug && $web_dev_id ) {
			$cat_ids[] = $web_dev_id;
		}
	}

	$post_tag_ids = array();
	foreach ( $post_data['tags'] as $tag_slug ) {
		if ( isset( $tag_ids[ $tag_slug ] ) ) {
			$post_tag_ids[] = $tag_ids[ $tag_slug ];
		}
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $post_data['title'],
		'post_name'    => $post_data['slug'],
		'post_content' => $post_data['content'],
		'post_category' => $cat_ids,
	) );

	if ( $post_id && ! is_wp_error( $post_id ) && ! empty( $post_tag_ids ) ) {
		wp_set_post_tags( $post_id, $post_tag_ids );
	}
}

/* ------------------------------------------------------------------
 * 5. Seed the tutorial "Getting Started" post (landing page)
 * ------------------------------------------------------------------ */

$tutorial_slug    = 'getting-started-with-taxonomy-colors';
$tutorial_existing = get_page_by_path( $tutorial_slug, OBJECT, 'post' );

if ( ! $tutorial_existing ) {
	$tutorial_content = '<!-- wp:heading -->
<h2 class="wp-block-heading">Welcome to GatherPress Taxonomy Colors</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This plugin turns your taxonomy term colors into native WordPress design tokens. Every color you assign to a category or tag becomes available in the block editor\'s color picker — and resolves automatically based on the post you\'re editing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Try It Right Now</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Step 1:</strong> Look at the sidebar on the right. This post is filed under the <em>Technology</em> category (blue) and tagged <em>Tutorial</em> (teal).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Step 2:</strong> Select this paragraph, then open <strong>Color → Text</strong> in the block settings. Scroll to the bottom of the palette — you\'ll see entries like <strong>"Category Color (Primary)"</strong> and <strong>"Tag Color (Primary)"</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Step 3:</strong> Pick "Category Color (Primary)". The text turns blue — the color of the Technology category. Now switch this post\'s category to "Design" (in the sidebar) and watch the color update to purple.</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">How It Works</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The plugin registers abstract color slots in <code>theme.json</code> — one pair (primary + secondary) per taxonomy. On the frontend, CSS custom properties like <code>--flavor--category-primary</code> are injected based on the current post\'s terms. In the editor, those same properties resolve live as you edit.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>In a Query Loop, each post gets its own scoped colors — so a grid of posts from different categories shows each card in the correct color without any extra configuration.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Explore the Demo</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>Go to <strong>Posts → Categories</strong> to see the color swatches on each category.</li>
<li>Edit any of the sample posts to see how colors resolve differently per post.</li>
<li>Try the <strong>Query Loop</strong> block on a page — each post renders with its own term colors.</li>
<li>Check <strong>Posts → Tags</strong> to see tag colors (red for Breaking, teal for Tutorial, etc.).</li>
<li>The "Web Development" subcategory has no color — it inherits blue from its parent "Technology".</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">For Developers</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Add custom taxonomies to the color system with a single filter:</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>add_filter( \'gptc_term_color_taxonomies\', function ( $taxonomies ) {
    $taxonomies[] = \'genre\';
    return $taxonomies;
} );</code></pre>
<!-- /wp:code -->

<!-- wp:paragraph -->
<p>That\'s it — the plugin auto-generates palette entries, CSS overrides, and contextual resolution for any taxonomy you add.</p>
<!-- /wp:paragraph -->';

	$tutorial_id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Getting Started with Taxonomy Colors',
		'post_name'    => $tutorial_slug,
		'post_content' => $tutorial_content,
		'post_category' => isset( $category_ids['technology'] ) ? array( $category_ids['technology'] ) : array(),
	) );

	if ( $tutorial_id && ! is_wp_error( $tutorial_id ) && isset( $tag_ids['tutorial'] ) ) {
		wp_set_post_tags( $tutorial_id, array( $tag_ids['tutorial'] ) );
	}
}

/* ------------------------------------------------------------------
 * 6. Update the blueprint landing page URL dynamically
 * ------------------------------------------------------------------ */

$tutorial_post = get_page_by_path( $tutorial_slug, OBJECT, 'post' );

if ( $tutorial_post ) {
	// Write the landing URL for the blueprint to pick up.
	$landing_url = '/wp-admin/post.php?post=' . $tutorial_post->ID . '&action=edit';

	// Update the option so the blueprint step can redirect.
	update_option( 'gptc_demo_tutorial_post_id', $tutorial_post->ID, false );
}

$tutorial = get_page_by_path( 'getting-started-with-taxonomy-colors', OBJECT, 'post' );
if ( $tutorial ) {
	$url = '/wp-admin/post.php?post=' . $tutorial->ID . '&action=edit';
	file_put_contents( '/wordpress/blueprint-landing.txt', $url );
}
