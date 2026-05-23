# Taxonomy Color Roadmap

**Design Tokens via theme.json + Dynamic CSS Custom Properties**

---

## The Core Problem

You want:

1. An editor assigns a color to each term (e.g., category "Technology" → blue).
2. In the block/site editor, that color appears in the native color picker alongside theme palette colors.
3. The color is contextual — it resolves based on which term is relevant to the current post/template.
4. In the editor without context, the picker shows "Term Color" as an abstract slot (like a design token). With context (e.g., editing a post tagged "Technology"), it resolves to the actual hex value.
5. On the frontend, it always resolves correctly.

---

## The Architecture

The solution that feels closest to core is to treat term colors not as literal colors, but as **semantic design tokens** — exactly how `theme.json` presets already work.

This mirrors how WordPress already handles colors. The editor sees "Term Primary" in the color picker. The actual value is injected dynamically.

### Conceptual Model

```
theme.json palette (per taxonomy):
  "category-primary"   ->  var(--wp--preset--color--category-primary)
  "category-secondary" ->  var(--wp--preset--color--category-secondary)
  "post_tag-primary"   ->  var(--wp--preset--color--post_tag-primary)
  "post_tag-secondary" ->  var(--wp--preset--color--post_tag-secondary)

Term meta:
  Category "Technology"  ->  #2563eb (primary), #93c5fd (secondary)
  Tag "Breaking"         ->  #dc2626 (primary)

Frontend/Editor resolution (on a post in "Technology" + "Breaking"):
  --flavor--category-primary:   #2563eb;
  --flavor--category-secondary:  #93c5fd;
  --flavor--post_tag-primary:    #dc2626;
```

---

## Extended Scope: Shadow Taxonomies

Some architectures use a **shadow taxonomy** pattern: a hidden taxonomy where each published post of a specific type is mirrored by a term. Consumers (events, sessions, etc.) tag themselves with that term to model relationships. This extends the color system to work with these post-type-as-taxonomy structures — moving the admin UI to the post editor and using specialised helpers for term resolution.

---

## Layer 1 – Term Meta for Color Storage

*The data layer. Each term gets a primary and secondary color stored as term meta.*

This is the data layer. Each term gets a color stored as term meta. WordPress has supported term meta since version 4.4 via `add_term_meta()`, `get_term_meta()`, and `update_term_meta()`. This is the canonical, core-blessed way to store additional data on taxonomy terms.

For each taxonomy you want to color-code, register two meta keys — `term_color` (primary) and `term_color_secondary` — using `register_term_meta()`. This function accepts a schema definition and, critically, a `show_in_rest` flag that exposes the value to the block editor via the REST API.

> **Why register_term_meta with show_in_rest?** This is the same pattern core uses for post meta. The REST visibility is essential for the editor integration — it allows the block editor to read term colors without custom endpoints.

### Implementation Checklist

- ✅ `register_term_meta()` for both `term_color` and `term_color_secondary` with `show_in_rest`, `sanitize_hex_color` callback, and `single => true` for all configurable taxonomies.
- ✅ Filterable taxonomy list via `gptc_term_color_taxonomies` filter (defaults to `category` and `post_tag`).
- ✅ Primary and secondary color picker fields on both the "Add New Term" and "Edit Term" admin forms.
- ✅ Save handlers for term creation and edits with nonce verification and capability checks.
- ✅ Color swatch column in the taxonomy list table showing both primary and secondary colors.
- ✅ `wp-color-picker` assets enqueued only on relevant admin screens.
- ✅ Layer 2: Per-taxonomy abstract design token slots in `theme.json`.
- ✅ Layer 3: Per-taxonomy frontend CSS custom property injection.
- ✅ Layer 4: Per-taxonomy editor integration via `wp_theme_json_data_theme` and editor CSS injection.
- ✅ Layer 5: Scoped per-post resolution for Query Loop and archive contexts — injected onto existing elements, no wrapper divs.
- ✅ Layer 6: Shadow taxonomy support for post types with `gatherpress-shadow-source`.

---

## Layer 2 – The Design Token in theme.json — Per Taxonomy

*Define abstract color slot pairs per taxonomy in theme.json that reference CSS custom properties, not literal hex values.*

Here is where the architecture becomes interesting. For each color-enabled taxonomy, you define a pair of abstract color slots in `theme.json`. The palette entries use **literal fallback hex values** — not `var()` references — because WordPress core's `wp_get_global_stylesheet()` sanitizes palette `color` fields and strips CSS function references. Slots are generated dynamically from the taxonomy list, e.g.:

- `category-primary` → `#8b7e74` (fallback hex) — labelled "Category Color (Primary)"
- `category-secondary` → `#b8aea6` (fallback hex) — labelled "Category Color (Secondary)"
- `post_tag-primary` → `#6e7f8d` (fallback hex) — labelled "Tag Color (Primary)"
- `post_tag-secondary` → `#a3b1bc` (fallback hex) — labelled "Tag Color (Secondary)"

The `var()` indirection is then restored via a separate CSS override that re-declares each `--wp--preset--color--{slug}` property:

- `--wp--preset--color--category-primary: var(--flavor--category-primary, #8b7e74);`
- `--wp--preset--color--post_tag-primary: var(--flavor--post_tag-primary, #6e7f8d);`

The resolution chain per slot is: `--wp--preset--color--category-primary` (CSS override) → `var(--flavor--category-primary, #8b7e74)` → resolved by the injected custom property or the fallback.

> **Why literal hex in theme.json + CSS override?** WordPress core's `wp_get_global_stylesheet()` processes palette entries through a sanitizer that expects literal color values (hex, rgb, hsl). Passing `var(--flavor--..., #888888)` as the palette `color` results in WordPress stripping the `var()` and emitting only the fallback. The architectural pivot: register the fallback hex in theme.json (satisfying the sanitizer and making swatches visible), then override the generated `--wp--preset--color--*` CSS custom property with a `var()` reference in a later stylesheet. CSS custom properties on `:root` in a later source order always win — same specificity, later declaration takes precedence.

> **Why per-taxonomy slots?** A post may belong to multiple taxonomies simultaneously. Per-taxonomy slots let a post resolve `category-primary: #2563eb` AND `post_tag-primary: #dc2626` independently — enabling multi-taxonomy color schemes without slot collisions.

### Why Two Layers of Custom Properties?

You might ask: why not inject `--wp--preset--color--category-primary` directly? Because WordPress generates that property from `theme.json`. You would be fighting the system. Instead, you define an intermediate custom property (`--flavor--category-primary`) and use a CSS override to make the preset reference it. You control the intermediate property; WordPress controls its own preset generation. Clean separation.

### Implementation Checklist

- ✅ `get_term_color_slots()` dynamically generates slot pairs from `get_color_taxonomies()`, using each taxonomy's registered label for human-readable names.
- ✅ Each slot carries `taxonomy` and `meta_key` fields for resolution traceability.
- ✅ Filterable slot definitions via `gptc_term_color_slots` filter — themes/plugins can add tertiary slots, change fallbacks, or remove a taxonomy's slots entirely.
- ✅ `wp_theme_json_data_theme` filter merges all slots into the theme-origin palette via `update_with()` using literal fallback hex values.
- ✅ Separate CSS override (`inject_preset_custom_property_overrides()`) re-declares each `--wp--preset--color--{slug}` with `var(--flavor--{slug}, {fallback})` on both frontend and editor.
- ✅ Architectural pivot: works around WordPress core's palette sanitizer stripping `var()` from theme.json color values.
- ✅ Slots sit at theme priority — overridable by user Global Styles, takes precedence over core defaults.
- ✅ Adding a new taxonomy to the filter automatically generates new palette entries — zero additional code needed.

---

## Layer 3 – Dynamic Resolution — Per-Taxonomy CSS Custom Property Injection

*Inject per-taxonomy color values on the frontend based on post/archive context.*

This is the critical piece. Each taxonomy resolves its own slot pair independently based on context. On the frontend, you know which post is being rendered, so you know its terms across all taxonomies.

The approach uses `wp_add_inline_style()` hooked to `wp_enqueue_scripts` to inject CSS custom properties onto `:root`. The `resolve_contextual_term_colors()` method iterates over each color-enabled taxonomy and resolves independently:

- **Singular posts**: For each taxonomy, finds the first term with a primary color. That term's colors populate `{taxonomy}-primary` and `{taxonomy}-secondary`.
- **Archive pages**: The queried term's colors populate the slots for its own taxonomy only.

> **Example resolution:** A post in category "Technology" (#2563eb) and tagged "Breaking" (#dc2626) produces: `--flavor--category-primary: #2563eb; --flavor--post_tag-primary: #dc2626;`. Every block using "Category Color (Primary)" renders blue, while "Tag Color (Primary)" renders red — independently.

### Implementation Checklist

- ✅ `resolve_contextual_term_colors()` iterates each taxonomy independently, producing `{taxonomy}-primary` and `{taxonomy}-secondary` slot values.
- ✅ Singular post support: first term with a primary color wins per taxonomy.
- ✅ Taxonomy archive support: queried term resolves its own taxonomy's slots.
- ✅ `inject_frontend_term_color_properties()` outputs all resolved `--flavor--{taxonomy}-{role}` custom properties on `:root`.
- ✅ Each `--flavor--{slot}` property overrides the fallback from the Layer 2 palette entry.
- ✅ All values pass through `sanitize_hex_color()` and `sanitize_key()` for defense-in-depth.

---

## Layer 4 – Editor Integration — Per-Taxonomy Resolution

*Make per-taxonomy term colors resolve contextually inside the block editor.*

The frontend is straightforward. The editor is where it gets nuanced. When an editor picks "Category Color (Primary)" for a heading, the color picker needs to show something. There are three scenarios:

1. **Editing a specific post** — context exists, all taxonomy slot pairs can resolve from the post's terms.
2. **Editing a template** — no specific post context; fallback colors from Layer 2 are shown.
3. **Editing a template part / pattern** — same as templates.

### Approach: wp_theme_json_data_theme Filter

The Layer 4 filter runs at priority 20 (after Layer 2 at priority 10) and replaces abstract `var(--flavor--...)` palette entries with concrete hex values when a post context is available. Each taxonomy's slots are resolved independently from the post's terms via `resolve_term_colors_for_post()`.

> **Editor-Side CSS Custom Properties:** The `enqueue_block_editor_assets` hook injects `--flavor--{taxonomy}-primary` / `--flavor--{taxonomy}-secondary` as inline CSS on `body` inside the editor iframe. This means: when editing a post in category "Technology" and tagged "Breaking", the editor sees the correct colors for BOTH taxonomy slot pairs simultaneously.

`update_with()` merges palette entries by slug. Each per-taxonomy slot has a unique slug (`category-primary`, `post_tag-primary`, etc.), so there are no collisions. If no post context is available, the filter returns unchanged — all abstract fallbacks remain in effect.

### Implementation Checklist

- ✅ `resolve_term_colors_for_post( int $post_id )` resolves each taxonomy independently into `{taxonomy}-primary` / `{taxonomy}-secondary` slots.
- ✅ `inject_editor_term_color_tokens()` at priority 20 replaces abstract palette entries with resolved hex values per taxonomy.
- ✅ Post editor: all taxonomy slot pairs resolve from the edited post's terms.
- ✅ Site editor / templates: no post context → all abstract fallbacks remain.
- ✅ `inject_editor_term_color_styles()` injects all resolved `--flavor--{taxonomy}-{role}` properties on `body` via `wp_add_inline_style( 'wp-edit-blocks', ... )`.
- ✅ CSS custom properties scoped to `body` for correct cascade inside the editor iframe.
- ✅ Both hooks guard against missing post context — graceful fallback.
- ✅ All values sanitized via `sanitize_hex_color()`, `sanitize_key()`, and `esc_attr()`.

---

## Layer 5 – Query Loop & Multi-Post Scoped Resolution

*Scope term color properties per post when multiple posts render on the same page — without wrapper divs.*

Layers 3 and 4 inject `--flavor--{taxonomy}-{role}` as global properties on `:root` (frontend) or `body` (editor). This is perfect when a single context dominates the page: a singular post, or a taxonomy archive where one term defines the colour. But what happens on an archive page or a Query Loop block where **multiple posts** render side-by-side, each with different term assignments?

The answer: **the global custom property holds one value, so the last post to set it wins**. Every post on the page would display the same color — the one from the last-resolved post. This is the main architectural limitation of the global approach.

### The Solution: Scoped Custom Properties Without Wrapper Divs

Instead of injecting on `:root`, scope the `--flavor--` properties to each post in the loop. The key architectural decision: **inject directly onto the existing root HTML element** of the first block rendered for each post, rather than wrapping in an extra `<div>`. This preserves grid/flex layouts and avoids non-semantic markup.

The hook is `render_block`, which receives the block content, parsed block, and the `WP_Block` instance. When `$instance->context['postId']` is set, we know we are inside a `core/post-template` iteration:

- **Step 1**: Detect `core/post-template` context via `$instance->context['postId']`.
- **Step 2**: Use a static tracker to ensure only the **first** block per post gets the injection — subsequent blocks in the same loop iteration inherit via CSS cascade.
- **Step 3**: Call `resolve_term_colors_for_post( $post_id )` to get the per-taxonomy slot → hex map.
- **Step 4**: Parse the first HTML opening tag of the block content. If it already has a `style` attribute, prepend our `--flavor--` declarations. If not, add a new `style` attribute. No wrapper div.

> **Why inject on the existing element instead of wrapping?** Adding a `<div>` wrapper breaks CSS Grid and Flexbox layouts inside Query Loop blocks. A post grid using `display: grid` on the post-template container expects direct children to be the post elements — an extra wrapper becomes an unwanted grid item. Injecting onto the existing element is layout-transparent.

### Global vs. Scoped: When to Use Which

- **Global (`:root`)**: Singular posts, taxonomy archives, anywhere a single context dominates. Simpler, no modifications to block output.
- **Scoped (inline on first block)**: Query Loop blocks, archive templates with post grids, any template where multiple posts render. Required for correct per-post color resolution.

Both coexist naturally. The global injection (Layer 3) sets the "page-level" context. The scoped injection overrides it locally per post. CSS custom property inheritance means the scoped value takes precedence inside the element, while the global value remains available outside it.

> **Cascade consideration:** Blocks using `color-mix(in srgb, var(--flavor--category-primary) 12%, transparent)` or similar derived values automatically pick up the scoped property — `color-mix()` resolves at computed-value time, not at parse time. No extra work needed for derived styles.

### Implementation Checklist

- ✅ Block-specific `render_block_core/post-template` filter — fires only once for the entire post-template output, not per inner block.
- ✅ Uses `WP_HTML_Tag_Processor` to iterate over each `<li class="wp-block-post">` in a single pass.
- ✅ Extracts the post ID from the `post-{id}` CSS class (added by WordPress core's `post_class()`).
- ✅ Resolves per-post term colors using existing `resolve_term_colors_for_post()`.
- ✅ Injects `--flavor--` custom properties directly onto each `<li>` element via `WP_HTML_Tag_Processor` — no wrapper div, no regex, no static tracker.
- ✅ The `<li>` is the natural scoping container — all inner blocks inherit custom properties via CSS cascade.
- ✅ Global (Layer 3) and scoped (Layer 5) injection coexist — scoped overrides global within the element subtree.
- ✅ `color-mix()` and other derived values resolve correctly in scoped context.

### Ideas & Future Enhancements

- ⬜ **Editor Query Loop preview**: Extend Layer 4 to detect Query Loop blocks in the site editor and inject scoped styles per preview post.
- ⬜ **REST API endpoint**: Expose a `/wp-json/gptc/v1/term-colors/{post_id}` endpoint so JavaScript-driven UIs (e.g., AJAX pagination, Infinite Scroll) can fetch resolved colors per post without a full page reload.
- ⬜ **Tertiary / accent slots**: Allow more than two color roles per taxonomy via the `gptc_term_color_slots` filter — e.g., a "tertiary" or "accent" slot for richer color schemes.
- ✅ **Color inheritance**: When a child term has no color, the system walks up the parent chain for hierarchical taxonomies (e.g., categories) until it finds a term with a color set. A "Web Development" subcategory with no color inherits from "Technology". Non-hierarchical taxonomies (tags) are unaffected. Depth-limited to 10 ancestor levels.
- ⬜ **Gutenberg sidebar panel**: A custom sidebar panel in the post editor showing which term colors are currently active, with quick links to edit the source terms.
- ⬜ **WooCommerce integration**: Extend `gptc_term_color_taxonomies` to include `product_cat` and `product_tag`, making product category colors available as design tokens in shop templates.
- ⬜ **Block Bindings API**: When WordPress stabilises the Block Bindings API, use it to bind block color attributes directly to term meta — eliminating the need for CSS custom property indirection entirely.
- ⬜ **Dark mode awareness**: Store a light and dark variant per term color, and switch based on the active theme variation or a `prefers-color-scheme` media query.

---

## Layer 6 – Shadow Taxonomy Support for Post Types

*Extend the color system to post types that use hidden "shadow taxonomies" — where each published post is mirrored by a term.*

Some WordPress architectures use a pattern called a **shadow taxonomy**: a hidden taxonomy (registered with `show_ui => false`) where one term is kept in lockstep with each published post of a specific post type. The term mirrors the post's slug and title. Consumers (events, sessions, productions, etc.) tag themselves with that term to model a relationship to the source post — effectively turning a post type into something that behaves like a taxonomy for querying and filtering purposes.

In the GatherPress ecosystem, this is signalled by a post type declaring support for `gatherpress-shadow-source`. When a post type has this support, a hidden `_<post_type>` taxonomy is automatically registered, and one term per published post is maintained by the shadow system.

### Taxonomy Registration: The Filter as Single Source of Truth

The `gptc_term_color_taxonomies` filter is the first and single source of truth for which taxonomies participate in the color system. Shadow taxonomy support is derived entirely from this filter's return value — there is no separate detection scan of registered post types.

The algorithm:

1. Read the filter return (an array of taxonomy slugs).
2. For each slug that starts with `_` (underscore), check whether a post type exists with that name **minus the leading underscore**. For example, if the filter returns `_venue`, check if a `venue` post type exists.
3. If such a post type exists **and** it declares `post_type_supports( 'venue', 'gatherpress-shadow-source' )`, the taxonomy is confirmed as a shadow taxonomy. GatherPress also provides a canonical check: `GatherPress\Core\Shadow_Source::get_instance()->is_shadow_term_slug( $slug )` returns `true` when the slug starts with `_`.
4. Confirmed shadow taxonomies are stored in a separate internal list (e.g., a class property `$shadow_taxonomies`) for reuse by the admin UI, frontend resolution, and editor resolution methods — avoiding repeated detection logic.

> **Why no separate detection step?** Scanning all registered post types for `gatherpress-shadow-source` support would be an implicit, magical side-effect. By relying on the filter as the explicit entry point, site developers retain full control over which taxonomies — shadow or regular — participate in the color system. A GatherPress integration snippet simply adds `_venue` or `_topic` to the filter; the system handles the rest.

### The Challenge: Inverting the Admin Surface

For regular taxonomies (Layers 1–5), the color picker lives on the term edit screen. But shadow taxonomies have `show_ui = false` — there is no term edit screen. The architectural pivot:

- **Color picker on the post editor**: Instead of hooking into `{taxonomy}_edit_form_fields`, add a metabox or a custom sidebar panel to the shadow-source post type's edit screen. The picker reads and writes to the shadow term's `term_color` and `term_color_secondary` meta — the same meta keys used by regular taxonomies. The shadow term for the current post is resolved via GatherPress's helper: `GatherPress\Core\Shadow_Source::get_instance()->term_slug_from_post_name( $post->post_name )`, which returns the term slug that mirrors the post.
- **Admin columns on the post list**: Since there is no taxonomy list table (no UI), the color swatch column is added to the **post type's list table** instead. Hook into `manage_{post_type}_posts_columns` and `manage_{post_type}_posts_custom_column` to display the primary and secondary color circles for each post's shadow term.

### Frontend Resolution: Two Paths

The frontend resolution in `resolve_contextual_term_colors()` and `resolve_term_colors_for_post()` needs two distinct code paths, determined by whether the current context involves a shadow taxonomy:

1. **Context is a post that supports `gatherpress-shadow-source`** (i.e., the post IS a shadow source — e.g., rendering a venue): Use GatherPress's helper to map the currently rendered post back to its shadow term: `GatherPress\Core\Shadow_Source::get_instance()->term_slug_from_post_name( $post_name )`. This returns the term slug. Then use `get_term_by( 'slug', $term_slug, $shadow_taxonomy )` to get the term object and read its `term_color` / `term_color_secondary` meta.
2. **Context is any other post** (a regular post, or a consumer post like an event): Use the existing `get_the_terms()` path from Layers 3 and 5. This handles both regular taxonomies and the consumer side of shadow taxonomies (where an event is tagged with a shadow term like `_venue:madison-square-garden`).

> **Why two paths instead of always using get_the_terms()?** Shadow taxonomies maintain a 1:1 relationship between post and term. On a **consumer** post (event), `get_the_terms( $event_id, '_venue' )` correctly returns the shadow terms it's tagged with. But on the **source** post (venue) itself, the relationship is inverted: the post IS the term. `get_the_terms( $venue_id, '_venue' )` would return nothing — the venue isn't tagged with itself. GatherPress's `term_slug_from_post_name()` bridges this inversion by deriving the shadow term slug directly from the post's slug, ensuring the correct term is always found regardless of which side of the relationship you're on.

In the Layer 5 `scope_term_colors_to_post_template()` method, the same branching applies per post in the Query Loop: check if the post's post type is in the `$shadow_taxonomies` list (by checking its corresponding post type), and if so, use the GatherPress helper. Otherwise, use `get_the_terms()` as before.

### Design Token Registration

Shadow taxonomies participate in the same `get_term_color_slots()` pipeline as regular taxonomies. Once the hidden taxonomy slug is present in the `gptc_term_color_taxonomies` filter return, Layers 2–5 automatically generate palette entries, CSS overrides, and scoped properties for the shadow taxonomy — e.g., `--flavor--venue-primary`, `--wp--preset--color--venue-primary`. No changes to Layers 2–5 are needed; only the taxonomy registration logic, admin UI, and resolution helper are new.

### Architecture Summary

- **Single source of truth**: The `gptc_term_color_taxonomies` filter. Shadow taxonomies are detected by checking underscore-prefixed slugs against registered post types with `gatherpress-shadow-source` support. Validated post type slugs are cached in `$shadow_source_post_types` for reuse across admin column registration, sidebar panel decisions, and resolution branching.
- **Data layer**: Same `term_color` / `term_color_secondary` meta on the shadow term. Unchanged.
- **Admin UI**: Moved from taxonomy screens to post edit screens for shadow-source post types. Reads/writes shadow term meta transparently.
- **Design tokens**: Automatic — shadow taxonomy joins the filterable taxonomy list and gets its own slot pair.
- **Frontend resolution**: Two paths — shadow-source posts use `term_slug_from_post_name()`; all other posts use `get_the_terms()`.
- **Editor resolution**: Same Layer 4 filter — `resolve_term_colors_for_post()` extended with the two-path branching for shadow taxonomies.

### Implementation Checklist

- ✅ **Shadow taxonomy identification**: In `Shadow_Taxonomy_Support::detect_shadow_taxonomies()`, iterates the filter return. For each slug starting with `_`, derives the candidate post type slug (`ltrim( $slug, '_' )`) and checks `post_type_exists()` and `post_type_supports( ..., 'gatherpress-shadow-source' )`. Cross-checks with `GatherPress\Core\Shadow_Source::get_instance()->is_shadow_term_slug()` when available.
- ✅ **Cached shadow-source post type list**: Confirmed post type slugs stored in `$shadow_source_post_types` class property (map of post type slug → taxonomy slug). Drives admin column registration, metabox decisions, and resolution branching.
- ✅ **Shadow term resolver**: `resolve_shadow_term()` wraps `GatherPress\Core\Shadow_Source::get_instance()->term_slug_from_post_name()` with `class_exists()` and `method_exists()` guards. Returns `null` if GatherPress is not active.
- ✅ **Post editor metabox**: Color pickers for primary and secondary colors on shadow-source post types via `add_meta_box()`. Reads/writes shadow term meta via the resolver. Nonce verification and capability checks included.
- ✅ **Post list table columns**: Color swatch column on shadow-source post type admin list screens driven by `$shadow_source_post_types`.
- ✅ **Frontend resolution: shadow-source path**: `Helpers::resolve_all_taxonomy_colors_for_post()` detects when the current post IS the shadow source and uses `resolve_shadow_term()` instead of `get_the_terms()`.
- ✅ **Frontend resolution: consumer path**: Standard `get_the_terms()` path handles consumer-side shadow term assignments (e.g., an event tagged with a venue's shadow term).
- ✅ **Layer 5 scoped resolution**: `scope_term_colors_to_post_template()` inherits shadow-aware resolution via `Helpers::resolve_all_taxonomy_colors_for_post()` — no separate branching needed.
- ✅ **Graceful degradation**: All GatherPress calls guarded by `class_exists()` and `method_exists()`. Shadow taxonomy identification silently skips unconfirmed slugs.
- ✅ **Term color block style**: `inject_post_terms_color_properties()` uses `get_the_terms()` which works for consumer-side shadow taxonomy assignments without modification.

### Ideas & Future Enhancements

- ⬜ **Block editor sidebar panel**: A dedicated "Term Colors" panel in the post editor sidebar that shows all active shadow term colors with live preview swatches, regardless of which block is selected.
- ⬜ **Bidirectional resolution**: When viewing a consumer post (e.g., an event tagged with venue "Madison Square Garden"), resolve the venue's shadow term colors into `--flavor--venue-primary` — enabling event pages to inherit venue branding automatically.
- ⬜ **Bulk color assignment**: A bulk action on the post list table to assign a color to multiple shadow-source posts at once.
- ⬜ **Color preview in post editor**: Show the resolved color inline next to the post title or in the publish metabox, so editors see the visual identity at a glance without opening the color panel.
- ⬜ **Generic shadow taxonomy detection**: Beyond GatherPress, detect any post type with a `_<post_type>` hidden taxonomy pattern, making the system work with other plugins that implement shadow taxonomies (e.g., The Events Calendar, custom implementations).
- ⬜ **Auto-filter registration**: Provide a convenience function or a secondary filter that automatically adds all `_<post_type>` shadow taxonomy slugs for detected `gatherpress-shadow-source` post types, so site developers don't need to manually add each one to the filter.

---

This architecture uses only public WordPress APIs. No core patches, no fragile hacks — just term meta, a theme.json filter with CSS custom property indirection, contextual resolution, scoped per-post properties for multi-post contexts, and shadow taxonomy awareness for post types acting as quasi-taxonomies. Term colors become first-class palette citizens that any block can consume.

**Built to feel like core. Designed to scale.**
