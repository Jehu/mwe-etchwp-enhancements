<?php
/**
 * Focus Editor UI Class
 *
 * Injects focus point UI into Etch's canvas editor.
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
 * Focus Editor UI class.
 *
 * Handles the injection of focus point UI into Etch's editor canvas.
 *
 * @since 1.1.0
 */
class Focus_Editor_UI {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Editor_UI|null
	 */
	private static ?Focus_Editor_UI $instance = null;

	/**
	 * Main Focus_Editor_UI Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Editor_UI Main instance.
	 */
	public static function get_instance(): Focus_Editor_UI {
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
	 * Initialize editor UI hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		// Enqueue assets in Etch canvas.
		add_action( 'etch/canvas/enqueue_assets', array( $this, 'enqueue_canvas_assets' ) );

		// Also enqueue on frontend when Etch builder is active.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_builder_assets' ) );
	}

	/**
	 * Enqueue assets for Etch canvas.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function enqueue_canvas_assets(): void {
		$this->enqueue_assets();
	}

	/**
	 * Maybe enqueue assets when Etch builder is active.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function maybe_enqueue_builder_assets(): void {
		// Check if we're in Etch builder mode.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['etch'] ) || 'magic' !== $_GET['etch'] ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Enqueue focus point editor assets.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	private function enqueue_assets(): void {
		$version = defined( 'MWE_ETCHWP_VERSION' ) ? MWE_ETCHWP_VERSION : '1.1.0';

		// Enqueue CSS.
		wp_enqueue_style(
			'mwe-focus-point-editor',
			MWE_ETCHWP_PLUGIN_URL . 'assets/css/focus-point-editor.css',
			array(),
			$version
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'mwe-focus-point-editor',
			MWE_ETCHWP_PLUGIN_URL . 'assets/js/focus-point-editor.js',
			array(),
			$version,
			true
		);

		// Get current post ID.
		$post_id = $this->get_current_post_id();

		// Localize script with necessary data.
		wp_localize_script(
			'mwe-focus-point-editor',
			'mweFocusPointEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mwe_focus_point_nonce' ),
				'postId'  => $post_id,
				'i18n'    => array(
					'focusPoint'  => __( 'Focus Point', 'mwe-etchwp-enhancements' ),
					'clickToSet'  => __( 'Click on image to set focus point', 'mwe-etchwp-enhancements' ),
					'reset'       => __( 'Reset', 'mwe-etchwp-enhancements' ),
					'useGlobal'   => __( 'Use Global', 'mwe-etchwp-enhancements' ),
					'saving'      => __( 'Saving...', 'mwe-etchwp-enhancements' ),
					'saved'       => __( 'Saved', 'mwe-etchwp-enhancements' ),
					'error'       => __( 'Error saving', 'mwe-etchwp-enhancements' ),
					'override'    => __( 'Override', 'mwe-etchwp-enhancements' ),
					'globalValue' => __( 'Global', 'mwe-etchwp-enhancements' ),
				),
			)
		);
	}

	/**
	 * Get the current post ID from various sources.
	 *
	 * @since  1.1.0
	 * @return int The post ID or 0.
	 */
	private function get_current_post_id(): int {
		// Try from query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( $_GET['post_id'] );
		}

		// Try from global post.
		global $post;
		if ( $post instanceof \WP_Post ) {
			return $post->ID;
		}

		// Try from queried object.
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post ) {
			return $queried->ID;
		}

		return 0;
	}
}
