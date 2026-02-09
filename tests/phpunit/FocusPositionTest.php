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
		return $reflection->getMethod( 'add_focus_to_image' );
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
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		// Mock global $post.
		$GLOBALS['post']     = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		// Regex pattern: /<(img|etch:img)([^>]+)src=["\']([^"\']*wp-content\/uploads[^"\']*)["\']([^>]*)>/i
		// Matches: [0]=full tag, [1]=tag name, [2]=attrs before src, [3]=src URL, [4]=attrs after src
		$matches = array(
			0 => '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">',
			1 => 'img',
			2 => ' ',
			3 => 'https://example.com/wp-content/uploads/image.jpg',
			4 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertStringContainsString( 'object-position: 30% 70%', $result );
	}

	/**
	 * Test that original tag is returned when no focus point exists.
	 */
	public function test_returns_original_when_no_focus_point(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => 'img',
			2 => ' ',
			3 => 'https://example.com/wp-content/uploads/image.jpg',
			4 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that 50% 50% (default center) returns original tag.
	 */
	public function test_returns_original_for_center_position(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '50% 50%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => 'img',
			2 => ' ',
			3 => 'https://example.com/wp-content/uploads/image.jpg',
			4 => ' alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that existing object-position is not overwritten.
	 */
	public function test_does_not_overwrite_existing_object_position(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$original = '<img src="https://example.com/wp-content/uploads/image.jpg" style="object-position: 10% 90%" alt="Test">';
		$matches  = array(
			0 => $original,
			1 => 'img',
			2 => ' ',
			3 => 'https://example.com/wp-content/uploads/image.jpg',
			4 => ' style="object-position: 10% 90%" alt="Test"',
		);

		$result = $method->invoke( $instance, $matches );

		// Should return unchanged.
		$this->assertEquals( $original, $result );
	}

	/**
	 * Test that style attribute is appended when it exists.
	 */
	public function test_appends_to_existing_style(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'get_post_meta' )->justReturn( '30% 70%' );
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$matches = array(
			0 => '<img src="https://example.com/wp-content/uploads/image.jpg" style="width: 100%">',
			1 => 'img',
			2 => ' ',
			3 => 'https://example.com/wp-content/uploads/image.jpg',
			4 => ' style="width: 100%"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertStringContainsString( 'width: 100%', $result );
		$this->assertStringContainsString( 'object-position: 30% 70%', $result );
	}

	/**
	 * Test that external URLs with per-page override get focus point applied.
	 */
	public function test_external_url_with_override_gets_focus_point(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 0 ); // Not found in WP.

		// Mock post meta to return override for this external URL.
		$external_url = 'https://external-cdn.com/images/hero.jpg';
		$url_key      = 'url_' . md5( $external_url );

		// Simulate per-page override stored in post meta.
		Functions\when( 'get_post_meta' )->justReturn( array( $url_key => '25% 75%' ) );

		// Mock WP_Post for get_current_post_id.
		$mock_post     = \Mockery::mock( 'WP_Post' );
		$mock_post->ID = 1;
		$GLOBALS['post'] = $mock_post;

		Functions\when( 'get_queried_object' )->justReturn( null );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$matches = array(
			0 => '<img src="' . $external_url . '" alt="External">',
			1 => 'img',
			2 => ' ',
			3 => $external_url,
			4 => ' alt="External"',
		);

		$result = $method->invoke( $instance, $matches );

		$this->assertStringContainsString( 'object-position: 25% 75%', $result );
	}

	/**
	 * Test that external URLs without override return original tag.
	 */
	public function test_external_url_without_override_returns_original(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 0 );
		Functions\when( 'get_post_meta' )->justReturn( array() ); // No overrides.
		Functions\when( 'get_queried_object' )->justReturn( null );

		$GLOBALS['post'] = (object) array( 'ID' => 1 );

		$method   = $this->getAddFocusMethod();
		$instance = $this->getInstance();

		$external_url = 'https://external-cdn.com/images/photo.jpg';
		$original     = '<img src="' . $external_url . '" alt="External">';
		$matches      = array(
			0 => $original,
			1 => 'img',
			2 => ' ',
			3 => $external_url,
			4 => ' alt="External"',
		);

		$result = $method->invoke( $instance, $matches );

		// Should return unchanged (no override, no attachment).
		$this->assertEquals( $original, $result );
	}
}
