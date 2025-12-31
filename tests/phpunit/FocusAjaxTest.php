<?php
/**
 * Tests for Focus_Ajax class.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Focus Ajax test class.
 */
class FocusAjaxTest extends TestCase {

	/**
	 * Test that generate_url_key returns correct MD5 hash format.
	 */
	public function test_generate_url_key_returns_md5_hash(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-ajax.php';

		$url = 'https://example.com/image.jpg';
		$key = \MWE\EtchWP_Enhancements\Focus_Ajax::generate_url_key( $url );

		$this->assertStringStartsWith( 'url_', $key );
		$this->assertEquals( 'url_' . md5( $url ), $key );
	}

	/**
	 * Test that generate_url_key produces consistent results.
	 */
	public function test_generate_url_key_is_consistent(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-ajax.php';

		$url  = 'https://example.com/test-image.jpg';
		$key1 = \MWE\EtchWP_Enhancements\Focus_Ajax::generate_url_key( $url );
		$key2 = \MWE\EtchWP_Enhancements\Focus_Ajax::generate_url_key( $url );

		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test that generate_url_key produces different results for different URLs.
	 */
	public function test_generate_url_key_differs_for_different_urls(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-focus-ajax.php';

		$key1 = \MWE\EtchWP_Enhancements\Focus_Ajax::generate_url_key( 'https://example.com/image1.jpg' );
		$key2 = \MWE\EtchWP_Enhancements\Focus_Ajax::generate_url_key( 'https://example.com/image2.jpg' );

		$this->assertNotEquals( $key1, $key2 );
	}
}
