# gptc_term_color_roles


Filters the term color roles (or amount of colors).

This is the single source of truth for
which color slots are available per taxonomy term.

Each role is an associative array with three required keys:

- **`slug`** *(string)* — Unique identifier used in CSS custom property
names and design token slugs. Must be a valid CSS identifier fragment
(lowercase, hyphens allowed). Examples: `'primary'`, `'accent-dark'`.
- **`label`** *(string)* — Human-readable label shown in the admin color
picker fields, list table swatch tooltips, and the editor sidebar
panel. Translatable.
- **`meta_key`** *(string)* — The `term_meta` key used to store and
retrieve the hex color value. Must be unique across all roles.
Registered automatically via `register_term_meta()` with
`sanitize_hex_color` and `show_in_rest`.

The default roles are `primary` (`term_color`) and `secondary`
(`term_color_secondary`). Every layer in the architecture derives
its behavior from this filter:

- **Layer 1** registers one meta key per role per taxonomy.
- **Layer 2** generates one design token slot per role per taxonomy.
- **Layers 3–5** resolve and inject `--flavor--{taxonomy}-{role}` CSS
custom properties.
- **Admin UI** renders one color picker field per role on term edit
screens and one swatch per role in list table columns.
- **Shadow panel (JS)** renders one color row per role dynamically.

Roles are validated after filtering: entries missing any of the three
required keys are silently dropped. Values are sanitized via
`sanitize_key()` (slug, meta_key) and `sanitize_text_field()` (label).

## Example

```php
// Add an "accent" and "accent-dark" role to every taxonomy term.
add_filter( 'gptc_term_color_roles', function ( array $roles ): array {
    $roles[] = array(
        'slug'     => 'accent',
        'label'    => __( 'Accent', 'my-theme' ),
        'meta_key' => 'term_color_accent',
    );
    $roles[] = array(
        'slug'     => 'accent-dark',
        'label'    => __( 'Accent Dark', 'my-theme' ),
        'meta_key' => 'term_color_accent_dark',
    );
    return $roles;
} );
```

## Example

```php
// Replace the defaults entirely with a single "base" role.
add_filter( 'gptc_term_color_roles', function (): array {
    return array(
        array(
            'slug'     => 'base',
            'label'    => __( 'Base', 'my-theme' ),
            'meta_key' => 'term_color_base',
        ),
    );
} );
```

## Parameters

- *`array<int,`* `array{slug:` string, label: string, meta_key: string}> $roles
  - *`string`* `$slug` Unique role identifier for CSS and token slugs.
  - *`string`* `$label` Human-readable label for admin UI and editor.
  - *`string`* `$meta_key` Term meta key for storing the hex color value.

## Files

- [plugin.php:209](https://github.com/carstingaxion/gatherpress-taxonomy-colors/blob/main/plugin.php#L209)
```php
apply_filters( 'gptc_term_color_roles', $defaults )
```



[← All Hooks](Hooks.md)
