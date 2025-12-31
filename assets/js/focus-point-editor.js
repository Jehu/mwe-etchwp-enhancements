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

	/**
	 * Wait for config to be available.
	 */
	function waitForConfig(callback) {
		if (typeof mweFocusPointEditor !== 'undefined') {
			callback();
		} else {
			// Check again after a short delay
			setTimeout(() => waitForConfig(callback), 100);
		}
	}

	/**
	 * Main initialization function.
	 */
	function main() {
		// Ensure config is available.
		if (typeof mweFocusPointEditor === 'undefined') {
			console.error('MWE Focus Point Editor: Configuration not found after waiting');
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

		// Track the current image src to detect changes
		let currentImageSrc = null;

		/**
		 * Check if an image settings panel is open and inject our UI.
		 */
		function checkForImagePanel() {
			// Look for Etch's HTML block properties wrapper (the actual settings container for images)
			const panel = document.querySelector('.etch-html-block-properties-wrapper');
			
			if (!panel) {
				// No panel, remove any existing UI and reset tracking
				removeExistingFocusUI();
				currentImageSrc = null;
				return;
			}

			// Verify this is for an image by checking for img-specific fields
			const tagInput = panel.querySelector('input[value="img"], input[placeholder*="tag"]');
			const srcButton = panel.querySelector('button[class*="media"]');
			
			// Must have both tag input and src/media button to be an image panel
			if (!tagInput && !srcButton) {
				removeExistingFocusUI();
				currentImageSrc = null;
				return;
			}

			// Get the currently selected image.
			const selectedImage = getSelectedImage();
			if (!selectedImage) return;

			// Check if the image changed
			const newImageSrc = selectedImage.src;
			if (newImageSrc !== currentImageSrc) {
				// Image changed - remove old UI and create new one
				removeExistingFocusUI();
				currentImageSrc = newImageSrc;
				injectFocusPointUI(panel, selectedImage);
			}
		}

		/**
		 * Remove existing focus point UI.
		 */
		function removeExistingFocusUI() {
			const existingUI = document.querySelector('.mwe-focus-point-container');
			if (existingUI) {
				existingUI.remove();
			}
		}



		/**
		 * Get the currently selected image in the canvas.
		 */
		function getSelectedImage() {
			// First, try to get the image src from the panel itself
			const panel = document.querySelector('.etch-html-block-properties-wrapper');
			if (panel) {
				// Find the src input field (second input usually contains the URL)
				const inputs = panel.querySelectorAll('input.etch-input');
				for (const input of inputs) {
					if (input.value && input.value.includes('/wp-content/uploads/')) {
						// Create a virtual image object with the src
						return { src: input.value, fromPanel: true };
					}
				}
			}
			
			// Try to find in iframe
			const iframe = document.querySelector('iframe[title="Etch Iframe"]');
			if (iframe) {
				try {
					const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
					const selectors = [
						'.etch-selected img',
						'[data-etch-selected] img',
						'.etch-builder-block--selected img',
						'img.etch-builder-block--selected'
					];
					
					for (const selector of selectors) {
						const img = iframeDoc.querySelector(selector);
						if (img) return img;
					}
					
					// Fallback: get any image that matches the panel's src
					if (panel) {
						const inputs = panel.querySelectorAll('input.etch-input');
						for (const input of inputs) {
							if (input.value && input.value.includes('/wp-content/uploads/')) {
								const img = iframeDoc.querySelector(`img[src="${input.value}"]`);
								if (img) return img;
							}
						}
					}
				} catch (e) {
					console.warn('MWE Focus Point: Cannot access iframe', e);
				}
			}

			// Fallback: look in main document
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
			// Find the last label (after src, alt, class fields) to insert after it
			const labels = panel.querySelectorAll('label.etch-label');
			return labels.length > 0 ? labels[labels.length - 1] : panel.firstElementChild;
		}

		/**
		 * Get the image key for storage.
		 */
		function getImageKey(image) {
			// Handle virtual image object from panel
			if (image.fromPanel) {
				return 'url_' + hashCode(image.src);
			}
			
			// Try to get attachment ID from data attribute or class.
			const attachmentId = image.dataset?.attachmentId ||
								 (image.className || '').match(/wp-image-(\d+)/)?.[1];

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
			// Handle virtual image object from panel
			if (image.fromPanel) {
				return null; // No global focus point available from panel
			}
			
			// Check for inline style.
			const style = image.style?.objectPosition;
			if (style) return style;

			// Check for data attribute.
			return image.dataset?.focusPoint || null;
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
	}

	// Wait for config and then initialize.
	waitForConfig(main);

})();
