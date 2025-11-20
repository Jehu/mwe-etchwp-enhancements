<?php
/**
 * Helper Class
 *
 * Provides shared utility functions for the plugin.
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
 * Helper class with shared utility functions.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Check if a block type should be processed for image enhancement.
	 *
	 * This method determines which Etch block types contain images and should
	 * be processed by the image enhancement and focus position features.
	 *
	 * @since  1.0.1
	 * @param  string $block_name The block name to check.
	 * @return bool               Whether the block should be processed.
	 */
	public static function is_processable_etch_block( $block_name ) {
		// Default Etch blocks that can contain images.
		$processable_blocks = array(
			'etch/element',         // HTML elements (main image container).
			'etch/dynamic-element', // Dynamic HTML elements.
			'etch/raw-html',        // Raw HTML blocks.
			'etch/component',       // Component blocks (can contain any content).
		);

		/**
		 * Filter the list of processable Etch block types.
		 *
		 * Allows customization of which block types are processed for image
		 * enhancement and focus position features.
		 *
		 * @since 1.0.1
		 *
		 * @param array $processable_blocks Array of block names that should be processed.
		 */
		$processable_blocks = apply_filters( 'mwe_etchwp_processable_blocks', $processable_blocks );

		return in_array( $block_name, $processable_blocks, true );
	}

	/**
	 * Find attachment ID by searching for filename in database.
	 *
	 * This method tries multiple strategies to find the attachment:
	 * 1. Searches in _wp_attached_file meta with different filename variations
	 * 2. Falls back to searching in the guid field
	 *
	 * @since  1.0.0
	 * @param  string $src The image source URL.
	 * @return int|null    The attachment ID if found, null otherwise.
	 */
	public static function find_attachment_by_filename( $src ) {
		global $wpdb;

		// Extract filename from URL.
		$filename = basename( $src );

		// Remove size suffixes (e.g., -1440x960, -scaled, etc.).
		$base_filename = preg_replace( '/-\d+x\d+\./', '.', $filename );
		$base_filename = preg_replace( '/-scaled\./', '.', $base_filename );

		// Also get the original filename without any suffix.
		$original_filename = preg_replace( '/-[^.]*\./', '.', $filename );

		// Search for attachments with matching filenames.
		$query = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_wp_attached_file'
			AND (meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)
			LIMIT 1",
			'%' . $wpdb->esc_like( $filename ),
			'%' . $wpdb->esc_like( $base_filename ),
			'%' . $wpdb->esc_like( $original_filename )
		);

		$attachment_id = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $attachment_id ) {
			// Try searching in the guid field as a last resort.
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'attachment'
				AND guid LIKE %s
				LIMIT 1",
				'%' . $wpdb->esc_like( $original_filename )
			);
			$attachment_id = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $attachment_id ? intval( $attachment_id ) : null;
	}
}
