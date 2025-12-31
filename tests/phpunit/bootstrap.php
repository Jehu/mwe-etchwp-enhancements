<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package MWE_EtchWP_Enhancements
 */

declare(strict_types=1);

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

// Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Brain Monkey setup.
require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';
