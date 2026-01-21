=== MWE EtchWP Enhancements ===
Contributors: marcomichely
Tags: etch, page builder, images, responsive, focus point
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.2.2
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Enhances Etch page builder with improved image handling and focus position support for responsive images.

== Description ==

MWE EtchWP Enhancements is a WordPress plugin that extends the functionality of the Etch page builder by automatically enhancing images with essential attributes and supporting focus position features.

= Features =

**Image Enhancement**
* Automatically adds `srcset` attributes for responsive image sources
* Extracts and adds `width` and `height` attributes from filename or metadata
* Adds `alt` text from attachment metadata or title
* Supports decorative images: Use `alt="-"` (hyphen) to mark as decorative
* Generates `sizes` attributes for responsive sizing hints
* Only adds attributes that are missing - never overwrites existing ones

**Focus Position Support** (requires additional plugin)
* Integrates with Image Background Focus Position or Media Focus Point plugins
* Applies custom focus points to images using CSS `object-position`
* Supports separate desktop and mobile focus positions
* Automatically falls back to center position when not configured

= Requirements =

**Required:**
* Etch page builder plugin (version 1.0.0-beta-15 or higher recommended)
* PHP 8.1 or higher
* WordPress 5.9 or higher

**Optional (for focus position feature):**
* Image Background Focus Position plugin OR
* Media Focus Point plugin

= How It Works =

The plugin hooks into Etch's block rendering process and automatically enhances `<img>` tags within supported Etch blocks. It intelligently detects which attributes are missing and adds them without modifying existing attributes.

For focus position support, the plugin reads focus point data from compatible plugins and applies CSS `object-position` to ensure images display with the correct focal point on all devices.

= Supported Blocks =

**Full processing (image enhancement + focus position):**
* `etch/element` - Standard HTML elements
* `etch/dynamic-element` - Dynamic HTML elements
* `etch/raw-html` - Raw HTML blocks
* `etch/component` - Component blocks

**Focus position only:**
* `etch/dynamic-image` - [Dynamic Image element](https://docs.etchwp.com/elements/dynamic-image) (Etch 1.0.0-beta-15+)

The Dynamic Image element in Etch already handles responsive image attributes (srcset, sizes, width, height) internally. This plugin only applies focus position styling to these images to avoid conflicts and duplicate processing.

== Installation ==

1. Ensure the Etch page builder plugin is installed and activated
2. Upload the `mwe-etchwp-enhancements` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. (Optional) Install and activate a focus position plugin for focal point features

== Frequently Asked Questions ==

= Does this plugin work without Etch? =

No, this plugin requires the Etch page builder plugin to be installed and activated. It will display an admin notice if Etch is not active.

= Which focus position plugins are supported? =

The plugin supports:
* Image Background Focus Position (https://www.wordpress-focalpoint.com/)
* Media Focus Point (https://wordpress.org/plugins/media-focus-point/)

You only need one of these plugins for the focus position feature to work.

= Can I disable certain features? =

Yes, you can disable features using WordPress filters in your theme's `functions.php`:

**Disable Image Enhancement:**
`add_filter( 'mwe_etchwp_enable_image_enhancement', '__return_false' );`

**Disable Focus Position:**
`add_filter( 'mwe_etchwp_enable_focus_position', '__return_false' );`

You can also use constants:
`define( 'MWE_ETCHWP_IMAGE_ENHANCEMENT', false );`
`define( 'MWE_ETCHWP_FOCUS_POSITION', false );`

= Will this slow down my site? =

No, the plugin processes images during block rendering with minimal overhead. It uses efficient database queries and only processes images within Etch blocks.

= Does this modify my original images? =

No, the plugin only modifies the HTML output. Your original images and their metadata remain unchanged.

== Changelog ==

= 1.2.2 =
* Added: Support for new `etch/dynamic-image` block (Etch 1.0.0-beta-15+)
* Added: New filter `mwe_etchwp_skip_responsive_blocks` to control which blocks skip responsive image processing
* Note: `etch/dynamic-image` receives focus position only (no srcset/sizes changes as Etch handles these)

= 1.2.1 =
* Fixed: Portrait images now display fully in Focus Point preview (no more clipping)
* Fixed: Focus Point UI now updates when image src is changed in panel
* Fixed: Height jump when Saving/Saved status appears

= 1.2.0 =
* Added: Automatic updates from GitHub Releases
* Added: One-click upgrade support via WordPress admin

= 1.1.0 =
* Added: Per-page focus point override feature for Etch editor
* Added: Visual click-to-set UI in Element Settings panel
* Added: Real-time preview of focus point changes in Etch iframe
* Added: Support for both WordPress Media Library images and external URLs
* Added: PHPUnit test infrastructure with 31 tests
* Improved: Parallel image processing for better performance

= 1.0.7 =
* Fixed: Decorative images were being overwritten by Focus_Position filter
* Added: `data-decorative="true"` attribute to prevent re-processing

= 1.0.6 =
* Fixed: Sizes attribute was not added when srcset already existed (inverted logic)
* Changed: New decorative image handling - use `alt="-"` (hyphen) to mark images as decorative
* Changed: Empty `alt=""` now loads alt text from media library (previously kept empty)

= 1.0.5 =
* Fixed: Substring matching bug in attachment lookup that caused wrong srcset URLs (e.g., "Lang.webp" incorrectly matching "franz-jascha-lang.webp")
* Improved: Performance optimization - images with complete attributes are now skipped (no database queries)
* Improved: Runtime cache prevents duplicate database lookups for the same image within a request
* Improved: More precise attachment ID matching with exact filename comparison

= 1.0.2 =
* Fixed: Focus Position feature now initializes correctly regardless of plugin detection timing
* Fixed: Removed dependency check that prevented Focus Position from working in some cases
* Improved: Feature detection now happens at runtime instead of during initialization

= 1.0.1 =
* Fixed: Updated block type detection for Etch compatibility
* Changed: Now supports etch/element, etch/dynamic-element, etch/raw-html, and etch/component blocks
* Added: New filter `mwe_etchwp_processable_blocks` to customize which block types are processed
* Removed: Support for legacy etch/block (no longer exists in current Etch versions)
* Improved: Better code documentation and centralized block detection logic

= 1.0.0 =
* Initial release
* Image enhancement feature with automatic srcset, dimensions, alt, and sizes attributes
* Focus position integration for Image Background Focus Position and Media Focus Point plugins
* Dependency checking with admin notices
* Filters and constants for feature control

== Upgrade Notice ==

= 1.2.2 =
Adds support for Etch 1.0.0-beta-15's new dynamic-image block with focus position processing.

= 1.2.1 =
Bugfix release: Fixes Focus Point UI issues with portrait images, input changes, and layout stability.

= 1.1.0 =
New feature: Per-page focus point overrides in Etch editor. Click on image preview to set custom focus points per page.

= 1.0.7 =
Fixes decorative image handling when Focus_Position is active. Update recommended.

= 1.0.6 =
New decorative image handling: Use `alt="-"` (hyphen) to mark decorative images. Empty `alt=""` now loads from media library.

= 1.0.5 =
Important bug fix for incorrect srcset URLs and major performance improvements. Update recommended for all users.

= 1.0.2 =
Fixes Focus Position feature initialization. Update recommended if using focus point plugins.

= 1.0.1 =
Important compatibility update for current Etch versions. Updates block type detection to support new Etch block architecture.

= 1.0.0 =
Initial release of MWE EtchWP Enhancements.

== Developer Information ==

= Filters =

**mwe_etchwp_enable_image_enhancement**
Control whether image enhancement is enabled.
`apply_filters( 'mwe_etchwp_enable_image_enhancement', true )`

**mwe_etchwp_enable_focus_position**
Control whether focus position feature is enabled.
`apply_filters( 'mwe_etchwp_enable_focus_position', true )`

**mwe_etchwp_processable_blocks**
Customize which Etch block types are processed for image enhancement and focus position.
`apply_filters( 'mwe_etchwp_processable_blocks', array( 'etch/element', 'etch/dynamic-element', 'etch/raw-html', 'etch/component', 'etch/dynamic-image' ) )`

**mwe_etchwp_skip_responsive_blocks**
Customize which blocks should skip responsive image processing (srcset/sizes). These blocks still receive focus position processing.
`apply_filters( 'mwe_etchwp_skip_responsive_blocks', array( 'etch/dynamic-image' ) )`

= Constants =

**MWE_ETCHWP_IMAGE_ENHANCEMENT**
Alternative way to enable/disable image enhancement.
`define( 'MWE_ETCHWP_IMAGE_ENHANCEMENT', false );`

**MWE_ETCHWP_FOCUS_POSITION**
Alternative way to enable/disable focus position.
`define( 'MWE_ETCHWP_FOCUS_POSITION', false );`

== Credits ==

Developed by Marco Michely
Website: https://www.michelyweb.de
