<?php
/**
 * Tests for Focus_Position class.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Focus Position test class.
 */
class FocusPositionTest extends TestCase {

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load required classes.
		require_once dirname( __DIR__, 2 ) . '/includes/class-helper.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-ajax.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-position.php';
	}

	/**
	 * Get the add_focus_to_image method via reflection.
	 *
	 * @return \ReflectionMethod
	 */
	private function getAddFocusMethod(): \ReflectionMethod {
		$reflection = new ReflectionClass( \MWE\EtchWP_Enhancements\Focus_Position::class );
		$method     = $reflection->getMethod( 'add_focus_to_image' );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Get Focus_Position instance.
	 *
	 * @return \MWE\EtchWP_Enhancements\Focus_Position
	 */
	private function getInstance(): \MWE\EtchWP_Enhancements\Focus_Position {
		return \MWE\EtchWP_Enhancements\Focus_Position::get_instance();
	}

	/**
	 * Test that object-position is added when focus point exists.
	 */
	public function test_adds_object_position_with_focus_point(): void {
		// Mock WordPress functions.
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		// Mock global $post.
		$GLOBALS['post']     = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$matches = array(
			0 => '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">',
			1 => ' ',
			2 => 'https://example.com/wp-content/uploads/image.jpg',
			3 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertStringContainsString( 'object-position: 30% 70%', $result );
	}

	/**
	 * Test that original tag is returned when no focus point exists.
	 */
	public function test_returns_original_when_no_focus_point(): void {
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => ' ',
			2 => 'https://example.com/wp-content/uploads/image.jpg',
			3 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that 50% 50% (default center) returns original tag.
	 */
	public function test_returns_original_for_center_position(): void {
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '50% 50%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => ' ',
			2 => 'https://example.com/wp-content/uploads/image.jpg',
			3 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that existing object-position is not overwritten.
	 */
	public function test_does_not_overwrite_existing_object_position(): void {
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" style="object-position: 10% 90%" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => ' ',
			2 => 'https://example.com/wp-content/uploads/image.jpg',
			3 => ' style="object-position: 10% 90%" alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		// Should return unchanged.
		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that style attribute is appended when it exists.
	 */
	public function test_appends_to_existing_style(): void {
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$matches = array(
			0 => '<img src="https://example.com/wp-content/uploads/image.jpg" style="width: 100%">',
			1 => ' ',
			2 => 'https://example.com/wp-content/uploads/image.jpg',
			3 => ' style="width: 100%"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertStringContainsString( 'width: 100%', $result );
		$this->assertStringContainsString( 'object-position: 30% 70%', $result );
	}
}
