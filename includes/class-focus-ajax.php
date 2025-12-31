<?php
/**
 * Focus Ajax Class
 *
 * Handles AJAX requests for focus point overrides.
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
 * Focus Ajax class.
 *
 * Provides AJAX endpoints for saving and retrieving
 * per-page focus point overrides.
 *
 * @since 1.1.0
 */
class Focus_Ajax {

	/**
	 * Post meta key for storing focus overrides.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const META_KEY = '_mwe_etchwp_enhancements_focus_overrides';

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Ajax|null
	 */
	private static ?Focus_Ajax $instance = null;

	/**
	 * Main Focus_Ajax Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Ajax Main instance.
	 */
	public static function get_instance(): Focus_Ajax {
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
		// Private constructor.
	}

	/**
	 * Initialize AJAX hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_mwe_save_focus_override', array( $this, 'save_focus_override' ) );
		add_action( 'wp_ajax_mwe_get_focus_override', array( $this, 'get_focus_override' ) );
		add_action( 'wp_ajax_mwe_delete_focus_override', array( $this, 'delete_focus_override' ) );
		add_action( 'wp_ajax_mwe_get_global_focus_point', array( $this, 'get_global_focus_point' ) );
		add_action( 'wp_ajax_mwe_get_all_focus_overrides', array( $this, 'get_all_focus_overrides' ) );
	}

	/**
	 * Save a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function save_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		// Get and validate parameters.
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_key   = isset( $_POST['image_key'] ) ? sanitize_text_field( wp_unslash( $_POST['image_key'] ) ) : '';
		$focus_point = isset( $_POST['focus_point'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_point'] ) ) : '';

		if ( ! $post_id || ! $image_key || ! $focus_point ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		// Validate focus point format (e.g., "30% 70%").
		if ( ! $this->is_valid_focus_point( $focus_point ) ) {
			wp_send_json_error( array( 'message' => 'Invalid focus point format' ), 400 );
		}

		// Get existing overrides.
		$overrides = $this->get_overrides_for_post( $post_id );

		// Update or add the override.
		$overrides[ $image_key ] = $focus_point;

		// Save to post meta.
		update_post_meta( $post_id, self::META_KEY, $overrides );

		wp_send_json_success(
			array(
				'message'     => 'Focus point saved',
				'image_key'   => $image_key,
				'focus_point' => $focus_point,
			)
		);
	}

	/**
	 * Get a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function get_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$image_key = isset( $_GET['image_key'] ) ? sanitize_text_field( wp_unslash( $_GET['image_key'] ) ) : '';

		if ( ! $post_id || ! $image_key ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		$overrides   = $this->get_overrides_for_post( $post_id );
		$focus_point = $overrides[ $image_key ] ?? null;

		wp_send_json_success(
			array(
				'image_key'    => $image_key,
				'focus_point'  => $focus_point,
				'has_override' => null !== $focus_point,
			)
		);
	}

	/**
	 * Delete a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function delete_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_key = isset( $_POST['image_key'] ) ? sanitize_text_field( wp_unslash( $_POST['image_key'] ) ) : '';

		if ( ! $post_id || ! $image_key ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		$overrides = $this->get_overrides_for_post( $post_id );

		if ( isset( $overrides[ $image_key ] ) ) {
			unset( $overrides[ $image_key ] );
			update_post_meta( $post_id, self::META_KEY, $overrides );
		}

		wp_send_json_success( array( 'message' => 'Override deleted' ) );
	}

	/**
	 * Get all focus point overrides for a post.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function get_all_focus_overrides(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Missing post_id' ), 400 );
		}

		$overrides = $this->get_overrides_for_post( $post_id );

		wp_send_json_success( array( 'overrides' => $overrides ) );
	}

	/**
	 * Get all overrides for a post.
	 *
	 * @since  1.1.0
	 * @param  int $post_id The post ID.
	 * @return array        The overrides array.
	 */
	public function get_overrides_for_post( int $post_id ): array {
		$overrides = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Get override for a specific image.
	 *
	 * @since  1.1.0
	 * @param  int    $post_id   The post ID.
	 * @param  string $image_key The image key (attachment ID or URL hash).
	 * @return string|null       The focus point override or null.
	 */
	public function get_override( int $post_id, string $image_key ): ?string {
		$overrides = $this->get_overrides_for_post( $post_id );
		return $overrides[ $image_key ] ?? null;
	}

	/**
	 * Generate image key from URL (for external images).
	 *
	 * @since  1.1.0
	 * @param  string $url The image URL.
	 * @return string      The image key (prefixed MD5 hash).
	 */
	public static function generate_url_key( string $url ): string {
		return 'url_' . md5( $url );
	}

	/**
	 * Get global focus point for an image URL from Media Library.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function get_global_focus_point(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$image_url = isset( $_GET['image_url'] ) ? esc_url_raw( wp_unslash( $_GET['image_url'] ) ) : '';

		if ( ! $image_url ) {
			wp_send_json_error( array( 'message' => 'Missing image_url' ), 400 );
		}

		// Try to get attachment ID from URL.
		$attachment_id = attachment_url_to_postid( $image_url );

		// If that fails, try with the Helper class.
		if ( ! $attachment_id && class_exists( 'MWE\\EtchWP_Enhancements\\Helper' ) ) {
			$attachment_id = Helper::find_attachment_by_filename( $image_url );
		}

		if ( ! $attachment_id ) {
			wp_send_json_success(
				array(
					'focus_point'   => null,
					'attachment_id' => null,
				)
			);
			return;
		}

		// Get focus point from post meta.
		$focus_point = get_post_meta( $attachment_id, 'bg_pos_desktop', true );

		wp_send_json_success(
			array(
				'focus_point'   => $focus_point ? $focus_point : null,
				'attachment_id' => $attachment_id,
			)
		);
	}

	/**
	 * Validate focus point format.
	 *
	 * @since  1.1.0
	 * @param  string $focus_point The focus point string.
	 * @return bool                True if valid.
	 */
	private function is_valid_focus_point( string $focus_point ): bool {
		// Pattern: "XX% YY%" where XX and YY are 0-100.
		return (bool) preg_match( '/^(\d{1,3}(\.\d+)?%)\s+(\d{1,3}(\.\d+)?%)$/', $focus_point );
	}
}
