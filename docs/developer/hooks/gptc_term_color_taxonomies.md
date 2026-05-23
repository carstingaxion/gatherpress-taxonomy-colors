# gptc_term_color_taxonomies


Filters the taxonomies that participate in the term color system.

This is the **single source of truth** for which taxonomies receive
color meta registration (Layer 1), design token slots (Layer 2),
frontend CSS custom property injection (Layer 3), editor palette
resolution (Layer 4), scoped per-post injection (Layer 5), and
shadow taxonomy detection (Layer 6).

Each entry should be a registered taxonomy slug. Adding a slug
automatically triggers:

- `register_term_meta()` for every color role defined by
`gptc_term_color_roles`.
- Palette entries in `theme.json` (one per role per taxonomy).
- `--flavor--{taxonomy}-{role}` CSS custom properties on the
frontend and in the editor.
- Color picker fields on the term edit screen.
- A "Colors" swatch column in the term list table.

**Shadow taxonomy convention:** Slugs prefixed with `_` (underscore)
are treated as shadow taxonomy candidates. The plugin strips the
leading underscore, checks whether a matching post type exists with
`gatherpress-shadow-source` support, and — if confirmed — moves the
admin UI to the post editor and uses GatherPress helpers for term
resolution. See Layer 6 documentation for details.


Default: `array( '_gatherpress_play', 'post_tag', 'category' )`.

## Example

```php
// Add a custom "genre" taxonomy to the color system.
add_filter( 'gptc_term_color_taxonomies', function ( array $taxonomies ): array {
    $taxonomies[] = 'genre';
    return $taxonomies;
} );
```

## Example

```php
// Enable only categories (remove tags and shadow taxonomies).
add_filter( 'gptc_term_color_taxonomies', function (): array {
    return array( 'category' );
} );
```

## Example

```php
// Add the GatherPress shadow taxonomy for the "venue" post type.
add_filter( 'gptc_term_color_taxonomies', function ( array $taxonomies ): array {
    $taxonomies[] = '_gatherpress_venue';
    return $taxonomies;
} );
```

## Parameters

- *`array<int,`* `string>` $taxonomies Array of taxonomy slugs.

## Files

- [plugin.php:2095](https://github.com/carstingaxion/gatherpress-taxonomy-colors/blob/main/plugin.php#L2095)
```php
apply_filters(
				'gptc_term_color_taxonomies',
				array( '_gatherpress_play', 'post_tag', 'category' )
			)
```



[← All Hooks](Hooks.md)
