/**
 * OSWP User Portal - Frontend JavaScript
 */

(function () {
	'use strict';

	// Wait for DOM to be ready
	document.addEventListener('DOMContentLoaded', function () {
		// Initialize form enhancements
		initFormValidation();
		initPasswordStrength();
		initPasswordToggle();
		initOtpInputs();
		initFormToggle();
		initCharacterCounter();
		initConfirmDelete();
		initMediaUpload();
		initFormTabs();
		initWordCounter();
	});

	/**
	 * Media Upload Handler (WordPress Media Library - Admin Only)
	 * and Direct File Input Handler (Non-Admin Users)
	 */
	function initMediaUpload() {
		// Handle direct file upload input for non-admin users
		var fileInputs = document.querySelectorAll('.oswp-form__file-input');
		fileInputs.forEach(function (fileInput) {
			fileInput.addEventListener('change', function () {
				if (this.files && this.files[0]) {
					var file = this.files[0];
					var reader = new FileReader();
					
					reader.onload = function (e) {
						var container = fileInput.closest('.oswp-form__file-upload');
						if (!container) return;
						
						// Remove existing preview if any
						var existingPreview = container.querySelector('.oswp-form__file-preview');
						if (existingPreview) {
							existingPreview.remove();
						}
						
						// Create preview div
						var preview = document.createElement('div');
						preview.className = 'oswp-form__file-preview';
						
						var img = document.createElement('img');
						img.src = e.target.result;
						img.alt = 'Preview';
						
						var label = document.createElement('p');
						label.textContent = 'Selected image';
						
						preview.appendChild(img);
						preview.appendChild(label);
						
						// Insert preview after the hidden input
						var hiddenInput = container.querySelector('input[type="hidden"]');
						if (hiddenInput && hiddenInput.nextSibling) {
							hiddenInput.parentNode.insertBefore(preview, hiddenInput.nextSibling);
						} else if (hiddenInput) {
							container.appendChild(preview);
						} else {
							container.appendChild(preview);
						}
					};
					
					reader.readAsDataURL(file);
				}
			});
		});

		// Only run admin media library if wp.media is available (admin users only)
		if (typeof wp === 'undefined' || !wp.media) {
			// Media library not available - non-admin users will use direct file input
			return;
		}

		// Helper: open media frame for a given context
		function openMediaFrame(input, preview, placeholder, removeBtn) {
			try {
				var customUploader = wp.media({
					title: 'Choose Featured Image',
					button: { text: 'Use this image' },
					multiple: false,
					library: { type: 'image' }
				});

				customUploader.on('select', function () {
					try {
						var attachment = customUploader.state().get('selection').first().toJSON();
						if (input) {
							input.value = attachment.id;
						}
						if (preview) {
							preview.innerHTML = '<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" />';
						}
						if (placeholder) {
							placeholder.style.display = 'none';
						}
						if (removeBtn) {
							removeBtn.classList.add('is-visible');
						}
					} catch (e) {
						console.error('Error processing attachment:', e);
						alert('Error selecting image. Please try again.');
					}
				});

				customUploader.on('error', function (error) {
					console.error('Media library error:', error);
					alert('Error opening media library. Please ensure you have upload permissions.');
				});

				customUploader.open();
			} catch (e) {
				console.error('Error opening media uploader:', e);
				alert('Cannot open media library. Please refresh the page and try again.');
			}
		}

		// Dropzone click → open media frame
		var dropzones = document.querySelectorAll('.oswp-media-upload__dropzone');
		dropzones.forEach(function (dropzone) {
			dropzone.addEventListener('click', function (e) {
				e.preventDefault();
				var container = this.closest('.oswp-form__field-row') || this.parentNode;
				var input = container.querySelector('input[type="hidden"]');
				var preview = this.querySelector('.oswp-media-upload__preview');
				var placeholder = this.querySelector('.oswp-media-upload__placeholder');
				var removeBtn = container.querySelector('.oswp-media-upload__remove');
				openMediaFrame(input, preview, placeholder, removeBtn);
			});
		});

		// Choose Image button
		var uploadButtons = document.querySelectorAll('.oswp-media-upload__btn');
		uploadButtons.forEach(function (button) {
			button.addEventListener('click', function (e) {
				e.preventDefault();
				var container = this.closest('.oswp-form__field-row') || this.parentNode.parentNode;
				var dropzone = container.querySelector('.oswp-media-upload__dropzone');
				var input = container.querySelector('input[type="hidden"]');
				var preview = dropzone ? dropzone.querySelector('.oswp-media-upload__preview') : null;
				var placeholder = dropzone ? dropzone.querySelector('.oswp-media-upload__placeholder') : null;
				var removeBtn = container.querySelector('.oswp-media-upload__remove');
				openMediaFrame(input, preview, placeholder, removeBtn);
			});
		});

		// Remove button
		var removeButtons = document.querySelectorAll('.oswp-media-upload__remove');
		removeButtons.forEach(function (button) {
			button.addEventListener('click', function (e) {
				e.preventDefault();
				var container = this.closest('.oswp-form__field-row') || this.parentNode.parentNode;
				var dropzone = container.querySelector('.oswp-media-upload__dropzone');
				var input = container.querySelector('input[type="hidden"]');
				var preview = dropzone ? dropzone.querySelector('.oswp-media-upload__preview') : null;
				var placeholder = dropzone ? dropzone.querySelector('.oswp-media-upload__placeholder') : null;

				if (input) {
					input.value = '';
				}
				if (preview) {
					preview.innerHTML = '';
				}
				if (placeholder) {
					placeholder.style.display = '';
				}
				this.classList.remove('is-visible');
			});
		});

		// Check if any media fields already have a value and show remove button
		removeButtons.forEach(function (button) {
			var container = button.closest('.oswp-form__field-row') || button.parentNode.parentNode;
			var input = container.querySelector('input[type="hidden"]');
			if (input && input.value) {
				button.classList.add('is-visible');
				var placeholder = container.querySelector('.oswp-media-upload__placeholder');
				if (placeholder) {
					placeholder.style.display = 'none';
				}
			}
		});
	}

	/**
	 * Form Validation
	 */
	function initFormValidation() {
		const forms = document.querySelectorAll('.oswp-form');
		forms.forEach(function (form) {
			form.addEventListener('submit', function (e) {
				if (!validateForm(this)) {
					e.preventDefault();
				}
			});
		});
	}

	function validateForm(form) {
		let isValid = true;
		const inputs = form.querySelectorAll('input[required], textarea[required]');

		inputs.forEach(function (input) {
			if (!input.value.trim()) {
				showError(input, 'This field is required');
				isValid = false;
			} else {
				clearError(input);
			}

			// Email validation
			if (input.type === 'email' && input.value) {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(input.value)) {
					showError(input, 'Please enter a valid email address');
					isValid = false;
				}
			}

			// URL validation
			if (input.type === 'url' && input.value) {
				try {
					new URL(input.value);
				} catch (e) {
					showError(input, 'Please enter a valid URL');
					isValid = false;
				}
			}
		});

		return isValid;
	}

	function showError(input, message) {
		clearError(input);
		input.classList.add('oswp-form__input--error');
		const error = document.createElement('span');
		error.className = 'oswp-form__error';
		error.textContent = message;
		input.parentNode.insertBefore(error, input.nextSibling);
	}

	function clearError(input) {
		input.classList.remove('oswp-form__input--error');
		const error = input.parentNode.querySelector('.oswp-form__error');
		if (error) {
			error.remove();
		}
	}

	/**
	 * Password Toggle (Show/Hide)
	 */
	function initPasswordToggle() {
		const passwordInputs = document.querySelectorAll('input[type="password"]');
		passwordInputs.forEach(function (input) {
			// Create toggle button
			const toggleBtn = document.createElement('button');
			toggleBtn.type = 'button';
			toggleBtn.className = 'oswp-password-toggle';
			toggleBtn.innerHTML = '<span class="dashicons dashicons-visibility"></span>';
			toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
			toggleBtn.title = 'Show password';

			// Style the input container to be relative for positioning
			const inputContainer = input.parentNode;
			if (inputContainer && !inputContainer.classList.contains('oswp-input-container')) {
				inputContainer.classList.add('oswp-input-container');
				inputContainer.style.position = 'relative';
				inputContainer.appendChild(toggleBtn);
			}

			// Add click event
			toggleBtn.addEventListener('click', function (e) {
				e.preventDefault();
				const isVisible = input.type === 'text';
				input.type = isVisible ? 'password' : 'text';
				this.innerHTML = isVisible ? 
					'<span class="dashicons dashicons-visibility"></span>' : 
					'<span class="dashicons dashicons-hidden"></span>';
				this.title = isVisible ? 'Show password' : 'Hide password';
				this.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
			});
		});
	}

	/**
	 * OTP Input Handler (Auto-focus and navigation)
	 */
	function initOtpInputs() {
		const otpContainers = document.querySelectorAll('.oswp-verification-code');
		otpContainers.forEach(function (container) {
			const inputs = container.querySelectorAll('.oswp-code-input');

			inputs.forEach(function (input, index) {
				input.addEventListener('input', function (e) {
					// Only allow numeric input
					this.value = this.value.replace(/[^0-9]/g, '');

					// Auto-focus next input
					if (this.value.length === 1 && index < inputs.length - 1) {
						inputs[index + 1].focus();
					}
				});

				input.addEventListener('keydown', function (e) {
					// Handle backspace
					if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
						inputs[index - 1].focus();
					}

					// Handle left arrow
					if (e.key === 'ArrowLeft' && index > 0) {
						e.preventDefault();
						inputs[index - 1].focus();
					}


					// Handle right arrow
					if (e.key === 'ArrowRight' && index < inputs.length - 1) {
						e.preventDefault();
						inputs[index + 1].focus();
					}
				});

				input.addEventListener('paste', function (e) {
					e.preventDefault();
					const paste = (e.clipboardData || window.clipboardData).getData('text');
					const pasteNumbers = paste.replace(/[^0-9]/g, '').slice(0, 6);

					// Fill all inputs with pasted numbers
					for (let i = 0; i < pasteNumbers.length && index + i < inputs.length; i++) {
						inputs[index + i].value = pasteNumbers[i];
					}

					// Focus the next empty input or the last input
					const nextEmptyIndex = Array.from(inputs).findIndex((input, i) => i >= index && input.value === '');
					if (nextEmptyIndex !== -1) {
						inputs[nextEmptyIndex].focus();
					} else {
						inputs[Math.min(index + pasteNumbers.length, inputs.length - 1)].focus();
					}
				});
			});
		});
	}

	function initPasswordStrength() {
		const passwordInputs = document.querySelectorAll('input[name*="password"]:not([name="current_password"]):not([name="confirm_password"])');
		passwordInputs.forEach(function (input) {
			input.addEventListener('input', function () {
				const strength = calculatePasswordStrength(this.value);
				displayPasswordStrength(this, strength);
			});
		});
	}

	function calculatePasswordStrength(password) {
		let strength = 0;

		if (!password) return 0;
		if (password.length >= 8) strength++;
		if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
		if (/\d/.test(password)) strength++;
		if (/[!@#$%^&*]/.test(password)) strength++;

		return strength;
	}

	function displayPasswordStrength(input, strength) {
		let existingMeter = input.parentNode.querySelector('.oswp-password-meter');
		if (!existingMeter) {
			existingMeter = document.createElement('div');
			existingMeter.className = 'oswp-password-meter';
			input.parentNode.appendChild(existingMeter);
		}

		const strengths = [
			{ text: 'Very Weak', color: '#d32f2f' },
			{ text: 'Weak', color: '#ff9800' },
			{ text: 'Fair', color: '#fbc02d' },
			{ text: 'Good', color: '#8bc34a' },
			{ text: 'Strong', color: '#28a745' }
		];

		const current = strengths[strength] || strengths[0];
		existingMeter.innerHTML = '<span style="color: ' + current.color + ';">Strength: ' + current.text + '</span>';
		existingMeter.style.display = strength > 0 ? 'block' : 'none';
	}

	/**
	 * Form Toggle/Collapse
	 */
	function initFormToggle() {
		const toggles = document.querySelectorAll('[data-toggle]');
		toggles.forEach(function (toggle) {
			toggle.addEventListener('click', function (e) {
				e.preventDefault();
				const target = document.querySelector(this.getAttribute('data-toggle'));
				if (target) {
					target.classList.toggle('visible');
				}
			});
		});
	}

	/**
	 * Character Counter for Textareas
	 */
	function initCharacterCounter() {
		const textareas = document.querySelectorAll('.oswp-form__textarea[data-max-length]');
		textareas.forEach(function (textarea) {
			const maxLength = parseInt(textarea.getAttribute('data-max-length'), 10);
			const counter = document.createElement('div');
			counter.className = 'oswp-char-counter';
			counter.style.fontSize = '12px';
			counter.style.color = '#999';
			counter.style.marginTop = '4px';
			textarea.parentNode.appendChild(counter);

			textarea.addEventListener('input', function () {
				const remaining = maxLength - this.value.length;
				const percentage = (this.value.length / maxLength) * 100;
				counter.textContent = 'Characters: ' + this.value.length + ' / ' + maxLength;
				if (percentage > 80) {
					counter.style.color = '#ff9800';
				} else if (percentage > 95) {
					counter.style.color = '#d32f2f';
				} else {
					counter.style.color = '#999';
				}
			});

			// Trigger initial count
			textarea.dispatchEvent(new Event('input'));
		});
	}

	/**
	 * Confirm Before Delete
	 */
	function initConfirmDelete() {
		const deleteLinks = document.querySelectorAll('a[href*="delete"]');
		deleteLinks.forEach(function (link) {
			if (link.classList.contains('oswp-btn--danger')) {
				link.addEventListener('click', function (e) {
					if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
						e.preventDefault();
					}
				});
			}
		});
	}

	/**
	 * Auto-hide notices after 5 seconds
	 */
	(function () {
		const notices = document.querySelectorAll('.oswp-notice');
		notices.forEach(function (notice) {
			setTimeout(function () {
				notice.style.opacity = '1';
				notice.style.transition = 'opacity 0.3s ease';
				// Don't auto-hide errors
				if (!notice.classList.contains('oswp-notice--error')) {
					setTimeout(function () {
						notice.style.opacity = '0';
						setTimeout(function () {
							notice.style.display = 'none';
						}, 300);
					}, 5000);
				}
			}, 100);
		});
	})();

	/**
	 * Smooth scroll for anchor links
	 */
	(function () {
		const links = document.querySelectorAll('a[href^="#"]');
		links.forEach(function (link) {
			link.addEventListener('click', function (e) {
				const target = document.querySelector(this.getAttribute('href'));
				if (target) {
					e.preventDefault();
					target.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			});
		});
	})();

	/**
	 * Word Counter for TinyMCE post content editor
	 * Uses dynamic limits from admin settings
	 */
	function initWordCounter() {
		var countEl = document.getElementById('oswp-word-count-number');
		var wrapEl = document.getElementById('oswp-word-count');
		if (!countEl || !wrapEl) {
			return;
		}

		// Get limits from localized data or use defaults
		var limits = (typeof oswpCharLimits !== 'undefined') ? oswpCharLimits : {};
		var MIN_WORDS = limits.post_content_min || 800;
		var MAX_WORDS = limits.post_content_max || 2000;

		function updateCount(text) {
			// Strip HTML tags and count words
			var stripped = text.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
			var count = stripped ? stripped.split(/\s+/).length : 0;
			countEl.textContent = count;

			// Update visual state
			wrapEl.classList.remove('is-valid', 'is-invalid');
			if (count >= MIN_WORDS && count <= MAX_WORDS) {
				wrapEl.classList.add('is-valid');
			} else if (count > 0) {
				wrapEl.classList.add('is-invalid');
			}
		}

		// Hook into TinyMCE when it initializes
		if (typeof tinymce !== 'undefined') {
			// Wait a moment for TinyMCE to initialize editors
			var checkInterval = setInterval(function () {
				var editor = tinymce.get('post_content');
				if (editor) {
					clearInterval(checkInterval);
					// Initial count
					updateCount(editor.getContent());

					// Listen for changes
					editor.on('input keyup change NodeChange', function () {
						updateCount(editor.getContent());
					});
				}
			}, 500);

			// Clear interval after 15 seconds to avoid infinite polling
			setTimeout(function () {
				clearInterval(checkInterval);
			}, 15000);
		}

		// Also listen to the raw textarea in case TinyMCE is in text mode
		var textarea = document.getElementById('post_content');
		if (textarea) {
			textarea.addEventListener('input', function () {
				updateCount(this.value);
			});
			// Initial count from textarea
			if (textarea.value) {
				updateCount(textarea.value);
			}
		}
	}

	/**
	 * Form Tabs Handler
	 */
	function initFormTabs() {
		const tabButtons = document.querySelectorAll('.oswp-form-tabs__tab');
		
		tabButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				
				const tabId = this.dataset.tab;
				const tabsContainer = this.closest('.oswp-form-tabs');
				
				// Remove active class from all tabs and content
				tabsContainer.querySelectorAll('.oswp-form-tabs__tab').forEach(function(tab) {
					tab.classList.remove('oswp-form-tabs__tab--active');
				});
				tabsContainer.querySelectorAll('.oswp-form-tabs__content').forEach(function(content) {
					content.classList.remove('oswp-form-tabs__content--active');
				});
				
				// Add active class to clicked tab and corresponding content
				this.classList.add('oswp-form-tabs__tab--active');
				const activeContent = tabsContainer.querySelector('.oswp-form-tabs__content[data-tab="' + tabId + '"]');
				if (activeContent) {
					activeContent.classList.add('oswp-form-tabs__content--active');
				}
			});
		});
	}

	/* ===================================================================
	 * CONTENT MODERATION — Banned Words (JS-side)
	 * Dynamically loaded from database via wp_localize_script()
	 * Mirrors the PHP list in Keyword_Manager::get_blocked_keywords()
	 * =================================================================== */
	var OSWP_BANNED_WORDS = (typeof oswpBannedWords !== 'undefined' && oswpBannedWords.words) ? oswpBannedWords.words : [];

	/**
	 * Search for banned words inside a string.
	 *
	 * Returns an array of words that were found (empty if none).  This mirrors
	 * the backend behaviour which can report multiple offending terms.
	 *
	 * @param {string} text
	 * @return {Array<string>}
	 */
	function oswpContainsBannedWord(text) {
		if (!text) {
			return [];
		}

		// Normalize for case-insensitive matching.
		var lower = text.toLowerCase();
		var found = [];

		function escapeRegExp(str) {
			return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		}

		for (var i = 0; i < OSWP_BANNED_WORDS.length; i++) {
			var word = OSWP_BANNED_WORDS[i];
			var wordLower = word.toLowerCase().trim();
			if (!wordLower) {
				continue;
			}

			// Use word boundaries so "bet" does not match "better" or "between".
			var pattern = new RegExp('\\b' + escapeRegExp(wordLower) + '\\b', 'u');
			if (pattern.test(lower)) {
				found.push(word);
			}
		}

		// Deduplicate in case one keyword is a substring of another
		return found.filter(function(value, index, self) {
			return self.indexOf(value) === index;
		});
	}

	/**
	 * Show an inline validation error below an element.
	 */
	function oswpShowInlineError(el, message, errorId) {
		oswpClearInlineError(errorId);
		var err = document.createElement('p');
		err.id = errorId;
		err.className = 'oswp-form__error oswp-form__error--js';
		err.style.cssText = 'color:#c0392b;font-size:13px;margin:4px 0 0;';
		err.textContent = message;
		if (el && el.parentNode) {
			el.parentNode.appendChild(err);
		}
	}

	function oswpClearInlineError(errorId) {
		var existing = document.getElementById(errorId);
		if (existing) { existing.remove(); }
	}

	/**
	 * Get plain-text content from TinyMCE editor or textarea fallback.
	 */
	function oswpGetEditorContent(editorId) {
		if (typeof tinymce !== 'undefined') {
			var ed = tinymce.get(editorId);
			if (ed) { return ed.getContent(); }
		}
		var ta = document.getElementById(editorId);
		return ta ? ta.value : '';
	}

	/**
	 * Count <a href= occurrences in HTML string.
	 */
	function oswpCountLinks(html) {
		var matches = html.match(/<a\s[^>]*href\s*=/gi);
		return matches ? matches.length : 0;
	}

	/* ===================================================================
	 * SEO Character Counters (real-time)
	 * Uses dynamic limits from admin settings
	 * =================================================================== */
	function initSeoCharCounters() {
		// Get limits from localized data or use defaults
		var limits = (typeof oswpCharLimits !== 'undefined') ? oswpCharLimits : {};
		var seoTitleMin = limits.seo_title_min || 50;
		var seoTitleMax = limits.seo_title_limit || 70;
		var metaDescMin = limits.seo_meta_desc_min || 150;
		var metaDescMax = limits.seo_meta_desc_limit || 170;

		var seoFields = [
			{ id: '_yoast_wpseo_title',    min: seoTitleMin, ideal: [seoTitleMin, 60], max: seoTitleMax,  label: 'SEO Title' },
			{ id: '_yoast_wpseo_metadesc', min: metaDescMin, ideal: [metaDescMin, metaDescMax], max: metaDescMax, label: 'Meta Description' }
		];

		seoFields.forEach(function (cfg) {
			var field = document.getElementById(cfg.id);
			if (!field) return;

			// Build counter element
			var counter = document.createElement('div');
			counter.id = 'oswp-seo-counter-' + cfg.id;
			counter.style.cssText = 'font-size:12px;margin-top:4px;';
			field.parentNode.appendChild(counter);

			function update() {
				var len = field.value.length;
				var color, status;
				if (len === 0) {
					counter.textContent = '';
					return;
				}
				if (len < cfg.min) {
					color = '#c0392b'; status = 'Too short';
				} else if (len >= cfg.ideal[0] && len <= cfg.ideal[1]) {
					color = '#27ae60'; status = 'Optimal';
				} else if (len <= cfg.max) {
					color = '#e67e22'; status = 'Acceptable';
				} else {
					color = '#c0392b'; status = 'Too long';
				}
				counter.innerHTML = '<span style="color:' + color + ';">' + len + ' chars — ' + status +
					' (ideal ' + cfg.ideal[0] + '–' + cfg.ideal[1] + ')</span>';
			}

			field.addEventListener('input', update);
			// Run on load if editing an existing post
			update();
		});
	}

	/* ===================================================================
	 * Post Form — JS-side validation on submit
	 * Checks: banned words, hyperlinks, SEO fields, word count
	 * Uses dynamic limits from admin settings
	 * =================================================================== */
	function initPostFormValidation() {
		var form = document.getElementById('oswp-post-form');
		if (!form) return;

		// Get limits from localized data or use defaults
		var limits = (typeof oswpCharLimits !== 'undefined') ? oswpCharLimits : {};
		var seoTitleMax = limits.seo_title_limit || 70;
		var metaDescMax = limits.seo_meta_desc_limit || 170;
		var contentMinWords = limits.post_content_min || 800;
		var contentMaxWords = limits.post_content_max || 2000;

		// Real-time validation for SEO title
		var seoTitleEl = document.getElementById('_yoast_wpseo_title');
		if (seoTitleEl) {
			seoTitleEl.addEventListener('input', function() {
				validateSeoTitle(this, seoTitleMax, limits);
			});
		}

		// Real-time validation for meta description
		var metaDescEl = document.getElementById('_yoast_wpseo_metadesc');
		if (metaDescEl) {
			metaDescEl.addEventListener('input', function() {
				validateMetaDescription(this, metaDescMax, limits);
			});
		}

		form.addEventListener('submit', function (e) {
			var errors = [];

			// ── 1. Banned words in title ──────────────────────────────
			var titleEl = document.getElementById('post_title');
			if (titleEl) {
				oswpClearInlineError('oswp-err-title-banned');
				var found = oswpContainsBannedWord(titleEl.value);
				if (found && found.length) {
					var msg = 'Title contains prohibited word' + (found.length>1? 's':'') + ': ' + found.join(', ') + '. Please remove ' + (found.length>1? 'them':'it') + '.';
					oswpShowInlineError(titleEl, msg, 'oswp-err-title-banned');
					errors.push(msg);
				}
			}

			// ── 2. Banned words in SEO fields ─────────────────────────
			['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw'].forEach(function (fid) {
				var el = document.getElementById(fid);
				if (!el) return;
				var errId = 'oswp-err-' + fid + '-banned';
				oswpClearInlineError(errId);
				var found = oswpContainsBannedWord(el.value);
				if (found && found.length) {
					var fieldName = el.name.replace(/_/g, ' ').replace('yoast wpseo ', '').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
					var msg = fieldName + ' contains prohibited word' + (found.length>1? 's':'') + ': ' + found.join(', ') + '. Please remove ' + (found.length>1? 'them':'it') + '.';
					oswpShowInlineError(el, msg, errId);
					errors.push(msg);
				}
			});

			// ── 3. SEO Title length (using admin-configured max) ──────
			if (seoTitleEl && seoTitleEl.value.length > 0) {
				var errId = 'oswp-err-seo-title-len';
				oswpClearInlineError(errId);			// verify minimum length
			if ( limits.seo_title_min && seoTitleEl.value.length < limits.seo_title_min ) {
				var msg = 'SEO Title must be at least ' + limits.seo_title_min + ' characters.';
				oswpShowInlineError(seoTitleEl, msg, errId);
				errors.push(msg);
			}
				var slen = seoTitleEl.value.length;
				if (slen < 10) {
					var msg = 'SEO Title is too short (minimum 10 characters).';
					oswpShowInlineError(seoTitleEl, msg, errId);
					errors.push(msg);
				} else if (slen > seoTitleMax) {
					var msg = 'SEO Title is too long (' + slen + ' characters). Maximum is ' + seoTitleMax + '.';
					oswpShowInlineError(seoTitleEl, msg, errId);
					errors.push(msg);
				}
			}

			// ── 4. Meta Description length (using admin-configured max) 
			if (metaDescEl && metaDescEl.value.length > 0) {
				var errId = 'oswp-err-meta-desc-len';
				oswpClearInlineError(errId);
				var dlen = metaDescEl.value.length;
                                var metaMinLen = limits.seo_meta_desc_min || 150;
				if (dlen < metaMinLen) {
					var msg = 'Meta Description is too short (minimum ' + metaMinLen + ' characters).';
					oswpShowInlineError(metaDescEl, msg, errId);
					errors.push(msg);
				} else if (dlen > metaDescMax) {
					var msg = 'Meta Description is too long (' + dlen + ' characters). Maximum is ' + metaDescMax + '.';
					oswpShowInlineError(metaDescEl, msg, errId);
					errors.push(msg);
				}
			}

			// ── 5. Content: banned words + hyperlinks ─────────────────
			var rawContent = oswpGetEditorContent('post_content');
			var plainContent = rawContent.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ');
			var contentErrEl = document.getElementById('post_content') || form;

			// Banned words in content
			var errIdContent = 'oswp-err-content-banned';
			oswpClearInlineError(errIdContent);
			var foundInContent = oswpContainsBannedWord(plainContent);
		if (foundInContent && foundInContent.length) {
			var msg = 'Content contains prohibited word' + (foundInContent.length>1? 's':'') + ': ' + foundInContent.join(', ') + '. Please remove ' + (foundInContent.length>1? 'them':'it') + '.';
				oswpShowInlineError(document.getElementById('post_content') || document.getElementById('oswp-word-count') || form, msg, errIdContent);
			// Hyperlink count in content
			var errIdLinks = 'oswp-err-content-links';
			oswpClearInlineError(errIdLinks);
			var linkCount = oswpCountLinks(rawContent);
			if (linkCount > 1) {
				var msg = 'Your content contains ' + linkCount + ' hyperlinks. Only 1 hyperlink is allowed in the entire article.';
				var anchorEl = document.getElementById('oswp-word-count') || document.getElementById('post_content') || form;
				oswpShowInlineError(anchorEl, msg, errIdLinks);
				errors.push(msg);
			}

			// ── 6. Block submission if errors ─────────────────────────
			if (errors.length > 0) {
				e.preventDefault();
				// display flash box with all errors
				// remove any existing flash messages first
				var existingFlash = form.querySelector('.oswp-form__flash');
				if (existingFlash) {
					existingFlash.remove();
				}
				var flash = document.createElement('div');
				flash.className = 'oswp-notice oswp-notice--error oswp-form__flash';
				errors.forEach(function(msg) {
					var p = document.createElement('p');
					p.textContent = msg;
					flash.appendChild(p);
				});
				form.insertBefore(flash, form.firstChild);
				// Scroll to the first inline error
				var firstErr = form.querySelector('.oswp-form__error--js');
				if (firstErr) {
					firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}
		});
	}

	// Real-time validation for SEO title
	function validateSeoTitle(el, max, limits) {
		var parent = el.closest('.oswp-form__group') || el.parentNode;
		var len = el.value.length;
		var min = limits.seo_title_min || 10;
		
		// Remove old state
		el.classList.remove('oswp-form__input--valid', 'oswp-form__input--warning', 'oswp-form__input--error');
		oswpClearFieldStatus(parent, 'oswp-form__status-bar');
		
		if (len === 0) {
			return; // Empty field, no validation
		}
		
		if (len < min || len > max) {
			el.classList.add('oswp-form__input--warning');
			addFieldStatusBar(parent, 'warning', 'SEO Title: ' + len + ' characters (optimal: ' + min + '-' + max + ')');
		} else {
			el.classList.add('oswp-form__input--valid');
			addFieldStatusBar(parent, 'valid', 'SEO Title length: Good ✓');
		}
	}

	// Real-time validation for meta description
	function validateMetaDescription(el, max, limits) {
		var parent = el.closest('.oswp-form__group') || el.parentNode;
		var len = el.value.length;
		var min = limits.seo_meta_desc_min || 150;
		
		// Remove old state
		el.classList.remove('oswp-form__input--valid', 'oswp-form__input--warning', 'oswp-form__input--error');
		oswpClearFieldStatus(parent, 'oswp-form__status-bar');
		
		if (len === 0) {
			return; // Empty field, no validation
		}
		
		if (len < min || len > max) {
			el.classList.add('oswp-form__input--warning');
			addFieldStatusBar(parent, 'warning', 'Meta Description: ' + len + ' characters (required: ' + min + '-' + max + ')');
		} else {
			el.classList.add('oswp-form__input--valid');
			addFieldStatusBar(parent, 'valid', 'Meta Description: Good ✓');
		}
	}

	// Add visual status bar under field
	function addFieldStatusBar(parent, status, message) {
		oswpClearFieldStatus(parent, 'oswp-form__status-bar');
		var bar = document.createElement('div');
		bar.className = 'oswp-form__status-bar oswp-form__status-bar--' + status;
		bar.textContent = message;
		parent.appendChild(bar);
	}

	// Clear status bars
	function oswpClearFieldStatus(parent, className) {
		var existing = parent.querySelector('.' + className);
		if (existing) {
			existing.remove();
		}
	}

	// Initialise new modules
	document.addEventListener('DOMContentLoaded', function () {
		initSeoCharCounters();
		initPostFormValidation();
	});

	// Make functions available globally if needed
	window.oswpFrontend = {
		validateForm: validateForm,
		calculatePasswordStrength: calculatePasswordStrength,
		containsBannedWord: oswpContainsBannedWord
	};
})();
