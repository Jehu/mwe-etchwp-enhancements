# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that enhances the Etch page builder with automatic image attribute enhancement and focus position support. Uses strict typing (PHP 8.1+) and follows WordPress Coding Standards.

## Architecture

### Class Structure (Singleton Pattern)

```
MWE\EtchWP_Enhancements\
├── Plugin                    # Main orchestrator, dependency checks, feature initialization
├── Image_Enhancement         # Adds srcset, dimensions, alt, sizes to images
├── Focus_Position            # Applies CSS object-position from focus point data
└── Helper                    # Shared utilities (attachment lookup, block detection)
```

**Key Design Decision**: All features use Singletons accessed via `::get_instance()`. Each has an `init()` method that registers WordPress hooks.

### Plugin Initialization Flow

1. `plugins_loaded` hook → `init_plugin()`
2. `Plugin::get_instance()->init()`
3. Check dependencies (Etch active)
4. Initialize enabled features via `init_features()`
5. Each feature registers its own `render_block` filter

### Hook Priorities

- **Image_Enhancement**: Priority 15 on `render_block` (after Etch at 10)
- **Focus_Position**: Priority 15 on `render_block`, also hooks `wp_get_attachment_metadata` at 10
- Focus Position reads `bg_pos_desktop` and `bg_pos_mobile` post meta from focus point plugins

### Block Processing

Only processes Etch blocks defined in `Helper::is_processable_etch_block()`:
- `etch/element` - Main HTML elements
- `etch/dynamic-element` - Dynamic HTML
- `etch/raw-html` - Raw HTML blocks
- `etch/component` - Components

Customizable via `mwe_etchwp_processable_blocks` filter.

### Feature Detection Pattern

**Important**: Features initialize if enabled (via filter/constant), NOT based on plugin detection. This avoids timing issues with WordPress plugin loading. Features check for actual data at runtime (e.g., focus point metadata).

## Release Process

### Version Update Checklist

1. Update version in three places:
   - `mwe-etchwp-enhancements.php` header comment
   - `MWE_ETCHWP_VERSION` constant
   - `readme.txt` Stable tag

2. Add changelog entries to:
   - `readme.txt` (== Changelog ==)
   - `readme.txt` (== Upgrade Notice ==)
   - `README.md` (## Changelog)

3. Commit, tag, and release:
   ```bash
   git add -A
   git commit -m "Release version X.Y.Z"
   git tag vX.Y.Z
   git push origin main
   git push origin vX.Y.Z
   gh release create vX.Y.Z --title "vX.Y.Z - Title" --notes "Release notes..."
   ```

4. Create WordPress-compatible ZIP:
   ```bash
   zip -r mwe-etchwp-enhancements.zip mwe-etchwp-enhancements -x "*.git*" "*.DS_Store"
   gh release upload vX.Y.Z mwe-etchwp-enhancements.zip --clobber
   ```

**Critical**: GitHub auto-generates ZIPs with version-suffixed folders (`mwe-etchwp-enhancements-1.0.2/`) which WordPress treats as a new plugin. Always upload a properly structured ZIP as a release asset.

## Configuration

### Disable Features

```php
// Via filters (in functions.php)
add_filter( 'mwe_etchwp_enable_image_enhancement', '__return_false' );
add_filter( 'mwe_etchwp_enable_focus_position', '__return_false' );

// Via constants (in wp-config.php)
define( 'MWE_ETCHWP_IMAGE_ENHANCEMENT', false );
define( 'MWE_ETCHWP_FOCUS_POSITION', false );
```

### Customize Block Types

```php
add_filter( 'mwe_etchwp_processable_blocks', function( $blocks ) {
    $blocks[] = 'etch/custom-block';
    return $blocks;
} );
```

## Debugging

Focus Position reads metadata from:
- `bg_pos_desktop` - Desktop focal point (e.g., "30% 70%")
- `bg_pos_mobile` - Mobile focal point (falls back to desktop if empty)

Data added to attachment metadata via `wp_get_attachment_metadata` filter with key `focus_point`.

## Autoloader

PSR-4 autoloader in main plugin file:
- Namespace: `MWE\EtchWP_Enhancements\`
- Base dir: `includes/`
- Convention: `Class_Name` → `class-class-name.php`
