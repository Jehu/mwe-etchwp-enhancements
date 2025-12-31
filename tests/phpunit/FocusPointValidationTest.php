<?php
/**
 * Tests for focus point validation.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Focus point validation test class.
 */
class FocusPointValidationTest extends TestCase {

	/**
	 * Get the is_valid_focus_point method via reflection.
	 *
	 * @return \ReflectionMethod
	 */
	private function getValidationMethod(): \ReflectionMethod {
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-ajax.php';

		$reflection = new ReflectionClass( \MWE\EtchWP_Enhancements\Focus_Ajax::class );
		$method     = $reflection->getMethod( 'is_valid_focus_point' );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Get Focus_Ajax instance.
	 *
	 * @return \MWE\EtchWP_Enhancements\Focus_Ajax
	 */
	private function getInstance(): \MWE\EtchWP_Enhancements\Focus_Ajax {
		return \MWE\EtchWP_Enhancements\Focus_Ajax::get_instance();
	}

	/**
	 * Test valid focus point formats.
	 *
	 * @dataProvider validFocusPointProvider
	 */
	public function test_valid_focus_points( string $focus_point ): void {
		$method   = $this->getValidationMethod();
		$instance = $this->getInstance();

		$this->assertTrue(
			$method->invoke( $instance, $focus_point ),
			"Expected '$focus_point' to be valid"
		);
	}

	/**
	 * Data provider for valid focus points.
	 *
	 * @return array
	 */
	public function validFocusPointProvider(): array {
		return array(
			'center'           => array( '50% 50%' ),
			'top-left'         => array( '0% 0%' ),
			'bottom-right'     => array( '100% 100%' ),
			'with-decimals'    => array( '33.5% 66.7%' ),
			'single-digit'     => array( '5% 5%' ),
			'mixed'            => array( '25% 75%' ),
			'zero-with-dec'    => array( '0.5% 0.5%' ),
			'high-precision'   => array( '33.333% 66.666%' ),
		);
	}

	/**
	 * Test invalid focus point formats.
	 *
	 * @dataProvider invalidFocusPointProvider
	 */
	public function test_invalid_focus_points( string $focus_point ): void {
		$method   = $this->getValidationMethod();
		$instance = $this->getInstance();

		$this->assertFalse(
			$method->invoke( $instance, $focus_point ),
			"Expected '$focus_point' to be invalid"
		);
	}

	/**
	 * Data provider for invalid focus points.
	 *
	 * @return array
	 */
	public function invalidFocusPointProvider(): array {
		return array(
			'over-100'         => array( '150% 50%' ),
			'over-100-y'       => array( '50% 150%' ),
			'both-over-100'    => array( '200% 200%' ),
			'negative'         => array( '-10% 50%' ),
			'no-percent'       => array( '50 50' ),
			'missing-y'        => array( '50%' ),
			'empty'            => array( '' ),
			'text'             => array( 'center center' ),
			'single-value'     => array( '50%' ),
			'extra-values'     => array( '50% 50% 50%' ),
			'wrong-separator'  => array( '50%,50%' ),
			'px-units'         => array( '50px 50px' ),
		);
	}

	/**
	 * Test edge case: exactly 0%.
	 */
	public function test_zero_percent_is_valid(): void {
		$method   = $this->getValidationMethod();
		$instance = $this->getInstance();

		$this->assertTrue( $method->invoke( $instance, '0% 0%' ) );
	}

	/**
	 * Test edge case: exactly 100%.
	 */
	public function test_hundred_percent_is_valid(): void {
		$method   = $this->getValidationMethod();
		$instance = $this->getInstance();

		$this->assertTrue( $method->invoke( $instance, '100% 100%' ) );
	}

	/**
	 * Test edge case: just over 100%.
	 */
	public function test_over_hundred_percent_is_invalid(): void {
		$method   = $this->getValidationMethod();
		$instance = $this->getInstance();

		$this->assertFalse( $method->invoke( $instance, '100.1% 50%' ) );
		$this->assertFalse( $method->invoke( $instance, '50% 100.1%' ) );
	}
}
