<?php
/**
 * Base test case for all plugin tests.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock common WordPress functions.
		$this->mockWordPressFunctions();
	}

	/**
	 * Tear down the test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Mock common WordPress functions.
	 */
	protected function mockWordPressFunctions(): void {
		// Mock sanitize functions.
		Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
		Monkey\Functions\when( 'wp_unslash' )->returnArg();
		Monkey\Functions\when( 'absint' )->alias( 'intval' );
		Monkey\Functions\when( 'esc_attr' )->returnArg();
		Monkey\Functions\when( 'esc_url_raw' )->returnArg();
	}
}
