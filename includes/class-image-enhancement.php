<?php
/**
 * Image Enhancement Class
 *
 * Enhances Etch page builder images by automatically adding missing attributes
 * like srcset, width, height, alt text, and sizes.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @link       https://www.michelyweb.de
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Enhancement class.
 *
 * @since 1.0.0
 */
class Image_Enhancement {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var Image_Enhancement|null
	 */
	private static $instance = null;

	/**
	 * Main Image_Enhancement Instance.
	 *
	 * Ensures only one instance of Image_Enhancement is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @return self Main instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.
	}

	/**
	 * Initialize the image enhancement feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init() {
		// Add support for Etch page builder - hook AFTER Etch processes images.
		add_filter( 'render_block', array( $this, 'filter_images' ), 15, 2 );

		/**
		 * Filter whether to disable WordPress core's `sizes="auto, …"` for lazy-loaded images entirely.
		 *
		 * Before 1.2.12 the plugin always disabled it (`wp_img_tag_add_auto_sizes` → false), which
		 * also switched it off for every image the plugin never touches (e.g. `etch/dynamic-image`).
		 * The default now keeps core's behaviour and only guards attribute-sized images (see
		 * `guard_auto_sizes()`). Return true to restore the old global behaviour.
		 *
		 * @since 1.2.12
		 * @param bool $disable Whether to disable auto-sizes globally. Default false.
		 */
		if ( apply_filters( 'mwe_etchwp_disable_auto_sizes', false ) ) {
			add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
			return;
		}

		// Core adds `auto` inside wp_filter_content_tags() and then hands every <img> to this filter.
		add_filter( 'wp_content_img_tag', array( $this, 'guard_auto_sizes' ), 20, 3 );
	}

	/**
	 * Remove core's `auto` sizes keyword from small, attribute-sized images.
	 *
	 * WordPress 6.7+ prepends `auto` to the sizes attribute of lazy-loaded images so the browser
	 * picks a srcset candidate from the rendered width. That is right for content images, but an
	 * image that relies on its width attribute for its size (a 56px icon, a slider arrow) has no
	 * CSS box when `auto` is evaluated and gets laid out at container width. This strips `auto`
	 * from images whose width attribute is below a threshold and leaves everything else alone.
	 *
	 * @since  1.2.12
	 * @param  string $filtered_image The full <img> tag.
	 * @param  string $context        Additional context (unused).
	 * @param  int    $attachment_id  The attachment ID (unused).
	 * @return string                 The (possibly modified) <img> tag.
	 */
	public function guard_auto_sizes( $filtered_image, $context = '', $attachment_id = 0 ) {
		if ( ! is_string( $filtered_image ) || ! preg_match( '/\ssizes=["\']auto\s*,/i', $filtered_image ) ) {
			return $filtered_image;
		}

		if ( ! preg_match( '/\swidth=["\'](\d+)["\']/i', $filtered_image, $matches ) ) {
			return $filtered_image;
		}

		$min_width = $this->get_auto_sizes_min_width();

		if ( (int) $matches[1] >= $min_width ) {
			return $filtered_image;
		}

		return preg_replace( '/(\ssizes=["\'])auto\s*,\s*/i', '$1', $filtered_image, 1 );
	}

	/**
	 * Get the minimum width attribute below which an image is treated as attribute-sized.
	 *
	 * Shared knob for guard_auto_sizes() (strips core's `auto` keyword) and
	 * add_attributes() (skips srcset/sizes) so both classify images identically.
	 *
	 * @since  1.2.12
	 * @return int Minimum width attribute in px. At least 1.
	 */
	private function get_auto_sizes_min_width(): int {
		/**
		 * Filter the width (in px, from the width attribute) below which an image is treated as
		 * attribute-sized: it receives no srcset/sizes attributes from this plugin and loses
		 * core's `auto` sizes keyword.
		 *
		 * @since 1.2.12
		 * @param int $min_width Minimum width attribute to keep responsive attributes and `auto`. Default 150.
		 */
		return max( 1, (int) apply_filters( 'mwe_etchwp_auto_sizes_min_width', 150 ) );
	}

	/**
	 * Apply image enhancements to images in Etch blocks.
	 *
	 * @since  1.0.0
	 * @param  string $block_content The block content.
	 * @param  array  $block         The block data.
	 * @return string                The modified block content.
	 */
	public function filter_images( $block_content, $block ) {
		$block_name = $block['blockName'] ?? '';

		// Process only supported Etch blocks that can contain images.
		if ( ! Helper::is_processable_etch_block( $block_name ) ) {
			return $block_content;
		}

		// Skip blocks that handle their own responsive images (e.g., etch/dynamic-image).
		if ( Helper::should_skip_responsive_images( $block_name ) ) {
			return $block_content;
		}

		// Apply enhancements to images in the block content.
		$block_content = preg_replace_callback(
			'/<img([^>]+)src=["\']([^"\']*wp-content\/uploads[^"\']*)["\']([^>]*)>/i',
			array( $this, 'enhance_image' ),
			$block_content
		);

		return $block_content;
	}

	/**
	 * Enhance individual Etch image with missing attributes.
	 *
	 * @since  1.0.0
	 * @param  array $matches Regex matches from preg_replace_callback.
	 * @return string         The enhanced image tag.
	 */
	public function enhance_image( $matches ) {
		$full_tag = $matches[0];
		$src      = $matches[2];

		// Performance optimization: Check if any attributes are actually missing.
		// Skip expensive DB lookups for images that already have all attributes.
		$needs_srcset = false === strpos( $full_tag, 'srcset=' );
		$needs_sizes  = false === strpos( $full_tag, 'sizes=' );
		$needs_width  = false === strpos( $full_tag, 'width=' );
		$needs_height = false === strpos( $full_tag, 'height=' );
		$needs_alt    = false === strpos( $full_tag, 'alt=' ) || preg_match( '/alt=["\']["\']/', $full_tag ) || preg_match( '/alt=["\']-["\']/', $full_tag );

		// If nothing is missing, return early (avoid DB queries).
		if ( ! $needs_srcset && ! $needs_sizes && ! $needs_width && ! $needs_height && ! $needs_alt ) {
			return $full_tag;
		}

		// Attribute-sized images receive no srcset/sizes (see add_attributes()): when those
		// are the only missing attributes and the existing width attribute is below the
		// auto-sizes threshold, skip the attachment lookup entirely.
		if ( ( $needs_srcset || $needs_sizes ) && ! $needs_width && ! $needs_height && ! $needs_alt ) {
			if ( preg_match( '/\swidth=["\'](\d+)["\']/i', $full_tag, $width_attr ) && (int) $width_attr[1] < $this->get_auto_sizes_min_width() ) {
				return $full_tag;
			}
		}

		// Get attachment ID from URL (uses caching and comprehensive lookup).
		$attachment_id = Helper::get_attachment_id_from_url( $src );

		if ( ! $attachment_id ) {
			return $full_tag;
		}

		// Enhance image with missing attributes.
		$full_tag = $this->add_attributes( $full_tag, $attachment_id );

		return $full_tag;
	}

	/**
	 * Enhance image tag with missing attributes (srcset, dimensions, alt, sizes).
	 *
	 * Only adds attributes if they don't already exist (even if empty).
	 *
	 * @since  1.0.0
	 * @param  string $img_tag       The image tag HTML.
	 * @param  int    $attachment_id The attachment ID.
	 * @return string                The enhanced image tag.
	 */
	public function add_attributes( $img_tag, $attachment_id ) {
		// Get attachment metadata and post data.
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$attachment = get_post( $attachment_id );

		if ( ! $metadata || ! $attachment ) {
			return $img_tag;
		}

		$attributes_to_add = array();

		// Extract dimensions from filename if present (e.g., my-image-1440x960.webp).
		$src_url = '';
		if ( preg_match( '/src=["\']([^"\']*)["\']/', $img_tag, $src_matches ) ) {
			$src_url = $src_matches[1];
		}

		$width  = null;
		$height = null;

		if ( $src_url ) {
			$filename = basename( $src_url );
			if ( preg_match( '/-(\d+)x(\d+)\.[^.]+$/', $filename, $size_matches ) ) {
				$width  = intval( $size_matches[1] );
				$height = intval( $size_matches[2] );
			}
		}

		// Fallback to metadata dimensions if no size found in filename.
		if ( ! $width && isset( $metadata['width'] ) ) {
			$width = $metadata['width'];
		}
		if ( ! $height && isset( $metadata['height'] ) ) {
			$height = $metadata['height'];
		}

		// Attribute-sized images (effective width below the auto-sizes threshold) get no
		// srcset/sizes: without a sizes attribute core never prepends `auto`, so the browser
		// sizes these images from their width attribute instead of the container width.
		// An existing width attribute wins over the resolved intrinsic width. Images with no
		// width information at all keep the previous behaviour (documented decision, issue #9).
		$effective_width = $width;
		if ( preg_match( '/\swidth=["\'](\d+)["\']/i', $img_tag, $width_attr ) ) {
			$effective_width = (int) $width_attr[1];
		}
		$is_attribute_sized = null !== $effective_width && $effective_width < $this->get_auto_sizes_min_width();

		// Add width if not present.
		if ( false === strpos( $img_tag, 'width=' ) && $width ) {
			$attributes_to_add[] = 'width="' . $width . '"';
		}

		// Add height if not present.
		if ( false === strpos( $img_tag, 'height=' ) && $height ) {
			$attributes_to_add[] = 'height="' . $height . '"';
		}

		// Handle alt attribute:
		// - alt="-" (hyphen) = intentional decorative image, normalize to alt=""
		// - alt="" (empty) = load alt text from media library
		// - no alt attribute = load alt text from media library (or empty fallback)
		$is_decorative      = preg_match( '/alt=["\']-["\']/', $img_tag ); // Hyphen inside quotes.
		$already_decorative = false !== strpos( $img_tag, 'data-decorative="true"' ); // Already processed.
		$has_empty_alt      = preg_match( '/alt=["\']["\']/', $img_tag ) && ! $already_decorative; // Empty quotes (but not decorative).
		$has_no_alt    = false === strpos( $img_tag, 'alt=' );

		if ( $is_decorative ) {
			// Normalize decorative marker (hyphen) to proper empty alt.
			// Add data attribute to prevent re-processing by other filters.
			$img_tag = preg_replace( '/alt=["\']-["\']/', 'alt="" data-decorative="true"', $img_tag );
		} elseif ( $has_empty_alt || $has_no_alt ) {
			// Load alt text from media library.
			$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( $alt_text ) {
				if ( $has_empty_alt ) {
					// Replace empty alt with media library alt text.
					$img_tag = preg_replace( '/alt=["\']["\']/', 'alt="' . esc_attr( $alt_text ) . '"', $img_tag );
				} else {
					// Add alt attribute.
					$attributes_to_add[] = 'alt="' . esc_attr( $alt_text ) . '"';
				}
			} elseif ( $has_no_alt ) {
				// Add empty alt for accessibility if no alt text is set.
				$attributes_to_add[] = 'alt=""';
			}
		}

		// Add srcset if not present (never for attribute-sized images).
		$srcset_added = false;
		if ( false === strpos( $img_tag, 'srcset=' ) && ! $is_attribute_sized ) {
			$srcset = wp_get_attachment_image_srcset( $attachment_id );
			if ( $srcset ) {
				$attributes_to_add[] = 'srcset="' . esc_attr( $srcset ) . '"';
				$srcset_added        = true;
			}
		}

		// Add sizes if not present and srcset exists (either already present or just added).
		$has_srcset = ( false !== strpos( $img_tag, 'srcset=' ) ) || $srcset_added;
		if ( false === strpos( $img_tag, 'sizes=' ) && $has_srcset && ! $is_attribute_sized ) {
			$sizes = wp_get_attachment_image_sizes( $attachment_id );
			if ( $sizes ) {
				$attributes_to_add[] = 'sizes="' . esc_attr( $sizes ) . '"';
			}
		}

		// Add all missing attributes to the img tag.
		if ( ! empty( $attributes_to_add ) ) {
			$attributes_string = ' ' . implode( ' ', $attributes_to_add );
			$img_tag           = str_replace( '<img', '<img' . $attributes_string, $img_tag );
		}

		return $img_tag;
	}
}
