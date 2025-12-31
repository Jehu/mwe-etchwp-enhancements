<?php
/**
 * GitHub Updater Class
 *
 * Handles automatic updates from GitHub releases.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @link       https://www.michelyweb.de
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the plugin update checker library.
require_once MWE_ETCHWP_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * GitHub Updater class.
 *
 * Integrates with GitHub releases to provide automatic plugin updates.
 *
 * @since 1.2.0
 */
class GitHub_Updater {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.2.0
	 * @var GitHub_Updater|null
	 */
	private static $instance = null;

	/**
	 * The update checker instance.
	 *
	 * @since 1.2.0
	 * @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|null
	 */
	private $update_checker = null;

	/**
	 * GitHub repository URL.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const GITHUB_REPO = 'https://github.com/Jehu/mwe-etchwp-enhancements';

	/**
	 * Main GitHub_Updater Instance.
	 *
	 * Ensures only one instance of GitHub_Updater is loaded or can be loaded.
	 *
	 * @since  1.2.0
	 * @return GitHub_Updater Main instance.
	 */
	public static function get_instance(): GitHub_Updater {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.
	}

	/**
	 * Initialize the updater.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function init(): void {
		$this->setup_update_checker();
	}

	/**
	 * Setup the plugin update checker.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	private function setup_update_checker(): void {
		$this->update_checker = PucFactory::buildUpdateChecker(
			self::GITHUB_REPO,
			MWE_ETCHWP_PLUGIN_FILE,
			'mwe-etchwp-enhancements'
		);

		// Set the branch that contains the stable release.
		$this->update_checker->setBranch( 'main' );

		// Use GitHub releases for updates.
		$this->update_checker->getVcsApi()->enableReleaseAssets();
	}

	/**
	 * Get the update checker instance.
	 *
	 * Useful for debugging or extending functionality.
	 *
	 * @since  1.2.0
	 * @return \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|null
	 */
	public function get_update_checker() {
		return $this->update_checker;
	}
}
