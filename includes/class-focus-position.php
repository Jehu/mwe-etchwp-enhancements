<?php
/**
 * Focus Position Class
 *
 * Integrates focus position support from focus point plugins with Etch page builder.
 * Adds object-position CSS to images based on focus point data.
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
 * Focus Position class.
 *
 * @since 1.0.0
 */
class Focus_Position {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var Focus_Position|null
	 */
	private static $instance = null;

	/**
	 * Main Focus_Position Instance.
	 *
	 * Ensures only one instance of Focus_Position is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @return Focus_Position Main instance.
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
	 * Initialize the focus position feature.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init() {
		// Add focus point data to attachment metadata.
		add_filter( 'wp_get_attachment_metadata', array( $this, 'add_focus_to_metadata' ), 10, 2 );

		// Add support for Etch page builder - hook AFTER Etch processes images.
		add_filter( 'render_block', array( $this, 'filter_images' ), 15, 2 );
	}

	/**
	 * Add focus point data to attachment metadata.
	 *
	 * @since  1.0.0
	 * @param  array $metadata      The attachment metadata.
	 * @param  int   $attachment_id The attachment ID.
	 * @return array                The modified metadata.
	 */
	public function add_focus_to_metadata( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		$desktop = get_post_meta( $attachment_id, 'bg_pos_desktop', true );
		$mobile  = get_post_meta( $attachment_id, 'bg_pos_mobile', true );

		if ( $desktop || $mobile ) {
			$metadata['focus_point'] = array(
				'desktop' => $desktop ? $desktop : '50% 50%',
				'mobile'  => $mobile ? $mobile : $desktop,
			);
		}

		return $metadata;
	}

	/**
	 * Apply focus points to images in Etch blocks.
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

		// Apply focus points to images in the block content.
		$block_content = preg_replace_callback(
			'/<img([^>]+)src=["\']([^"\']*wp-content\/uploads[^"\']*)["\']([^>]*)>/i',
			array( $this, 'add_focus_to_image' ),
			$block_content
		);

		return $block_content;
	}

	/**
	 * Add focus point to individual Etch image.
	 *
	 * @since  1.0.0
	 * @param  array $matches Regex matches from preg_replace_callback.
	 * @return string         The enhanced image tag.
	 */
	public function add_focus_to_image( $matches ) {
		$full_tag = $matches[0];
		$src      = $matches[2];

		// Get current post ID for override lookup.
		$post_id = $this->get_current_post_id();

		// Try to get attachment ID from src URL.
		$attachment_id = attachment_url_to_postid( $src );

		// If that fails, try a more comprehensive search.
		if ( ! $attachment_id ) {
			$attachment_id = Helper::find_attachment_by_filename( $src );
		}

		// Determine the image key for override lookup.
		$image_key = $attachment_id
			? 'attachment_' . $attachment_id
			: Focus_Ajax::generate_url_key( $src );

		// Check for per-page override first.
		$position = null;
		if ( $post_id ) {
			$position = $this->get_override_position( $post_id, $image_key );
		}

		// Fall back to global focus point from Media Library.
		// Use get_post_meta directly to avoid potential recursion through
		// wp_get_attachment_metadata filter.
		if ( ! $position && $attachment_id ) {
			$position = get_post_meta( $attachment_id, 'bg_pos_desktop', true );
		}

		// No position found, return original tag.
		if ( ! $position || '50% 50%' === $position ) {
			return $full_tag;
		}

		// Check if object-position is already applied.
		if ( false !== strpos( $full_tag, 'object-position:' ) ) {
			return $full_tag; // Already has focus point applied.
		}

		// Add or modify style attribute.
		if ( false !== strpos( $full_tag, 'style=' ) ) {
			// Style attribute exists, append to it.
			$full_tag = preg_replace(
				'/style=["\']([^"\']*)["\']/',
				'style="$1; object-position: ' . esc_attr( $position ) . '"',
				$full_tag
			);
		} else {
			// No style attribute, add one.
			$full_tag = str_replace( '<img', '<img style="object-position: ' . esc_attr( $position ) . '"', $full_tag );
		}

		// Enhance image with missing attributes if Image_Enhancement is available.
		if ( class_exists( 'MWE\\EtchWP_Enhancements\\Image_Enhancement' ) ) {
			$enhancement = Image_Enhancement::get_instance();
			$full_tag    = $enhancement->add_attributes( $full_tag, $attachment_id );
		}

		return $full_tag;
	}

	/**
	 * Get override position from post meta.
	 *
	 * @since  1.1.0
	 * @param  int    $post_id   The post ID.
	 * @param  string $image_key The image key.
	 * @return string|null       The focus point position or null.
	 */
	private function get_override_position( int $post_id, string $image_key ): ?string {
		if ( ! class_exists( 'MWE\\EtchWP_Enhancements\\Focus_Ajax' ) ) {
			return null;
		}

		return Focus_Ajax::get_instance()->get_override( $post_id, $image_key );
	}

	/**
	 * Get the current post ID.
	 *
	 * @since  1.1.0
	 * @return int The post ID or 0.
	 */
	private function get_current_post_id(): int {
		global $post;

		if ( $post instanceof \WP_Post ) {
			return $post->ID;
		}

		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post ) {
			return $queried->ID;
		}

		return 0;
	}
}
