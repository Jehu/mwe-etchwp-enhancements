# Focus Point Override Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable per-page focus point overrides for images in Etch editor with a visual UI, plus expose focus points in Etch's Dynamic Data system.

**Architecture:** Three-layer approach: (1) Dynamic Data filter exposes focus points to Etch's template system, (2) AJAX endpoints manage per-page overrides stored in post meta, (3) JavaScript UI injected into Etch's canvas provides visual focus point selection. The existing `render_block` filter is extended to respect overrides.

**Tech Stack:** PHP 8.1+, WordPress REST/AJAX API, Vanilla JavaScript (no build step), CSS3

**Reference Issue:** https://github.com/Jehu/mwe-etchwp-enhancements/issues/1

---

## Task 1: Create Focus_Dynamic_Data Class

**Files:**
- Create: `includes/class-focus-dynamic-data.php`

**Step 1: Create the class file with filter hook**

```php
<?php
/**
 * Focus Dynamic Data Class
 *
 * Exposes focus point data in Etch's Dynamic Data system.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focus Dynamic Data class.
 *
 * Adds focus point information to Etch's dynamic data system,
 * making it available as {this.image.focusPoint} in templates.
 *
 * @since 1.1.0
 */
class Focus_Dynamic_Data {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Dynamic_Data|null
	 */
	private static ?Focus_Dynamic_Data $instance = null;

	/**
	 * Main Focus_Dynamic_Data Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Dynamic_Data Main instance.
	 */
	public static function get_instance(): Focus_Dynamic_Data {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.
	}

	/**
	 * Initialize the dynamic data hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		add_filter( 'etch/dynamic_data/post', array( $this, 'add_focus_point_to_post_data' ), 10, 2 );
	}

	/**
	 * Add focus point data to Etch's post dynamic data.
	 *
	 * @since  1.1.0
	 * @param  array $data    The dynamic data array.
	 * @param  int   $post_id The post ID.
	 * @return array          The modified data array.
	 */
	public function add_focus_point_to_post_data( array $data, int $post_id ): array {
		// Add focus point to featured image if present.
		if ( isset( $data['image'] ) && is_array( $data['image'] ) ) {
			$attachment_id = $data['image']['id'] ?? 0;
			if ( $attachment_id ) {
				$data['image']['focusPoint'] = $this->get_focus_point( (int) $attachment_id );
			}
		}

		// Also add to thumbnail for backward compatibility.
		if ( ! empty( $data['thumbnail'] ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$data['thumbnailFocusPoint'] = $this->get_focus_point( (int) $thumbnail_id );
			}
		}

		return $data;
	}

	/**
	 * Get focus point for an attachment.
	 *
	 * @since  1.1.0
	 * @param  int $attachment_id The attachment ID.
	 * @return string             The focus point value (e.g., "30% 70%").
	 */
	public function get_focus_point( int $attachment_id ): string {
		$desktop = get_post_meta( $attachment_id, 'bg_pos_desktop', true );

		if ( $desktop && is_string( $desktop ) ) {
			return $desktop;
		}

		return '50% 50%';
	}
}
```

**Step 2: Commit**

```bash
git add includes/class-focus-dynamic-data.php
git commit -m "feat: add Focus_Dynamic_Data class for Etch dynamic data integration"
```

---

## Task 2: Create Focus_Ajax Class

**Files:**
- Create: `includes/class-focus-ajax.php`

**Step 1: Create the AJAX handler class**

```php
<?php
/**
 * Focus Ajax Class
 *
 * Handles AJAX requests for focus point overrides.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focus Ajax class.
 *
 * Provides AJAX endpoints for saving and retrieving
 * per-page focus point overrides.
 *
 * @since 1.1.0
 */
class Focus_Ajax {

	/**
	 * Post meta key for storing focus overrides.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const META_KEY = '_mwe_etchwp_enhancements_focus_overrides';

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Ajax|null
	 */
	private static ?Focus_Ajax $instance = null;

	/**
	 * Main Focus_Ajax Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Ajax Main instance.
	 */
	public static function get_instance(): Focus_Ajax {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Initialize AJAX hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_mwe_save_focus_override', array( $this, 'save_focus_override' ) );
		add_action( 'wp_ajax_mwe_get_focus_override', array( $this, 'get_focus_override' ) );
		add_action( 'wp_ajax_mwe_delete_focus_override', array( $this, 'delete_focus_override' ) );
		add_action( 'wp_ajax_mwe_get_all_focus_overrides', array( $this, 'get_all_focus_overrides' ) );
	}

	/**
	 * Save a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function save_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		// Get and validate parameters.
		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_key  = isset( $_POST['image_key'] ) ? sanitize_text_field( wp_unslash( $_POST['image_key'] ) ) : '';
		$focus_point = isset( $_POST['focus_point'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_point'] ) ) : '';

		if ( ! $post_id || ! $image_key || ! $focus_point ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		// Validate focus point format (e.g., "30% 70%").
		if ( ! $this->is_valid_focus_point( $focus_point ) ) {
			wp_send_json_error( array( 'message' => 'Invalid focus point format' ), 400 );
		}

		// Get existing overrides.
		$overrides = $this->get_overrides_for_post( $post_id );

		// Update or add the override.
		$overrides[ $image_key ] = $focus_point;

		// Save to post meta.
		update_post_meta( $post_id, self::META_KEY, $overrides );

		wp_send_json_success(
			array(
				'message'     => 'Focus point saved',
				'image_key'   => $image_key,
				'focus_point' => $focus_point,
			)
		);
	}

	/**
	 * Get a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function get_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$image_key = isset( $_GET['image_key'] ) ? sanitize_text_field( wp_unslash( $_GET['image_key'] ) ) : '';

		if ( ! $post_id || ! $image_key ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		$overrides   = $this->get_overrides_for_post( $post_id );
		$focus_point = $overrides[ $image_key ] ?? null;

		wp_send_json_success(
			array(
				'image_key'   => $image_key,
				'focus_point' => $focus_point,
				'has_override' => null !== $focus_point,
			)
		);
	}

	/**
	 * Delete a focus point override.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function delete_focus_override(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$image_key = isset( $_POST['image_key'] ) ? sanitize_text_field( wp_unslash( $_POST['image_key'] ) ) : '';

		if ( ! $post_id || ! $image_key ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
		}

		$overrides = $this->get_overrides_for_post( $post_id );

		if ( isset( $overrides[ $image_key ] ) ) {
			unset( $overrides[ $image_key ] );
			update_post_meta( $post_id, self::META_KEY, $overrides );
		}

		wp_send_json_success( array( 'message' => 'Override deleted' ) );
	}

	/**
	 * Get all focus point overrides for a post.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function get_all_focus_overrides(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mwe_focus_point_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Missing post_id' ), 400 );
		}

		$overrides = $this->get_overrides_for_post( $post_id );

		wp_send_json_success( array( 'overrides' => $overrides ) );
	}

	/**
	 * Get all overrides for a post.
	 *
	 * @since  1.1.0
	 * @param  int $post_id The post ID.
	 * @return array        The overrides array.
	 */
	public function get_overrides_for_post( int $post_id ): array {
		$overrides = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Get override for a specific image.
	 *
	 * @since  1.1.0
	 * @param  int    $post_id   The post ID.
	 * @param  string $image_key The image key (attachment ID or URL hash).
	 * @return string|null       The focus point override or null.
	 */
	public function get_override( int $post_id, string $image_key ): ?string {
		$overrides = $this->get_overrides_for_post( $post_id );
		return $overrides[ $image_key ] ?? null;
	}

	/**
	 * Generate image key from URL (for external images).
	 *
	 * @since  1.1.0
	 * @param  string $url The image URL.
	 * @return string      The image key (prefixed MD5 hash).
	 */
	public static function generate_url_key( string $url ): string {
		return 'url_' . md5( $url );
	}

	/**
	 * Validate focus point format.
	 *
	 * @since  1.1.0
	 * @param  string $focus_point The focus point string.
	 * @return bool                True if valid.
	 */
	private function is_valid_focus_point( string $focus_point ): bool {
		// Pattern: "XX% YY%" where XX and YY are 0-100.
		return (bool) preg_match( '/^(\d{1,3}(\.\d+)?%)\s+(\d{1,3}(\.\d+)?%)$/', $focus_point );
	}
}
```

**Step 2: Commit**

```bash
git add includes/class-focus-ajax.php
git commit -m "feat: add Focus_Ajax class for saving/retrieving focus point overrides"
```

---

## Task 3: Create Focus_Editor_UI Class

**Files:**
- Create: `includes/class-focus-editor-ui.php`

**Step 1: Create the editor UI class**

```php
<?php
/**
 * Focus Editor UI Class
 *
 * Injects focus point UI into Etch's canvas editor.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focus Editor UI class.
 *
 * Handles the injection of focus point UI into Etch's editor canvas.
 *
 * @since 1.1.0
 */
class Focus_Editor_UI {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.0
	 * @var Focus_Editor_UI|null
	 */
	private static ?Focus_Editor_UI $instance = null;

	/**
	 * Main Focus_Editor_UI Instance.
	 *
	 * @since  1.1.0
	 * @return Focus_Editor_UI Main instance.
	 */
	public static function get_instance(): Focus_Editor_UI {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Initialize editor UI hooks.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function init(): void {
		// Enqueue assets in Etch canvas.
		add_action( 'etch/canvas/enqueue_assets', array( $this, 'enqueue_canvas_assets' ) );

		// Also enqueue on frontend when Etch builder is active.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_builder_assets' ) );
	}

	/**
	 * Enqueue assets for Etch canvas.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function enqueue_canvas_assets(): void {
		$this->enqueue_assets();
	}

	/**
	 * Maybe enqueue assets when Etch builder is active.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public function maybe_enqueue_builder_assets(): void {
		// Check if we're in Etch builder mode.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['etch'] ) || 'magic' !== $_GET['etch'] ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Enqueue focus point editor assets.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	private function enqueue_assets(): void {
		$version = defined( 'MWE_ETCHWP_VERSION' ) ? MWE_ETCHWP_VERSION : '1.1.0';

		// Enqueue CSS.
		wp_enqueue_style(
			'mwe-focus-point-editor',
			MWE_ETCHWP_PLUGIN_URL . 'assets/css/focus-point-editor.css',
			array(),
			$version
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'mwe-focus-point-editor',
			MWE_ETCHWP_PLUGIN_URL . 'assets/js/focus-point-editor.js',
			array(),
			$version,
			true
		);

		// Get current post ID.
		$post_id = $this->get_current_post_id();

		// Localize script with necessary data.
		wp_localize_script(
			'mwe-focus-point-editor',
			'mweFocusPointEditor',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mwe_focus_point_nonce' ),
				'postId'    => $post_id,
				'i18n'      => array(
					'focusPoint'     => __( 'Focus Point', 'mwe-etchwp-enhancements' ),
					'clickToSet'     => __( 'Click on image to set focus point', 'mwe-etchwp-enhancements' ),
					'reset'          => __( 'Reset', 'mwe-etchwp-enhancements' ),
					'useGlobal'      => __( 'Use Global', 'mwe-etchwp-enhancements' ),
					'saving'         => __( 'Saving...', 'mwe-etchwp-enhancements' ),
					'saved'          => __( 'Saved', 'mwe-etchwp-enhancements' ),
					'error'          => __( 'Error saving', 'mwe-etchwp-enhancements' ),
					'override'       => __( 'Override', 'mwe-etchwp-enhancements' ),
					'globalValue'    => __( 'Global', 'mwe-etchwp-enhancements' ),
				),
			)
		);
	}

	/**
	 * Get the current post ID from various sources.
	 *
	 * @since  1.1.0
	 * @return int The post ID or 0.
	 */
	private function get_current_post_id(): int {
		// Try from query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( $_GET['post_id'] );
		}

		// Try from global post.
		global $post;
		if ( $post instanceof \WP_Post ) {
			return $post->ID;
		}

		// Try from queried object.
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post ) {
			return $queried->ID;
		}

		return 0;
	}
}
```

**Step 2: Commit**

```bash
git add includes/class-focus-editor-ui.php
git commit -m "feat: add Focus_Editor_UI class for Etch canvas asset loading"
```

---

## Task 4: Create JavaScript Focus Point Editor

**Files:**
- Create: `assets/js/focus-point-editor.js`

**Step 1: Create the JavaScript file**

```javascript
/**
 * Focus Point Editor for Etch
 *
 * Injects a visual focus point selector into Etch's element settings panel.
 *
 * @package MWE_EtchWP_Enhancements
 * @since   1.1.0
 */

(function() {
	'use strict';

	// Ensure config is available.
	if (typeof mweFocusPointEditor === 'undefined') {
		console.warn('MWE Focus Point Editor: Configuration not found');
		return;
	}

	const config = mweFocusPointEditor;
	const { ajaxUrl, nonce, postId, i18n } = config;

	// Cache for focus point overrides.
	let overridesCache = {};

	/**
	 * Initialize the focus point editor.
	 */
	function init() {
		// Load existing overrides.
		loadOverrides();

		// Watch for panel changes.
		observePanelChanges();

		// Listen for image selection in canvas.
		observeCanvasSelection();
	}

	/**
	 * Load all focus point overrides for current post.
	 */
	async function loadOverrides() {
		if (!postId) return;

		try {
			const response = await fetch(
				`${ajaxUrl}?action=mwe_get_all_focus_overrides&post_id=${postId}&nonce=${nonce}`
			);
			const data = await response.json();

			if (data.success && data.data.overrides) {
				overridesCache = data.data.overrides;
			}
		} catch (error) {
			console.error('MWE Focus Point: Failed to load overrides', error);
		}
	}

	/**
	 * Observe changes to the Etch settings panel.
	 */
	function observePanelChanges() {
		const observer = new MutationObserver((mutations) => {
			for (const mutation of mutations) {
				if (mutation.type === 'childList') {
					checkForImagePanel();
				}
			}
		});

		// Start observing the document body.
		observer.observe(document.body, {
			childList: true,
			subtree: true
		});

		// Initial check.
		setTimeout(checkForImagePanel, 500);
	}

	/**
	 * Observe canvas for image selection.
	 */
	function observeCanvasSelection() {
		document.addEventListener('click', (e) => {
			const img = e.target.closest('img');
			if (img && isInEtchCanvas(img)) {
				setTimeout(checkForImagePanel, 100);
			}
		});
	}

	/**
	 * Check if element is within Etch canvas.
	 */
	function isInEtchCanvas(element) {
		return element.closest('[data-etch-canvas]') !== null ||
			   element.closest('.etch-canvas') !== null ||
			   document.querySelector('[data-etch-builder]') !== null;
	}

	/**
	 * Check if an image settings panel is open and inject our UI.
	 */
	function checkForImagePanel() {
		// Look for Etch's element settings panel.
		// These selectors may need adjustment based on Etch's actual DOM structure.
		const panelSelectors = [
			'[data-panel="element-settings"]',
			'.etch-element-settings',
			'.etch-inspector-panel',
			'[class*="element-settings"]',
			'[class*="ElementSettings"]'
		];

		let panel = null;
		for (const selector of panelSelectors) {
			panel = document.querySelector(selector);
			if (panel) break;
		}

		if (!panel) return;

		// Check if this is for an image element.
		const isImagePanel = checkIfImagePanel(panel);
		if (!isImagePanel) return;

		// Check if we already injected our UI.
		if (panel.querySelector('.mwe-focus-point-container')) return;

		// Get the currently selected image.
		const selectedImage = getSelectedImage();
		if (!selectedImage) return;

		// Inject our focus point UI.
		injectFocusPointUI(panel, selectedImage);
	}

	/**
	 * Check if the panel is for an image element.
	 */
	function checkIfImagePanel(panel) {
		// Look for indicators that this is an image panel.
		const panelText = panel.textContent.toLowerCase();
		const hasImageIndicator = panelText.includes('img') ||
								  panelText.includes('image') ||
								  panel.querySelector('[data-element-type="img"]') !== null;

		// Also check panel header/title.
		const header = panel.querySelector('h2, h3, [class*="header"], [class*="title"]');
		if (header) {
			const headerText = header.textContent.toLowerCase();
			if (headerText.includes('img') || headerText.includes('image')) {
				return true;
			}
		}

		return hasImageIndicator;
	}

	/**
	 * Get the currently selected image in the canvas.
	 */
	function getSelectedImage() {
		// Look for selected/active image in canvas.
		const selectors = [
			'.etch-selected img',
			'[data-etch-selected] img',
			'.etch-canvas img.selected',
			'img[data-etch-selected]',
			'.etch-element--selected img'
		];

		for (const selector of selectors) {
			const img = document.querySelector(selector);
			if (img) return img;
		}

		// Fallback: find any focused/active image.
		const activeElement = document.activeElement;
		if (activeElement && activeElement.tagName === 'IMG') {
			return activeElement;
		}

		return null;
	}

	/**
	 * Inject the focus point UI into the panel.
	 */
	function injectFocusPointUI(panel, image) {
		const imageKey = getImageKey(image);
		const currentOverride = overridesCache[imageKey] || null;
		const globalValue = getGlobalFocusPoint(image);

		// Create container.
		const container = document.createElement('div');
		container.className = 'mwe-focus-point-container';

		// Create header.
		const header = document.createElement('div');
		header.className = 'mwe-focus-point-header';
		header.innerHTML = `
			<span class="mwe-focus-point-title">${i18n.focusPoint}</span>
			<span class="mwe-focus-point-status"></span>
		`;

		// Create preview area.
		const preview = document.createElement('div');
		preview.className = 'mwe-focus-point-preview';

		const previewImage = document.createElement('img');
		previewImage.src = image.src;
		previewImage.className = 'mwe-focus-point-preview-image';

		const marker = document.createElement('div');
		marker.className = 'mwe-focus-point-marker';

		preview.appendChild(previewImage);
		preview.appendChild(marker);

		// Create info area.
		const info = document.createElement('div');
		info.className = 'mwe-focus-point-info';

		const positionDisplay = document.createElement('span');
		positionDisplay.className = 'mwe-focus-point-position';
		positionDisplay.textContent = currentOverride || globalValue || '50% 50%';

		const typeLabel = document.createElement('span');
		typeLabel.className = 'mwe-focus-point-type';
		typeLabel.textContent = currentOverride ? i18n.override : i18n.globalValue;

		info.appendChild(positionDisplay);
		info.appendChild(typeLabel);

		// Create actions.
		const actions = document.createElement('div');
		actions.className = 'mwe-focus-point-actions';

		const resetButton = document.createElement('button');
		resetButton.type = 'button';
		resetButton.className = 'mwe-focus-point-button mwe-focus-point-reset';
		resetButton.textContent = i18n.useGlobal;
		resetButton.disabled = !currentOverride;

		actions.appendChild(resetButton);

		// Assemble container.
		container.appendChild(header);
		container.appendChild(preview);
		container.appendChild(info);
		container.appendChild(actions);

		// Find insertion point in panel.
		const insertionPoint = findInsertionPoint(panel);
		if (insertionPoint) {
			insertionPoint.parentNode.insertBefore(container, insertionPoint.nextSibling);
		} else {
			panel.appendChild(container);
		}

		// Set initial marker position.
		const position = parsePosition(currentOverride || globalValue || '50% 50%');
		updateMarkerPosition(marker, position);

		// Add event listeners.
		preview.addEventListener('click', (e) => {
			const rect = preview.getBoundingClientRect();
			const x = ((e.clientX - rect.left) / rect.width * 100).toFixed(1);
			const y = ((e.clientY - rect.top) / rect.height * 100).toFixed(1);

			const newPosition = `${x}% ${y}%`;
			updateMarkerPosition(marker, { x: parseFloat(x), y: parseFloat(y) });
			positionDisplay.textContent = newPosition;
			typeLabel.textContent = i18n.override;
			resetButton.disabled = false;

			saveFocusPoint(imageKey, newPosition, header.querySelector('.mwe-focus-point-status'));
		});

		resetButton.addEventListener('click', () => {
			deleteFocusPoint(imageKey, header.querySelector('.mwe-focus-point-status'));

			const globalPos = parsePosition(globalValue || '50% 50%');
			updateMarkerPosition(marker, globalPos);
			positionDisplay.textContent = globalValue || '50% 50%';
			typeLabel.textContent = i18n.globalValue;
			resetButton.disabled = true;
		});
	}

	/**
	 * Find the best insertion point in the panel.
	 */
	function findInsertionPoint(panel) {
		// Try to find a good spot after the main content but before other controls.
		const candidates = [
			panel.querySelector('[class*="attributes"]'),
			panel.querySelector('[class*="styles"]'),
			panel.querySelector('[class*="settings"]'),
			panel.querySelector('div:first-child')
		];

		for (const candidate of candidates) {
			if (candidate) return candidate;
		}

		return null;
	}

	/**
	 * Get the image key for storage.
	 */
	function getImageKey(image) {
		// Try to get attachment ID from data attribute or class.
		const attachmentId = image.dataset.attachmentId ||
							 image.className.match(/wp-image-(\d+)/)?.[1];

		if (attachmentId) {
			return `attachment_${attachmentId}`;
		}

		// Fall back to URL hash.
		return 'url_' + hashCode(image.src);
	}

	/**
	 * Get global focus point from image data.
	 */
	function getGlobalFocusPoint(image) {
		// Check for inline style.
		const style = image.style.objectPosition;
		if (style) return style;

		// Check for data attribute.
		return image.dataset.focusPoint || null;
	}

	/**
	 * Parse position string to x/y values.
	 */
	function parsePosition(positionStr) {
		const match = positionStr.match(/([\d.]+)%\s+([\d.]+)%/);
		if (match) {
			return { x: parseFloat(match[1]), y: parseFloat(match[2]) };
		}
		return { x: 50, y: 50 };
	}

	/**
	 * Update marker position.
	 */
	function updateMarkerPosition(marker, position) {
		marker.style.left = `${position.x}%`;
		marker.style.top = `${position.y}%`;
	}

	/**
	 * Save focus point via AJAX.
	 */
	async function saveFocusPoint(imageKey, focusPoint, statusElement) {
		if (!postId) {
			console.error('MWE Focus Point: No post ID available');
			return;
		}

		statusElement.textContent = i18n.saving;
		statusElement.className = 'mwe-focus-point-status saving';

		try {
			const formData = new FormData();
			formData.append('action', 'mwe_save_focus_override');
			formData.append('nonce', nonce);
			formData.append('post_id', postId);
			formData.append('image_key', imageKey);
			formData.append('focus_point', focusPoint);

			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: formData
			});

			const data = await response.json();

			if (data.success) {
				overridesCache[imageKey] = focusPoint;
				statusElement.textContent = i18n.saved;
				statusElement.className = 'mwe-focus-point-status saved';

				setTimeout(() => {
					statusElement.textContent = '';
					statusElement.className = 'mwe-focus-point-status';
				}, 2000);
			} else {
				throw new Error(data.data?.message || 'Save failed');
			}
		} catch (error) {
			console.error('MWE Focus Point: Save error', error);
			statusElement.textContent = i18n.error;
			statusElement.className = 'mwe-focus-point-status error';
		}
	}

	/**
	 * Delete focus point override via AJAX.
	 */
	async function deleteFocusPoint(imageKey, statusElement) {
		if (!postId) return;

		statusElement.textContent = i18n.saving;
		statusElement.className = 'mwe-focus-point-status saving';

		try {
			const formData = new FormData();
			formData.append('action', 'mwe_delete_focus_override');
			formData.append('nonce', nonce);
			formData.append('post_id', postId);
			formData.append('image_key', imageKey);

			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: formData
			});

			const data = await response.json();

			if (data.success) {
				delete overridesCache[imageKey];
				statusElement.textContent = i18n.saved;
				statusElement.className = 'mwe-focus-point-status saved';

				setTimeout(() => {
					statusElement.textContent = '';
					statusElement.className = 'mwe-focus-point-status';
				}, 2000);
			}
		} catch (error) {
			console.error('MWE Focus Point: Delete error', error);
			statusElement.textContent = i18n.error;
			statusElement.className = 'mwe-focus-point-status error';
		}
	}

	/**
	 * Simple hash function for URLs.
	 */
	function hashCode(str) {
		let hash = 0;
		for (let i = 0; i < str.length; i++) {
			const char = str.charCodeAt(i);
			hash = ((hash << 5) - hash) + char;
			hash = hash & hash;
		}
		return Math.abs(hash).toString(16);
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
```

**Step 2: Commit**

```bash
git add assets/js/focus-point-editor.js
git commit -m "feat: add JavaScript for focus point visual editor"
```

---

## Task 5: Create CSS for Focus Point Editor

**Files:**
- Create: `assets/css/focus-point-editor.css`

**Step 1: Create the CSS file**

```css
/**
 * Focus Point Editor Styles
 *
 * Styles for the visual focus point editor in Etch canvas.
 *
 * @package MWE_EtchWP_Enhancements
 * @since   1.1.0
 */

/* Container */
.mwe-focus-point-container {
	padding: 12px;
	margin: 8px 0;
	background: var(--e-base-light, #2a2a2e);
	border-radius: var(--e-border-radius, 6px);
	border: 1px solid var(--e-border-color, #3a3a3e);
}

/* Header */
.mwe-focus-point-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
}

.mwe-focus-point-title {
	font-size: var(--e-font-size-m, 12px);
	font-weight: 600;
	color: var(--e-foreground-color, #e0e0e4);
}

.mwe-focus-point-status {
	font-size: var(--e-font-size-s, 11px);
	padding: 2px 6px;
	border-radius: 3px;
}

.mwe-focus-point-status.saving {
	color: var(--e-warning, #f2c960);
}

.mwe-focus-point-status.saved {
	color: var(--e-success, #67d992);
}

.mwe-focus-point-status.error {
	color: var(--e-danger, #f26060);
}

/* Preview Area */
.mwe-focus-point-preview {
	position: relative;
	width: 100%;
	aspect-ratio: 16 / 10;
	background: var(--e-base-dark, #1a1a1e);
	border-radius: 4px;
	overflow: hidden;
	cursor: crosshair;
	margin-bottom: 10px;
}

.mwe-focus-point-preview-image {
	width: 100%;
	height: 100%;
	object-fit: cover;
	pointer-events: none;
}

/* Focus Point Marker */
.mwe-focus-point-marker {
	position: absolute;
	width: 20px;
	height: 20px;
	transform: translate(-50%, -50%);
	pointer-events: none;
	z-index: 10;
}

.mwe-focus-point-marker::before,
.mwe-focus-point-marker::after {
	content: '';
	position: absolute;
	background: var(--e-primary, #6dd5d5);
	box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.5);
}

/* Horizontal line */
.mwe-focus-point-marker::before {
	width: 20px;
	height: 2px;
	top: 50%;
	left: 0;
	transform: translateY(-50%);
}

/* Vertical line */
.mwe-focus-point-marker::after {
	width: 2px;
	height: 20px;
	left: 50%;
	top: 0;
	transform: translateX(-50%);
}

/* Center dot */
.mwe-focus-point-marker::before {
	box-shadow:
		0 0 0 1px rgba(0, 0, 0, 0.5),
		9px 0 0 0 var(--e-primary, #6dd5d5),
		-9px 0 0 0 var(--e-primary, #6dd5d5);
}

/* Info Area */
.mwe-focus-point-info {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
	font-size: var(--e-font-size-s, 11px);
}

.mwe-focus-point-position {
	font-family: var(--e-font-code, monospace);
	color: var(--e-foreground-color, #e0e0e4);
	background: var(--e-base-dark, #1a1a1e);
	padding: 3px 8px;
	border-radius: 3px;
}

.mwe-focus-point-type {
	color: var(--e-foreground-color-muted, #a0a0a4);
	font-style: italic;
}

/* Actions */
.mwe-focus-point-actions {
	display: flex;
	gap: 8px;
}

.mwe-focus-point-button {
	flex: 1;
	padding: 6px 12px;
	font-size: var(--e-font-size-s, 11px);
	font-weight: 500;
	border: 1px solid var(--e-border-color, #3a3a3e);
	border-radius: 4px;
	background: var(--e-base, #26262a);
	color: var(--e-foreground-color, #e0e0e4);
	cursor: pointer;
	transition: all 0.15s ease;
}

.mwe-focus-point-button:hover:not(:disabled) {
	background: var(--e-base-light, #2a2a2e);
	border-color: var(--e-primary, #6dd5d5);
}

.mwe-focus-point-button:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.mwe-focus-point-reset {
	background: transparent;
}

/* Hover effect on preview */
.mwe-focus-point-preview:hover .mwe-focus-point-marker::before,
.mwe-focus-point-preview:hover .mwe-focus-point-marker::after {
	background: var(--e-selected, #469fea);
}

/* Animation for marker on click */
@keyframes mwe-focus-pulse {
	0% { transform: translate(-50%, -50%) scale(1); }
	50% { transform: translate(-50%, -50%) scale(1.3); }
	100% { transform: translate(-50%, -50%) scale(1); }
}

.mwe-focus-point-marker.pulse {
	animation: mwe-focus-pulse 0.3s ease;
}
```

**Step 2: Commit**

```bash
git add assets/css/focus-point-editor.css
git commit -m "feat: add CSS styles for focus point visual editor"
```

---

## Task 6: Update Focus_Position to Support Overrides

**Files:**
- Modify: `includes/class-focus-position.php`

**Step 1: Update the add_focus_to_image method to check for overrides**

In `includes/class-focus-position.php`, replace the `add_focus_to_image` method (lines 136-192) with:

```php
	/**
	 * Add focus point to individual Etch image.
	 *
	 * @since  1.0.0
	 * @param  array $matches Regex matches from preg_replace_callback.
	 * @return string         The enhanced image tag.
	 */
	public function add_focus_to_image( $matches ) {
		$full_tag = $matches[0];
		$src      = $matches[2];

		// Get current post ID for override lookup.
		$post_id = $this->get_current_post_id();

		// Try to get attachment ID from src URL.
		$attachment_id = attachment_url_to_postid( $src );

		// If that fails, try a more comprehensive search.
		if ( ! $attachment_id ) {
			$attachment_id = Helper::find_attachment_by_filename( $src );
		}

		// Determine the image key for override lookup.
		$image_key = $attachment_id
			? 'attachment_' . $attachment_id
			: Focus_Ajax::generate_url_key( $src );

		// Check for per-page override first.
		$position = null;
		if ( $post_id ) {
			$position = $this->get_override_position( $post_id, $image_key );
		}

		// Fall back to global focus point from Media Library.
		if ( ! $position && $attachment_id ) {
			$metadata    = wp_get_attachment_metadata( $attachment_id );
			$focus_point = $metadata['focus_point'] ?? null;

			if ( $focus_point ) {
				// We only use desktop value now (mobile is deprecated).
				$position = $focus_point['desktop'] ?? null;
			}
		}

		// No position found, return original tag.
		if ( ! $position || '50% 50%' === $position ) {
			return $full_tag;
		}

		// Check if object-position is already applied.
		if ( false !== strpos( $full_tag, 'object-position:' ) ) {
			return $full_tag; // Already has focus point applied.
		}

		// Add or modify style attribute.
		if ( false !== strpos( $full_tag, 'style=' ) ) {
			// Style attribute exists, append to it.
			$full_tag = preg_replace(
				'/style=["\']([^"\']*)["\']/',
				'style="$1; object-position: ' . esc_attr( $position ) . '"',
				$full_tag
			);
		} else {
			// No style attribute, add one.
			$full_tag = str_replace( '<img', '<img style="object-position: ' . esc_attr( $position ) . '"', $full_tag );
		}

		// Enhance image with missing attributes if Image_Enhancement is available.
		if ( class_exists( 'MWE\\EtchWP_Enhancements\\Image_Enhancement' ) ) {
			$enhancement = Image_Enhancement::get_instance();
			$full_tag    = $enhancement->add_attributes( $full_tag, $attachment_id );
		}

		return $full_tag;
	}

	/**
	 * Get override position from post meta.
	 *
	 * @since  1.1.0
	 * @param  int    $post_id   The post ID.
	 * @param  string $image_key The image key.
	 * @return string|null       The focus point position or null.
	 */
	private function get_override_position( int $post_id, string $image_key ): ?string {
		if ( ! class_exists( 'MWE\\EtchWP_Enhancements\\Focus_Ajax' ) ) {
			return null;
		}

		return Focus_Ajax::get_instance()->get_override( $post_id, $image_key );
	}

	/**
	 * Get the current post ID.
	 *
	 * @since  1.1.0
	 * @return int The post ID or 0.
	 */
	private function get_current_post_id(): int {
		global $post;

		if ( $post instanceof \WP_Post ) {
			return $post->ID;
		}

		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post ) {
			return $queried->ID;
		}

		return 0;
	}
```

**Step 2: Commit**

```bash
git add includes/class-focus-position.php
git commit -m "feat: add override support to Focus_Position class"
```

---

## Task 7: Update Plugin Class to Initialize New Components

**Files:**
- Modify: `includes/class-plugin.php`

**Step 1: Add new instance properties after line 54**

After `private $focus_position = null;` (line 54), add:

```php
	/**
	 * Focus Dynamic Data instance.
	 *
	 * @since 1.1.0
	 * @var Focus_Dynamic_Data|null
	 */
	private $focus_dynamic_data = null;

	/**
	 * Focus Ajax instance.
	 *
	 * @since 1.1.0
	 * @var Focus_Ajax|null
	 */
	private $focus_ajax = null;

	/**
	 * Focus Editor UI instance.
	 *
	 * @since 1.1.0
	 * @var Focus_Editor_UI|null
	 */
	private $focus_editor_ui = null;
```

**Step 2: Update init_features method (around line 133)**

Replace the `init_features` method with:

```php
	/**
	 * Initialize plugin features.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_features() {
		// Initialize Image Enhancement.
		if ( $this->is_image_enhancement_enabled() ) {
			$this->image_enhancement = Image_Enhancement::get_instance();
			$this->image_enhancement->init();
		}

		// Initialize Focus Position features.
		if ( $this->is_focus_position_enabled() ) {
			// Core focus position (render_block filter).
			$this->focus_position = Focus_Position::get_instance();
			$this->focus_position->init();

			// Dynamic Data integration for Etch.
			$this->focus_dynamic_data = Focus_Dynamic_Data::get_instance();
			$this->focus_dynamic_data->init();

			// AJAX handlers for overrides.
			$this->focus_ajax = Focus_Ajax::get_instance();
			$this->focus_ajax->init();

			// Editor UI for Etch canvas.
			$this->focus_editor_ui = Focus_Editor_UI::get_instance();
			$this->focus_editor_ui->init();
		}
	}
```

**Step 3: Commit**

```bash
git add includes/class-plugin.php
git commit -m "feat: initialize new focus point components in Plugin class"
```

---

## Task 8: Create Assets Directory Structure

**Files:**
- Create: `assets/js/.gitkeep` (if not exists)
- Create: `assets/css/.gitkeep` (if not exists)

**Step 1: Ensure asset directories exist**

```bash
mkdir -p assets/js assets/css
touch assets/js/.gitkeep assets/css/.gitkeep
```

**Step 2: Commit (if directories were new)**

```bash
git add assets/
git commit -m "chore: add assets directory structure"
```

---

## Task 9: Update Version Number

**Files:**
- Modify: `mwe-etchwp-enhancements.php`

**Step 1: Update version from 1.0.7 to 1.1.0**

In `mwe-etchwp-enhancements.php`, update:
- Line 14: ` * Version:           1.1.0`
- Line 37: `define( 'MWE_ETCHWP_VERSION', '1.1.0' );`

**Step 2: Commit**

```bash
git add mwe-etchwp-enhancements.php
git commit -m "chore: bump version to 1.1.0"
```

---

## Task 10: Update README with Documentation

**Files:**
- Modify: `README.md` (create if not exists)

**Step 1: Add focus point documentation section**

Add the following section to README.md:

```markdown
## Focus Point for Images

### Overview

This plugin provides comprehensive focus point support for images in Etch page builder. Focus points ensure the most important part of an image remains visible when the image is cropped at different viewport sizes.

### Features

1. **Automatic Application**: Focus points set in the Media Library (via compatible plugins) are automatically applied to images rendered by Etch.

2. **Per-Page Overrides**: In the Etch editor, you can set a custom focus point for any image that overrides the global Media Library value.

3. **Dynamic Data Integration**: Focus points are available in Etch's Dynamic Data system for use in custom styles.

### Setting Focus Points

#### In the Media Library (Global)

Install one of the following compatible plugins:
- [Image Background Focus Position](https://www.wordpress-focalpoint.com/)
- [Media Focus Point](https://wordpress.org/plugins/media-focus-point/)

Then set focus points in the Media Library - these apply globally wherever the image is used.

#### In the Etch Editor (Per-Page Override)

1. Select an image in the Etch canvas
2. In the Element Settings panel, find the "Focus Point" section
3. Click on the preview image to set the focus point
4. The override is saved automatically for this page only

### Using Focus Points in Etch Styles

You can reference focus point values in your Etch styles using Dynamic Data:

```css
/* For featured images */
object-position: {this.image.focusPoint};

/* Apply to a specific class */
.hero-image {
  object-position: {this.image.focusPoint};
}
```

### Priority Order

Focus points are applied in this order (first match wins):
1. Per-page override (set in Etch editor)
2. Media Library focus point (global)
3. Default: `50% 50%` (center)

### Hooks and Filters

#### Filter: `mwe_etchwp_enable_focus_position`

Enable or disable the focus position feature:

```php
add_filter( 'mwe_etchwp_enable_focus_position', '__return_false' );
```

#### Constant: `MWE_ETCHWP_FOCUS_POSITION`

```php
define( 'MWE_ETCHWP_FOCUS_POSITION', false ); // Disable feature
```
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add focus point documentation to README"
```

---

## Task 11: Final Integration Test Checklist

**Manual Testing Steps:**

1. **Activate Plugin**
   - Verify no PHP errors in debug.log
   - Verify all classes autoload correctly

2. **Test Dynamic Data Filter**
   - Create a post with featured image
   - Set focus point in Media Library
   - Verify `{this.image.focusPoint}` returns correct value in Etch

3. **Test Render Block Filter**
   - Add image to Etch page
   - Set focus point in Media Library
   - Verify `object-position` appears in rendered HTML

4. **Test Per-Page Override**
   - Open Etch editor
   - Select an image
   - Verify Focus Point UI appears in settings panel
   - Click to set focus point
   - Verify AJAX save works
   - Refresh page - verify override persists
   - Verify frontend uses override, not global

5. **Test Reset to Global**
   - Click "Use Global" button
   - Verify override is removed
   - Verify frontend uses global value again

**Step: Create final commit after testing**

```bash
git add -A
git commit -m "feat: complete focus point override implementation

- Add Focus_Dynamic_Data for Etch dynamic data integration
- Add Focus_Ajax for AJAX save/load of per-page overrides
- Add Focus_Editor_UI for visual editor in Etch canvas
- Update Focus_Position to respect overrides
- Add comprehensive documentation

Closes #1"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Focus_Dynamic_Data class | `includes/class-focus-dynamic-data.php` |
| 2 | Focus_Ajax class | `includes/class-focus-ajax.php` |
| 3 | Focus_Editor_UI class | `includes/class-focus-editor-ui.php` |
| 4 | JavaScript editor | `assets/js/focus-point-editor.js` |
| 5 | CSS styles | `assets/css/focus-point-editor.css` |
| 6 | Update Focus_Position | `includes/class-focus-position.php` |
| 7 | Update Plugin class | `includes/class-plugin.php` |
| 8 | Create asset directories | `assets/js/`, `assets/css/` |
| 9 | Update version | `mwe-etchwp-enhancements.php` |
| 10 | Update README | `README.md` |
| 11 | Integration testing | Manual verification |

---

**Plan complete and saved to `docs/plans/2025-01-01-focus-point-override.md`. Two execution options:**

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

**Which approach?**
