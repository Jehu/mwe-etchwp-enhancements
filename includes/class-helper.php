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
	 * This method tries multiple strategies to find the attachment with precision:
	 * 1. Exact filename match (highest priority)
	 * 2. Match without WordPress size suffixes (-1440x960, -scaled, -rotated, etc.)
	 * 3. Match with directory path to avoid collisions
	 * 4. Falls back to guid search only if above methods fail
	 *
	 * @since  1.0.0
	 * @param  string $src The image source URL.
	 * @return int|null    The attachment ID if found, null otherwise.
	 */
	public static function find_attachment_by_filename( $src ) {
		global $wpdb;

		// Parse the URL to get path components.
		$parsed_url = wp_parse_url( $src );
		$path       = $parsed_url['path'] ?? '';

		// Extract the path relative to wp-content/uploads.
		if ( preg_match( '#/wp-content/uploads/(.+)$#', $path, $matches ) ) {
			$relative_path = $matches[1];
		} else {
			return null;
		}

		// Extract filename and directory.
		$filename = basename( $relative_path );
		$dir_path = dirname( $relative_path );

		// Try 1: Exact match with full relative path (most precise).
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND meta_value = %s
				LIMIT 1",
				$relative_path
			)
		);

		if ( $attachment_id ) {
			return intval( $attachment_id );
		}

		// Try 2: Remove only known WordPress suffixes and search with directory.
		// Only remove: -scaled, -rotated, -NNNxNNN (size dimensions).
		$base_filename = $filename;

		// Remove dimension suffix (e.g., -1440x960).
		$base_filename = preg_replace( '/-(\d+)x(\d+)(\.[^.]+)$/', '$3', $base_filename );

		// Remove -scaled suffix.
		$base_filename = preg_replace( '/-scaled(\.[^.]+)$/', '$1', $base_filename );

		// Remove -rotated suffix.
		$base_filename = preg_replace( '/-rotated(\.[^.]+)$/', '$1', $base_filename );

		// If we modified the filename, try to find the original with directory path.
		if ( $base_filename !== $filename ) {
			$base_relative_path = ( '.' !== $dir_path ) ? trailingslashit( $dir_path ) . $base_filename : $base_filename;

			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
					WHERE meta_key = '_wp_attached_file'
					AND meta_value = %s
					LIMIT 1",
					$base_relative_path
				)
			);

			if ( $attachment_id ) {
				return intval( $attachment_id );
			}
		}

		// Try 3: Search within the same directory using LIKE (still safe).
		$dir_pattern = ( '.' !== $dir_path ) ? trailingslashit( $dir_path ) : '';
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND meta_value LIKE %s
				AND meta_value LIKE %s
				LIMIT 1",
				$wpdb->esc_like( $dir_pattern ) . '%',
				'%' . $wpdb->esc_like( $base_filename )
			)
		);

		if ( $attachment_id ) {
			return intval( $attachment_id );
		}

		// Try 4: Last resort - search in guid (least precise, kept for backwards compatibility).
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'attachment'
				AND guid LIKE %s
				LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		return $attachment_id ? intval( $attachment_id ) : null;
	}
}
