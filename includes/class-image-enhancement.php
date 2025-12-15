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
	 * @return Image_Enhancement Main instance.
	 */
	public static function get_instance() {
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

		// Disable automatic sizes attribute to have full control over responsive images.
		add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
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
		// Process only supported Etch blocks that can contain images.
		if ( ! Helper::is_processable_etch_block( $block['blockName'] ?? '' ) ) {
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
		$needs_alt    = false === strpos( $full_tag, 'alt=' ) || preg_match( '/alt=["\']["\']/', $full_tag );

		// If nothing is missing, return early (avoid DB queries).
		if ( ! $needs_srcset && ! $needs_sizes && ! $needs_width && ! $needs_height && ! $needs_alt ) {
			return $full_tag;
		}

		// Try to get attachment ID from src URL.
		$attachment_id = attachment_url_to_postid( $src );

		// If that fails, try a more comprehensive search.
		if ( ! $attachment_id ) {
			$attachment_id = Helper::find_attachment_by_filename( $src );
		}

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

		// Add width if not present.
		if ( false === strpos( $img_tag, 'width=' ) && $width ) {
			$attributes_to_add[] = 'width="' . $width . '"';
		}

		// Add height if not present.
		if ( false === strpos( $img_tag, 'height=' ) && $height ) {
			$attributes_to_add[] = 'height="' . $height . '"';
		}

		// Handle alt attribute:
		// - alt=" " (with space) = intentional decorative image, normalize to alt=""
		// - alt="" (empty) = load alt text from media library
		// - no alt attribute = load alt text from media library (or empty fallback)
		$is_decorative = preg_match( '/alt=["\'] ["\']/', $img_tag ); // Space inside quotes.
		$has_empty_alt = preg_match( '/alt=["\']["\']/', $img_tag );  // Empty quotes.
		$has_no_alt    = false === strpos( $img_tag, 'alt=' );

		if ( $is_decorative ) {
			// Normalize decorative marker (space) to proper empty alt.
			$img_tag = preg_replace( '/alt=["\'] ["\']/', 'alt=""', $img_tag );
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

		// Add srcset if not present.
		$srcset_added = false;
		if ( false === strpos( $img_tag, 'srcset=' ) ) {
			$srcset = wp_get_attachment_image_srcset( $attachment_id );
			if ( $srcset ) {
				$attributes_to_add[] = 'srcset="' . esc_attr( $srcset ) . '"';
				$srcset_added        = true;
			}
		}

		// Add sizes if not present and srcset exists (either already present or just added).
		$has_srcset = ( false !== strpos( $img_tag, 'srcset=' ) ) || $srcset_added;
		if ( false === strpos( $img_tag, 'sizes=' ) && $has_srcset ) {
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
