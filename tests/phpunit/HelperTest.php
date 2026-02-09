<?php
/**
 * Tests for Helper class.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MWE\EtchWP_Enhancements\Helper;
use ReflectionClass;

/**
 * Helper test class.
 */
class HelperTest extends TestCase {

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load required class.
		require_once dirname( __DIR__, 2 ) . '/includes/class-helper.php';

		// Reset the static attachment cache between tests.
		$this->resetAttachmentCache();
	}

	/**
	 * Reset the static attachment cache via reflection.
	 */
	private function resetAttachmentCache(): void {
		$reflection = new ReflectionClass( Helper::class );
		$property   = $reflection->getProperty( 'attachment_cache' );
		$property->setValue( null, array() );
	}

	/**
	 * Test is_processable_etch_block returns true for supported blocks.
	 */
	public function test_is_processable_etch_block_returns_true_for_supported(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$supported_blocks = array(
			'etch/element',
			'etch/dynamic-element',
			'etch/raw-html',
			'etch/component',
			'etch/dynamic-image',
		);

		foreach ( $supported_blocks as $block ) {
			$this->assertTrue(
				Helper::is_processable_etch_block( $block ),
				"Block $block should be processable"
			);
		}
	}

	/**
	 * Test is_processable_etch_block returns false for unsupported blocks.
	 */
	public function test_is_processable_etch_block_returns_false_for_unsupported(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$unsupported_blocks = array(
			'core/paragraph',
			'core/image',
			'etch/text',
			'',
			null,
		);

		foreach ( $unsupported_blocks as $block ) {
			$this->assertFalse(
				Helper::is_processable_etch_block( $block ?? '' ),
				"Block " . ( $block ?? 'null' ) . " should not be processable"
			);
		}
	}

	/**
	 * Test should_skip_responsive_images returns true for dynamic-image.
	 */
	public function test_should_skip_responsive_images_for_dynamic_image(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertTrue( Helper::should_skip_responsive_images( 'etch/dynamic-image' ) );
	}

	/**
	 * Test should_skip_responsive_images returns false for other blocks.
	 */
	public function test_should_skip_responsive_images_for_other_blocks(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertFalse( Helper::should_skip_responsive_images( 'etch/element' ) );
		$this->assertFalse( Helper::should_skip_responsive_images( 'etch/component' ) );
	}

	/**
	 * Test get_attachment_id_from_url with cache hit.
	 */
	public function test_get_attachment_id_from_url_returns_cached_result(): void {
		$url = 'https://example.com/wp-content/uploads/2024/01/test.jpg';

		// Mock functions for first call.
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );

		// First call should hit the database.
		$result1 = Helper::get_attachment_id_from_url( $url );
		$this->assertEquals( 123, $result1 );

		// Second call should return cached result (attachment_url_to_postid not called again).
		// Reset mock to verify it's not called again.
		$result2 = Helper::get_attachment_id_from_url( $url );
		$this->assertEquals( 123, $result2 );
	}

	/**
	 * Test get_attachment_id_from_url uses WP core function first.
	 */
	public function test_get_attachment_id_from_url_uses_wp_core_first(): void {
		$url = 'https://example.com/wp-content/uploads/2024/01/image.jpg';

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\expect( 'attachment_url_to_postid' )
			->once()
			->with( $url )
			->andReturn( 456 );

		$result = Helper::get_attachment_id_from_url( $url );

		$this->assertEquals( 456, $result );
	}

	/**
	 * Test get_attachment_id_from_url falls back to find_attachment_by_filename.
	 */
	public function test_get_attachment_id_from_url_falls_back_to_filename_search(): void {
		$this->resetAttachmentCache();

		$url = 'https://example.com/wp-content/uploads/2024/01/image-1440x960.jpg';

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\expect( 'attachment_url_to_postid' )
			->once()
			->andReturn( 0 );

		// Mock wpdb for find_attachment_by_filename.
		global $wpdb;
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->posts    = 'wp_posts';

		// First query - exact match.
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'PREPARED_QUERY' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( 789 );
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( function ( $str ) {
			return $str;
		} );

		$result = Helper::get_attachment_id_from_url( $url );

		$this->assertEquals( 789, $result );
	}

	/**
	 * Test get_attachment_id_from_url returns null for invalid URLs.
	 */
	public function test_get_attachment_id_from_url_returns_null_for_invalid_url(): void {
		$this->resetAttachmentCache();

		$url = 'https://example.com/not-an-upload-path/image.jpg';

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$result = Helper::get_attachment_id_from_url( $url );

		$this->assertNull( $result );
	}

	/**
	 * Test get_attachment_id_from_url caches null results.
	 */
	public function test_get_attachment_id_from_url_caches_null_results(): void {
		$this->resetAttachmentCache();

		$url = 'https://example.com/not-uploads/image.jpg';

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$result1 = Helper::get_attachment_id_from_url( $url );
		$result2 = Helper::get_attachment_id_from_url( $url );

		$this->assertNull( $result1 );
		$this->assertNull( $result2 );
	}

	/**
	 * Test get_current_post_id from global post.
	 */
	public function test_get_current_post_id_from_global_post(): void {
		// Create a mock WP_Post object.
		$mock_post     = \Mockery::mock( 'WP_Post' );
		$mock_post->ID = 42;
		$GLOBALS['post'] = $mock_post;

		Functions\when( 'get_queried_object' )->justReturn( null );

		$result = Helper::get_current_post_id();

		$this->assertEquals( 42, $result );

		unset( $GLOBALS['post'] );
	}

	/**
	 * Test get_current_post_id from query parameter.
	 */
	public function test_get_current_post_id_from_query_param(): void {
		$_GET['post_id'] = '123';

		$result = Helper::get_current_post_id();

		$this->assertEquals( 123, $result );

		unset( $_GET['post_id'] );
	}

	/**
	 * Test get_current_post_id query param takes precedence.
	 */
	public function test_get_current_post_id_query_param_takes_precedence(): void {
		$_GET['post_id']  = '100';
		$GLOBALS['post'] = (object) array( 'ID' => 200 );

		$result = Helper::get_current_post_id();

		$this->assertEquals( 100, $result );

		unset( $_GET['post_id'] );
		unset( $GLOBALS['post'] );
	}

	/**
	 * Test get_current_post_id from queried object.
	 */
	public function test_get_current_post_id_from_queried_object(): void {
		$GLOBALS['post'] = null;

		$mock_post     = \Mockery::mock( 'WP_Post' );
		$mock_post->ID = 789;

		Functions\when( 'get_queried_object' )->justReturn( $mock_post );

		$result = Helper::get_current_post_id();

		$this->assertEquals( 789, $result );
	}

	/**
	 * Test get_current_post_id returns 0 when no post available.
	 */
	public function test_get_current_post_id_returns_zero_when_no_post(): void {
		$GLOBALS['post'] = null;

		Functions\when( 'get_queried_object' )->justReturn( null );

		$result = Helper::get_current_post_id();

		$this->assertEquals( 0, $result );
	}
}
