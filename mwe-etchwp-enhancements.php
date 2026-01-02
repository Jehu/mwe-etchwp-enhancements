<?php
/**
 * MWE EtchWP Enhancements
 *
 * @package           MWE_EtchWP_Enhancements
 * @author            Marco Michely
 * @copyright         2025 Marco Michely
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MWE EtchWP Enhancements
 * Plugin URI:        https://github.com/Jehu/mwe-etchwp-enhancements
 * Description:       Enhances Etch page builder with improved image handling and focus position support for responsive images.
 * Version:           1.2.1
 * Requires at least: 5.9
 * Requires PHP:      8.1
 * Author:            Marco Michely
 * Author URI:        https://www.michelyweb.de
 * Text Domain:       mwe-etchwp-enhancements
 * Domain Path:       /languages
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants.
 */
define( 'MWE_ETCHWP_VERSION', '1.2.1' );
define( 'MWE_ETCHWP_PLUGIN_FILE', __FILE__ );
define( 'MWE_ETCHWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MWE_ETCHWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MWE_ETCHWP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		// Project-specific namespace prefix.
		$prefix = 'MWE\\EtchWP_Enhancements\\';

		// Base directory for the namespace prefix.
		$base_dir = MWE_ETCHWP_PLUGIN_DIR . 'includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators.
		$relative_class = str_replace( '\\', '/', $relative_class );

		// Convert class name to filename (Class_Name -> class-name.php).
		$filename = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		// Build the file path.
		$file = $base_dir . $filename;

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init_plugin() {
	// Load plugin textdomain for translations.
	load_plugin_textdomain(
		'mwe-etchwp-enhancements',
		false,
		dirname( MWE_ETCHWP_PLUGIN_BASENAME ) . '/languages'
	);

	// Initialize the main plugin class.
	Plugin::get_instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_plugin' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate_plugin() {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		deactivate_plugins( MWE_ETCHWP_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'MWE EtchWP Enhancements requires PHP 8.1 or higher.', 'mwe-etchwp-enhancements' ),
			esc_html__( 'Plugin Activation Error', 'mwe-etchwp-enhancements' ),
			array( 'back_link' => true )
		);
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, '5.9', '<' ) ) {
		deactivate_plugins( MWE_ETCHWP_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'MWE EtchWP Enhancements requires WordPress 5.9 or higher.', 'mwe-etchwp-enhancements' ),
			esc_html__( 'Plugin Activation Error', 'mwe-etchwp-enhancements' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
