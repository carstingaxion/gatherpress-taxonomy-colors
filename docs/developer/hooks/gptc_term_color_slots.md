# gptc_term_color_slots


Filters the term color design token slot definitions.

Each slot represents one entry in the `theme.json` color palette
and one CSS custom property pair (`--wp--preset--color--{slug}` and
`--flavor--{slug}`). Slots are generated automatically from the
cartesian product of **color-enabled taxonomies**
(`gptc_term_color_taxonomies`) × **color roles**
(`gptc_term_color_roles`).

Use this filter to:

- **Add** extra slots beyond what the automatic generation provides
(e.g., a composite "brand" slot that merges multiple taxonomies).
- **Remove** specific slots for taxonomies that should not appear in
the editor palette.
- **Change fallback colors** to match your theme's neutral palette.
- **Rename** slot labels for editor UX clarity.

Each slot array contains:

- **`slug`** *(string)* — Unique identifier used as the `theme.json`
palette slug and in the CSS custom property name. Format:
`{normalized-taxonomy}-{role-slug}`, e.g. `category-primary`.
- **`name`** *(string)* — Human-readable label shown in the editor
color picker, e.g. "Category Color (Primary)".
- **`property`** *(string)* — The intermediate CSS custom property
name, e.g. `--flavor--category-primary`. This is the property
that contextual resolution (Layers 3–5) sets to the actual hex.
- **`fallback`** *(string)* — Hex color used as the `theme.json`
palette `color` value and as the `var()` fallback in the CSS
override. Shown when no term color resolves for the context.
- **`taxonomy`** *(string)* — The raw taxonomy slug this slot
belongs to. Used for resolution traceability.
- **`meta_key`** *(string)* — The term meta key that supplies the
actual color value, e.g. `term_color`.

## Example

```php
// Change the fallback color for all category slots to a custom neutral.
add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
    foreach ( $slots as &$slot ) {
        if ( 'category' === $slot['taxonomy'] ) {
            $slot['fallback'] = '#cccccc';
        }
    }
    return $slots;
} );
```

## Example

```php
// Remove all tag slots from the palette (keep only category slots).
add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
    return array_values( array_filter( $slots, function ( $slot ) {
        return 'post_tag' !== $slot['taxonomy'];
    } ) );
} );
```

## Example

```php
// Add a custom composite slot that doesn't map to a specific taxonomy.
add_filter( 'gptc_term_color_slots', function ( array $slots ): array {
    $slots[] = array(
        'slug'     => 'brand-highlight',
        'name'     => __( 'Brand Highlight', 'my-theme' ),
        'property' => '--flavor--brand-highlight',
        'fallback' => '#ff6600',
        'taxonomy' => '',
        'meta_key' => '',
    );
    return $slots;
} );
```

## Parameters

- *`array<int,`* `array{slug:` string, name: string, property: string, fallback: string, taxonomy: string, meta_key: string}> $slots
  - *`string`* `$slug` Palette slug / CSS identifier.
  - *`string`* `$name` Human-readable label for the editor.
  - *`string`* `$property` Intermediate CSS custom property name.
  - *`string`* `$fallback` Hex color fallback value.
  - *`string`* `$taxonomy` Raw taxonomy slug.
  - *`string`* `$meta_key` Term meta key for the color value.

## Files

- [plugin.php:1029](https://github.com/carstingaxion/gatherpress-taxonomy-colors/blob/main/plugin.php#L1029)
```php
apply_filters( 'gptc_term_color_slots', $slots )
```



[← All Hooks](Hooks.md)
