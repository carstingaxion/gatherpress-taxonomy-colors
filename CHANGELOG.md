# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/carstingaxion/gatherpress-taxonomy-colors/compare/0.1.2...HEAD)

## [0.1.2](https://github.com/carstingaxion/gatherpress-taxonomy-colors/compare/0.1.0...0.1.2) - 2026-05-23

- Added Layer 6: Shadow taxonomy support for post types with `gatherpress-shadow-source`.
- Shadow term color pickers on post editor screens via block editor sidebar panel.
- Admin list table color swatch columns for shadow-source post types.
- Two-path frontend resolution: shadow-source and consumer paths.
- Graceful degradation when GatherPress is not active.

### 0.1.1

- DRY refactor: extracted `Singleton` trait, `Helpers` class with shared utilities.
- Centralized taxonomy slug normalization, CSS block generation, palette merging, and term color resolution.

### 0.1.0

- Initial release
- Term meta registration with REST API visibility.
- Per-taxonomy design token slots via `theme.json` filter.
- Frontend and editor contextual resolution.
- Query Loop scoped resolution via `WP_HTML_Tag_Processor`.
- Per-term color injection on `core/post-terms` blocks with "Term Colors" block style.
