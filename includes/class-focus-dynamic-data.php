<?php
/**
 * Focus Dynamic Data Class
 *
 * Exposes focus point data in Etch's Dynamic Data system.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focus Dynamic Data class.
 *
 * Adds focus point information to Etch's dynamic data system,
 * making it available as {this.image.focusPoint} in templates.
 *
 * @since 1.1.0
 */
class Focus_Dynamic_Data {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Dynamic_Data|null
	 */
	private static ?Focus_Dynamic_Data $instance = null;

	/**
	 * Main Focus_Dynamic_Data Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Dynamic_Data Main instance.
	 */
	public static function get_instance(): Focus_Dynamic_Data {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.
	}

	/**
	 * Initialize the dynamic data hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		add_filter( 'etch/dynamic_data/post', array( $this, 'add_focus_point_to_post_data' ), 10, 2 );
	}

	/**
	 * Add focus point data to Etch's post dynamic data.
	 *
	 * @since  1.1.0
	 * @param  array $data    The dynamic data array.
	 * @param  int   $post_id The post ID.
	 * @return array          The modified data array.
	 */
	public function add_focus_point_to_post_data( array $data, int $post_id ): array {
		// Add focus point to featured image if present.
		if ( isset( $data['image'] ) && is_array( $data['image'] ) ) {
			$attachment_id = $data['image']['id'] ?? 0;
			if ( $attachment_id ) {
				$data['image']['focusPoint'] = $this->get_focus_point( (int) $attachment_id );
			}
		}

		// Also add to thumbnail for backward compatibility.
		if ( ! empty( $data['thumbnail'] ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$data['thumbnailFocusPoint'] = $this->get_focus_point( (int) $thumbnail_id );
			}
		}

		return $data;
	}

	/**
	 * Get focus point for an attachment.
	 *
	 * @since  1.1.0
	 * @param  int $attachment_id The attachment ID.
	 * @return string             The focus point value (e.g., "30% 70%").
	 */
	public function get_focus_point( int $attachment_id ): string {
		$desktop = get_post_meta( $attachment_id, 'bg_pos_desktop', true );

		if ( $desktop && is_string( $desktop ) ) {
			return $desktop;
		}

		return '50% 50%';
	}
}
