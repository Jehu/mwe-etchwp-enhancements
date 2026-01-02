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

		// Cache for global focus point AJAX responses (by URL).
		const globalFocusPointCache = new Map();

		/**
		 * Initialize the focus point editor.
		 */
		function init() {
			// Load existing overrides, then apply to iframe.
			loadOverrides().then(() => {
				applyFocusPointsToIframe();
			});

			// Watch for panel changes.
			observePanelChanges();

			// Listen for image selection in canvas.
			observeCanvasSelection();

			// Watch for src input changes in panel.
			observeSrcInputChanges();

			// Watch iframe for image changes.
			observeIframeChanges();
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
		 * Observe src input changes in the panel.
		 * Input value changes are not DOM mutations, so we need event listeners.
		 */
		function observeSrcInputChanges() {
			// Use event delegation on document for input events
			document.addEventListener('input', (e) => {
				const input = e.target;
				if (!input.matches || !input.matches('.etch-input')) return;

				// Check if this input contains an image URL
				if (isImageUrl(input.value)) {
					// Debounce the update to avoid too many refreshes while typing
					clearTimeout(observeSrcInputChanges.debounceTimer);
					observeSrcInputChanges.debounceTimer = setTimeout(() => {
						checkForImagePanel();
					}, 300);
				}
			});

			// Also listen for change events (when input loses focus)
			document.addEventListener('change', (e) => {
				const input = e.target;
				if (!input.matches || !input.matches('.etch-input')) return;

				if (isImageUrl(input.value)) {
					checkForImagePanel();
				}
			});
		}

		/**
		 * Observe iframe for image changes and apply focus points.
		 */
		function observeIframeChanges() {
			const checkIframe = () => {
				const iframe = document.querySelector('iframe[title="Etch Iframe"]');
				if (!iframe) {
					setTimeout(checkIframe, 500);
					return;
				}

				try {
					const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
					
					// Apply focus points initially
					applyFocusPointsToIframe();

					// Observe iframe for image-related changes only
					const observer = new MutationObserver((mutations) => {
						let hasImageChange = false;
						
						for (const mutation of mutations) {
							// Check for src attribute changes on img elements
							if (mutation.type === 'attributes' && 
								mutation.attributeName === 'src' && 
								mutation.target.tagName === 'IMG') {
								hasImageChange = true;
								break;
							}
							
							// Check for added img elements
							if (mutation.type === 'childList') {
								for (const node of mutation.addedNodes) {
									if (node.tagName === 'IMG' || 
										(node.querySelectorAll && node.querySelectorAll('img').length > 0)) {
										hasImageChange = true;
										break;
									}
								}
							}
							
							if (hasImageChange) break;
						}
						
						if (hasImageChange) {
							applyFocusPointsToIframe();
						}
					});

					observer.observe(iframeDoc.body, {
						childList: true,
						subtree: true,
						attributes: true,
						attributeFilter: ['src']
					});
				} catch (e) {
					// Iframe not ready, try again
					setTimeout(checkIframe, 500);
				}
			};

			checkIframe();
		}

		/**
		 * Apply focus points to all images in the Etch iframe.
		 */
		async function applyFocusPointsToIframe() {
			const iframe = document.querySelector('iframe[title="Etch Iframe"]');
			if (!iframe) return;

			try {
				const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
				const images = iframeDoc.querySelectorAll('img');

				// Process all images in parallel for better performance
				await Promise.all([...images].map(img => applyFocusPointToImage(img)));
			} catch (e) {
				// Iframe not accessible
			}
		}

		/**
		 * Apply focus point to a single image.
		 */
		async function applyFocusPointToImage(img) {
			const src = img.src;
			if (!src || src.includes('data:')) return;

			// Fetch global data (includes attachment_id)
			const globalData = await fetchGlobalFocusPoint(src);
			const attachmentId = globalData?.attachmentId || null;
			const globalFocusPoint = globalData?.focusPoint || null;

			// Determine image key
			const imageKey = attachmentId 
				? `attachment_${attachmentId}` 
				: 'url_' + md5(src);

			// Check for override first, then global
			const focusPoint = overridesCache[imageKey] || globalFocusPoint;

			if (focusPoint && focusPoint !== '50% 50%') {
				img.style.objectPosition = focusPoint;
			}
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

			// Verify this is for an image by checking if tag value is 'img'
			const tagInput = panel.querySelector('input.etch-combobox__input, input[placeholder="Enter tag"]');
			const isImageTag = tagInput && tagInput.value.toLowerCase() === 'img';
			
			if (!isImageTag) {
				removeExistingFocusUI();
				currentImageSrc = null;
				return;
			}

			// Get the currently selected image.
			const selectedImage = getSelectedImage();
			if (!selectedImage) {
				removeExistingFocusUI();
				currentImageSrc = null;
				return;
			}

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
		 * Check if a string looks like an image URL.
		 */
		function isImageUrl(str) {
			if (!str) return false;
			// Check for common image patterns
			return str.startsWith('http://') || 
				   str.startsWith('https://') || 
				   str.startsWith('/wp-content/uploads/');
		}

		/**
		 * Get the currently selected image in the canvas.
		 */
		function getSelectedImage() {
			// First, try to get the image src from the panel itself
			const panel = document.querySelector('.etch-html-block-properties-wrapper');
			if (panel) {
				// Find the src input field - look for input after 'src' label
				const labels = panel.querySelectorAll('label.etch-label');
				for (const label of labels) {
					if (label.textContent.trim().toLowerCase() === 'src') {
						// Get the next input after this label
						const nextInput = label.querySelector('input.etch-input');
						if (nextInput && isImageUrl(nextInput.value)) {
							return { src: nextInput.value, fromPanel: true };
						}
					}
				}
				
				// Fallback: check all inputs for image URLs
				const inputs = panel.querySelectorAll('input.etch-input');
				for (const input of inputs) {
					if (isImageUrl(input.value)) {
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
		async function injectFocusPointUI(panel, image) {
			// Fetch global focus point and attachment ID from server
			const globalData = await fetchGlobalFocusPoint(image.src);
			const globalValue = globalData?.focusPoint || null;
			const attachmentId = globalData?.attachmentId || null;
			
			// Use attachment_id for WordPress images, URL hash for external
			const imageKey = attachmentId 
				? `attachment_${attachmentId}` 
				: 'url_' + md5(image.src);
			
			const currentOverride = overridesCache[imageKey] || null;

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
		 * Fetch global focus point and attachment ID from server via AJAX.
		 * Returns { focusPoint, attachmentId } or null.
		 * Results are cached by URL.
		 */
		async function fetchGlobalFocusPoint(imageUrl) {
			if (!imageUrl) return null;

			// Check cache first
			if (globalFocusPointCache.has(imageUrl)) {
				return globalFocusPointCache.get(imageUrl);
			}
			
			try {
				const response = await fetch(
					`${ajaxUrl}?action=mwe_get_global_focus_point&image_url=${encodeURIComponent(imageUrl)}&nonce=${nonce}`
				);
				const data = await response.json();
				
				if (data.success) {
					const result = {
						focusPoint: data.data.focus_point || null,
						attachmentId: data.data.attachment_id || null
					};
					// Cache the result
					globalFocusPointCache.set(imageUrl, result);
					return result;
				}
			} catch (error) {
				console.warn('MWE Focus Point: Failed to fetch global focus point', error);
			}
			
			// Cache null result to avoid repeated failed requests
			globalFocusPointCache.set(imageUrl, null);
			return null;
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

					// Apply to iframe immediately
					applyFocusPointsToIframe();

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

					// Apply to iframe immediately (will use global or default)
					applyFocusPointsToIframe();

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
		 * MD5 hash function for URLs (matches PHP's md5()).
		 * Simplified implementation for generating consistent keys.
		 */
		function md5(str) {
			// Use a simple but consistent hash that matches PHP's md5
			// We'll use the same algorithm as PHP by implementing MD5
			function rotateLeft(val, shift) {
				return (val << shift) | (val >>> (32 - shift));
			}

			function addUnsigned(x, y) {
				const x8 = x & 0x80000000;
				const y8 = y & 0x80000000;
				const x4 = x & 0x40000000;
				const y4 = y & 0x40000000;
				const result = (x & 0x3FFFFFFF) + (y & 0x3FFFFFFF);
				if (x4 & y4) return result ^ 0x80000000 ^ x8 ^ y8;
				if (x4 | y4) {
					if (result & 0x40000000) return result ^ 0xC0000000 ^ x8 ^ y8;
					return result ^ 0x40000000 ^ x8 ^ y8;
				}
				return result ^ x8 ^ y8;
			}

			function F(x, y, z) { return (x & y) | (~x & z); }
			function G(x, y, z) { return (x & z) | (y & ~z); }
			function H(x, y, z) { return x ^ y ^ z; }
			function I(x, y, z) { return y ^ (x | ~z); }

			function FF(a, b, c, d, x, s, ac) {
				a = addUnsigned(a, addUnsigned(addUnsigned(F(b, c, d), x), ac));
				return addUnsigned(rotateLeft(a, s), b);
			}
			function GG(a, b, c, d, x, s, ac) {
				a = addUnsigned(a, addUnsigned(addUnsigned(G(b, c, d), x), ac));
				return addUnsigned(rotateLeft(a, s), b);
			}
			function HH(a, b, c, d, x, s, ac) {
				a = addUnsigned(a, addUnsigned(addUnsigned(H(b, c, d), x), ac));
				return addUnsigned(rotateLeft(a, s), b);
			}
			function II(a, b, c, d, x, s, ac) {
				a = addUnsigned(a, addUnsigned(addUnsigned(I(b, c, d), x), ac));
				return addUnsigned(rotateLeft(a, s), b);
			}

			function convertToWordArray(str) {
				const len = str.length;
				const numWords = ((len + 8 - (len + 8) % 64) / 64 + 1) * 16;
				const words = new Array(numWords).fill(0);
				let pos = 0;
				for (let i = 0; i < len; i++) {
					const wordIdx = (i - i % 4) / 4;
					pos = (i % 4) * 8;
					words[wordIdx] |= str.charCodeAt(i) << pos;
				}
				const wordIdx = (len - len % 4) / 4;
				pos = (len % 4) * 8;
				words[wordIdx] |= 0x80 << pos;
				words[numWords - 2] = len << 3;
				words[numWords - 1] = len >>> 29;
				return words;
			}

			function wordToHex(val) {
				let hex = '';
				for (let i = 0; i <= 3; i++) {
					const byte = (val >>> (i * 8)) & 255;
					hex += ('0' + byte.toString(16)).slice(-2);
				}
				return hex;
			}

			const x = convertToWordArray(str);
			let a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;

			const S = [7, 12, 17, 22, 5, 9, 14, 20, 4, 11, 16, 23, 6, 10, 15, 21];
			const T = [];
			for (let i = 1; i <= 64; i++) T[i] = Math.floor(Math.abs(Math.sin(i)) * 0x100000000);

			for (let k = 0; k < x.length; k += 16) {
				const AA = a, BB = b, CC = c, DD = d;
				a = FF(a, b, c, d, x[k + 0], S[0], T[1]);
				d = FF(d, a, b, c, x[k + 1], S[1], T[2]);
				c = FF(c, d, a, b, x[k + 2], S[2], T[3]);
				b = FF(b, c, d, a, x[k + 3], S[3], T[4]);
				a = FF(a, b, c, d, x[k + 4], S[0], T[5]);
				d = FF(d, a, b, c, x[k + 5], S[1], T[6]);
				c = FF(c, d, a, b, x[k + 6], S[2], T[7]);
				b = FF(b, c, d, a, x[k + 7], S[3], T[8]);
				a = FF(a, b, c, d, x[k + 8], S[0], T[9]);
				d = FF(d, a, b, c, x[k + 9], S[1], T[10]);
				c = FF(c, d, a, b, x[k + 10], S[2], T[11]);
				b = FF(b, c, d, a, x[k + 11], S[3], T[12]);
				a = FF(a, b, c, d, x[k + 12], S[0], T[13]);
				d = FF(d, a, b, c, x[k + 13], S[1], T[14]);
				c = FF(c, d, a, b, x[k + 14], S[2], T[15]);
				b = FF(b, c, d, a, x[k + 15], S[3], T[16]);

				a = GG(a, b, c, d, x[k + 1], S[4], T[17]);
				d = GG(d, a, b, c, x[k + 6], S[5], T[18]);
				c = GG(c, d, a, b, x[k + 11], S[6], T[19]);
				b = GG(b, c, d, a, x[k + 0], S[7], T[20]);
				a = GG(a, b, c, d, x[k + 5], S[4], T[21]);
				d = GG(d, a, b, c, x[k + 10], S[5], T[22]);
				c = GG(c, d, a, b, x[k + 15], S[6], T[23]);
				b = GG(b, c, d, a, x[k + 4], S[7], T[24]);
				a = GG(a, b, c, d, x[k + 9], S[4], T[25]);
				d = GG(d, a, b, c, x[k + 14], S[5], T[26]);
				c = GG(c, d, a, b, x[k + 3], S[6], T[27]);
				b = GG(b, c, d, a, x[k + 8], S[7], T[28]);
				a = GG(a, b, c, d, x[k + 13], S[4], T[29]);
				d = GG(d, a, b, c, x[k + 2], S[5], T[30]);
				c = GG(c, d, a, b, x[k + 7], S[6], T[31]);
				b = GG(b, c, d, a, x[k + 12], S[7], T[32]);

				a = HH(a, b, c, d, x[k + 5], S[8], T[33]);
				d = HH(d, a, b, c, x[k + 8], S[9], T[34]);
				c = HH(c, d, a, b, x[k + 11], S[10], T[35]);
				b = HH(b, c, d, a, x[k + 14], S[11], T[36]);
				a = HH(a, b, c, d, x[k + 1], S[8], T[37]);
				d = HH(d, a, b, c, x[k + 4], S[9], T[38]);
				c = HH(c, d, a, b, x[k + 7], S[10], T[39]);
				b = HH(b, c, d, a, x[k + 10], S[11], T[40]);
				a = HH(a, b, c, d, x[k + 13], S[8], T[41]);
				d = HH(d, a, b, c, x[k + 0], S[9], T[42]);
				c = HH(c, d, a, b, x[k + 3], S[10], T[43]);
				b = HH(b, c, d, a, x[k + 6], S[11], T[44]);
				a = HH(a, b, c, d, x[k + 9], S[8], T[45]);
				d = HH(d, a, b, c, x[k + 12], S[9], T[46]);
				c = HH(c, d, a, b, x[k + 15], S[10], T[47]);
				b = HH(b, c, d, a, x[k + 2], S[11], T[48]);

				a = II(a, b, c, d, x[k + 0], S[12], T[49]);
				d = II(d, a, b, c, x[k + 7], S[13], T[50]);
				c = II(c, d, a, b, x[k + 14], S[14], T[51]);
				b = II(b, c, d, a, x[k + 5], S[15], T[52]);
				a = II(a, b, c, d, x[k + 12], S[12], T[53]);
				d = II(d, a, b, c, x[k + 3], S[13], T[54]);
				c = II(c, d, a, b, x[k + 10], S[14], T[55]);
				b = II(b, c, d, a, x[k + 1], S[15], T[56]);
				a = II(a, b, c, d, x[k + 8], S[12], T[57]);
				d = II(d, a, b, c, x[k + 15], S[13], T[58]);
				c = II(c, d, a, b, x[k + 6], S[14], T[59]);
				b = II(b, c, d, a, x[k + 13], S[15], T[60]);
				a = II(a, b, c, d, x[k + 4], S[12], T[61]);
				d = II(d, a, b, c, x[k + 11], S[13], T[62]);
				c = II(c, d, a, b, x[k + 2], S[14], T[63]);
				b = II(b, c, d, a, x[k + 9], S[15], T[64]);

				a = addUnsigned(a, AA);
				b = addUnsigned(b, BB);
				c = addUnsigned(c, CC);
				d = addUnsigned(d, DD);
			}

			return wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);
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
