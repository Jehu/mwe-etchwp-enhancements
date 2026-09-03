<?php
/**
 * Tests for Image_Enhancement class.
 *
 * @package MWE_EtchWP_Enhancements\Tests
 */

declare(strict_types=1);

namespace MWE\EtchWP_Enhancements\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MWE\EtchWP_Enhancements\Image_Enhancement;
use MWE\EtchWP_Enhancements\Helper;
use ReflectionClass;

/**
 * Image Enhancement test class.
 */
class ImageEnhancementTest extends TestCase {

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load required classes.
		require_once dirname( __DIR__, 2 ) . '/includes/class-helper.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-image-enhancement.php';

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
	 * Get Image_Enhancement instance.
	 *
	 * @return Image_Enhancement
	 */
	private function getInstance(): Image_Enhancement {
		return Image_Enhancement::get_instance();
	}

	/**
	 * Test singleton pattern returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {
		$instance1 = Image_Enhancement::get_instance();
		$instance2 = Image_Enhancement::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test filter_images skips non-Etch blocks.
	 */
	public function test_filter_images_skips_non_etch_blocks(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$instance = $this->getInstance();

		$content = '<img src="https://example.com/wp-content/uploads/image.jpg">';
		$block   = array( 'blockName' => 'core/paragraph' );

		$result = $instance->filter_images( $content, $block );

		$this->assertEquals( $content, $result );
	}

	/**
	 * Test filter_images processes Etch blocks.
	 */
	public function test_filter_images_processes_etch_element(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( array(
			'width'  => 1920,
			'height' => 1080,
		) );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 123 ) );
		Functions\when( 'get_post_meta' )->justReturn( 'Test Alt Text' );
		Functions\when( 'wp_get_attachment_image_srcset' )->justReturn( 'image-320.jpg 320w, image-640.jpg 640w' );
		Functions\when( 'wp_get_attachment_image_sizes' )->justReturn( '(max-width: 1920px) 100vw, 1920px' );

		$instance = $this->getInstance();

		$content = '<img src="https://example.com/wp-content/uploads/image.jpg">';
		$block   = array( 'blockName' => 'etch/element' );

		$result = $instance->filter_images( $content, $block );

		// Should add attributes.
		$this->assertStringContainsString( 'width="1920"', $result );
		$this->assertStringContainsString( 'height="1080"', $result );
		$this->assertStringContainsString( 'alt="Test Alt Text"', $result );
		$this->assertStringContainsString( 'srcset=', $result );
		$this->assertStringContainsString( 'sizes=', $result );
	}

	/**
	 * Test filter_images skips dynamic-image blocks.
	 */
	public function test_filter_images_skips_dynamic_image(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$instance = $this->getInstance();

		$content = '<img src="https://example.com/wp-content/uploads/image.jpg">';
		$block   = array( 'blockName' => 'etch/dynamic-image' );

		$result = $instance->filter_images( $content, $block );

		// Should not be processed (returns original).
		$this->assertEquals( $content, $result );
	}

	/**
	 * Test enhance_image returns original when all attributes exist.
	 */
	public function test_enhance_image_skips_when_all_attributes_exist(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$instance = $this->getInstance();

		$content = '<img src="https://example.com/wp-content/uploads/image.jpg" srcset="..." sizes="..." width="800" height="600" alt="Test">';
		$block   = array( 'blockName' => 'etch/element' );

		$result = $instance->filter_images( $content, $block );

		// Should remain unchanged.
		$this->assertEquals( $content, $result );
	}

	/**
	 * Test enhance_image extracts dimensions from filename.
	 */
	public function test_enhance_image_extracts_dimensions_from_filename(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( array(
			'width'  => 3000, // Original is larger.
			'height' => 2000,
		) );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 123 ) );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_srcset' )->justReturn( null );
		Functions\when( 'wp_get_attachment_image_sizes' )->justReturn( null );

		$instance = $this->getInstance();

		// Image with dimensions in filename.
		$content = '<img src="https://example.com/wp-content/uploads/image-1440x960.jpg">';
		$block   = array( 'blockName' => 'etch/element' );

		$result = $instance->filter_images( $content, $block );

		// Should use dimensions from filename, not metadata.
		$this->assertStringContainsString( 'width="1440"', $result );
		$this->assertStringContainsString( 'height="960"', $result );
	}

	/**
	 * Test decorative image handling (alt="-").
	 */
	public function test_enhance_image_normalizes_decorative_marker(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 123 );
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( array(
			'width'  => 800,
			'height' => 600,
		) );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 123 ) );
		Functions\when( 'get_post_meta' )->justReturn( 'Should not be used' );
		Functions\when( 'wp_get_attachment_image_srcset' )->justReturn( null );
		Functions\when( 'wp_get_attachment_image_sizes' )->justReturn( null );

		$instance = $this->getInstance();

		// Image with decorative marker.
		$content = '<img src="https://example.com/wp-content/uploads/image.jpg" alt="-">';
		$block   = array( 'blockName' => 'etch/element' );

		$result = $instance->filter_images( $content, $block );

		// Should normalize hyphen to empty alt with data attribute.
		$this->assertStringContainsString( 'alt=""', $result );
		$this->assertStringContainsString( 'data-decorative="true"', $result );
		$this->assertStringNotContainsString( 'alt="-"', $result );
	}
	/**
	 * Test init keeps core auto-sizes on and registers the per-image guard instead.
	 */
	public function test_init_registers_auto_sizes_guard_instead_of_global_disable(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Monkey\Filters\expectAdded( 'render_block' )->once();
		Monkey\Filters\expectAdded( 'wp_content_img_tag' )->once();
		Monkey\Filters\expectAdded( 'wp_img_tag_add_auto_sizes' )->never();

		$this->getInstance()->init();
	}

	/**
	 * Test the global disable can be restored via filter.
	 */
	public function test_init_can_restore_global_auto_sizes_disable_via_filter(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return 'mwe_etchwp_disable_auto_sizes' === $hook ? true : $value;
			}
		);
		Monkey\Filters\expectAdded( 'render_block' )->once();
		Monkey\Filters\expectAdded( 'wp_img_tag_add_auto_sizes' )->once()->with( '__return_false' );
		Monkey\Filters\expectAdded( 'wp_content_img_tag' )->never();

		$this->getInstance()->init();
	}

	/**
	 * Test guard_auto_sizes strips `auto` from attribute-sized (small) images.
	 */
	public function test_guard_auto_sizes_strips_auto_from_small_images(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$tag    = '<img src="https://example.com/wp-content/uploads/arrow.png" width="56" height="16" loading="lazy" sizes="auto, (max-width: 56px) 100vw, 56px">';
		$result = $this->getInstance()->guard_auto_sizes( $tag, 'the_content', 123 );

		$this->assertStringContainsString( 'sizes="(max-width: 56px) 100vw, 56px"', $result );
		$this->assertStringNotContainsString( 'auto', $result );
	}

	/**
	 * Test guard_auto_sizes keeps `auto` on content-sized images.
	 */
	public function test_guard_auto_sizes_keeps_auto_on_content_images(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$tag    = '<img src="https://example.com/wp-content/uploads/hero.jpg" width="1920" height="1080" loading="lazy" sizes="auto, (max-width: 1920px) 100vw, 1920px">';
		$result = $this->getInstance()->guard_auto_sizes( $tag, 'the_content', 123 );

		$this->assertSame( $tag, $result );
	}

	/**
	 * Test guard_auto_sizes leaves images without `auto` (or without a width attribute) untouched.
	 */
	public function test_guard_auto_sizes_ignores_images_without_auto_or_width(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$no_auto  = '<img src="https://example.com/wp-content/uploads/a.jpg" width="56" sizes="(max-width: 56px) 100vw, 56px">';
		$no_width = '<img src="https://example.com/wp-content/uploads/a.jpg" loading="lazy" sizes="auto, (max-width: 56px) 100vw, 56px">';

		$this->assertSame( $no_auto, $this->getInstance()->guard_auto_sizes( $no_auto, 'the_content', 1 ) );
		$this->assertSame( $no_width, $this->getInstance()->guard_auto_sizes( $no_width, 'the_content', 1 ) );
	}

	/**
	 * Test the threshold is filterable.
	 */
	public function test_guard_auto_sizes_respects_min_width_filter(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return 'mwe_etchwp_auto_sizes_min_width' === $hook ? 400 : $value;
			}
		);

		$tag    = '<img src="https://example.com/wp-content/uploads/card.jpg" width="300" height="200" loading="lazy" sizes="auto, (max-width: 300px) 100vw, 300px">';
		$result = $this->getInstance()->guard_auto_sizes( $tag, 'the_content', 123 );

		$this->assertStringContainsString( 'sizes="(max-width: 300px) 100vw, 300px"', $result );
	}
}
