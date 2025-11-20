<?php
/**
 * Main Plugin Class
 *
 * Handles plugin initialization and dependency management.
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
 * Main plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Image Enhancement instance.
	 *
	 * @since 1.0.0
	 * @var Image_Enhancement|null
	 */
	private $image_enhancement = null;

	/**
	 * Focus Position instance.
	 *
	 * @since 1.0.0
	 * @var Focus_Position|null
	 */
	private $focus_position = null;

	/**
	 * Whether Etch plugin is active.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $etch_active = false;

	/**
	 * Main Plugin Instance.
	 *
	 * Ensures only one instance of Plugin is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @return Plugin Main instance.
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
	 * Initialize the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init() {
		// Check dependencies.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Initialize features.
		$this->init_features();
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @since  1.0.0
	 * @return bool True if all required dependencies are met.
	 */
	private function check_dependencies() {
		// Check if Etch is active.
		$this->etch_active = $this->is_etch_active();

		if ( ! $this->etch_active ) {
			add_action( 'admin_notices', array( $this, 'etch_missing_notice' ) );
			return false;
		}

		// Check if any focus position plugin is active (optional).
		if ( ! $this->is_any_focus_plugin_active() ) {
			add_action( 'admin_notices', array( $this, 'focus_plugin_info_notice' ) );
		}

		return true;
	}

	/**
	 * Initialize plugin features.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_features() {
		// Initialize Image Enhancement.
		if ( $this->is_image_enhancement_enabled() ) {
			$this->image_enhancement = Image_Enhancement::get_instance();
			$this->image_enhancement->init();
		}

		// Initialize Focus Position (only if plugin is available).
		if ( $this->is_focus_position_enabled() && $this->is_any_focus_plugin_active() ) {
			$this->focus_position = Focus_Position::get_instance();
			$this->focus_position->init();
		}
	}

	/**
	 * Check if Etch plugin is active.
	 *
	 * @since  1.0.0
	 * @return bool True if Etch is active.
	 */
	private function is_etch_active() {
		// Check if Etch class exists as indicator.
		if ( class_exists( 'Etch\\Plugin' ) ) {
			return true;
		}

		// Check using WordPress plugin API.
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'etch/etch.php' );
	}

	/**
	 * Check if any focus position plugin is active.
	 *
	 * @since  1.0.0
	 * @return bool True if at least one focus position plugin is active.
	 */
	private function is_any_focus_plugin_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Check for Image Background Focus Position plugin.
		$ibfp_active = is_plugin_active( 'image-background-focus-position/image-background-focus-position.php' );

		// Check for Media Focus Point plugin.
		$mfp_active = is_plugin_active( 'media-focus-point/media-focus-point.php' );

		return $ibfp_active || $mfp_active;
	}

	/**
	 * Check if Image Enhancement feature is enabled.
	 *
	 * @since  1.0.0
	 * @return bool True if enabled.
	 */
	private function is_image_enhancement_enabled() {
		// Check constant (backward compatibility).
		if ( defined( 'MWE_ETCHWP_IMAGE_ENHANCEMENT' ) ) {
			return (bool) MWE_ETCHWP_IMAGE_ENHANCEMENT;
		}

		/**
		 * Filter to enable/disable image enhancement feature.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether image enhancement is enabled. Default true.
		 */
		return apply_filters( 'mwe_etchwp_enable_image_enhancement', true );
	}

	/**
	 * Check if Focus Position feature is enabled.
	 *
	 * @since  1.0.0
	 * @return bool True if enabled.
	 */
	private function is_focus_position_enabled() {
		// Check constant (backward compatibility).
		if ( defined( 'MWE_ETCHWP_FOCUS_POSITION' ) ) {
			return (bool) MWE_ETCHWP_FOCUS_POSITION;
		}

		/**
		 * Filter to enable/disable focus position feature.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether focus position is enabled. Default true.
		 */
		return apply_filters( 'mwe_etchwp_enable_focus_position', true );
	}

	/**
	 * Display admin notice when Etch is not active.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function etch_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'MWE EtchWP Enhancements', 'mwe-etchwp-enhancements' ); ?></strong>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Link to Etch plugin website */
						__( 'requires the <a href="%s" target="_blank">Etch</a> plugin to be installed and activated.', 'mwe-etchwp-enhancements' ),
						'https://etchwp.com'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice when focus position plugin is not active.
	 *
	 * This is an informational notice, not an error.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function focus_plugin_info_notice() {
		// Only show on plugins page.
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'MWE EtchWP Enhancements', 'mwe-etchwp-enhancements' ); ?></strong>
				<?php
				esc_html_e(
					'To use the focus position feature, please install one of the following plugins:',
					'mwe-etchwp-enhancements'
				);
				?>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>
					<a href="https://www.wordpress-focalpoint.com/" target="_blank">
						<?php esc_html_e( 'Image Background Focus Position', 'mwe-etchwp-enhancements' ); ?>
					</a>
				</li>
				<li>
					<a href="https://wordpress.org/plugins/media-focus-point/" target="_blank">
						<?php esc_html_e( 'Media Focus Point', 'mwe-etchwp-enhancements' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<?php
	}
}
