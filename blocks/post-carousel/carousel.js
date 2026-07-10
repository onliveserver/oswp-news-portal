/**
 * OSWP Post Carousel — Frontend JavaScript
 * Handles Slick initialization for card, overlay, hero, and ticker layouts.
 *
 * @package OSWP News Portal
 */
(function ($) {
	'use strict';

	/* ════════════════════════════════════════════
	   Master init — called on ready + mutations
	   ════════════════════════════════════════════ */
	function initAll() {
		initSliders();
		initTickers();
		initHeroSliders();
	}

	/* ────────────────────────────────────────────
	   Standard sliders  (card / overlay)
	   ──────────────────────────────────────────── */
	function initSliders() {
		$(
			'.oswp-post-carousel-wrapper[data-layout="card"],' +
			'.oswp-post-carousel-wrapper[data-layout="overlay"]'
		).each(function () {
			var $el = $(this);

			// Skip editor preview (no Slick in SSR context)
			if ($el.hasClass('oswp-editor-preview')) return;
			if ($el.hasClass('slick-initialized')) $el.slick('unslick');

			var settings = $el.data('slick');
			if (!settings) return;

			try {
				$el.slick(settings);
			} catch (e) {
				console.error('OSWP Slider init error:', e);
			}
		});
	}

	/* ────────────────────────────────────────────
	   Ticker  (breaking-news bar)
	   ──────────────────────────────────────────── */
	function initTickers() {
		$('.oswp-ticker__items').each(function () {
			var $el = $(this);
			var $wrap = $el.closest('.oswp-ticker-wrapper');

			if ($el.hasClass('slick-initialized')) $el.slick('unslick');

			var settings = $el.data('slick');
			if (!settings) return;

			try {
				$el.slick(settings);

				// External prev / next buttons
				$wrap
					.find('.oswp-ticker__prev')
					.off('click.oswp')
					.on('click.oswp', function () {
						$el.slick('slickPrev');
					});
				$wrap
					.find('.oswp-ticker__next')
					.off('click.oswp')
					.on('click.oswp', function () {
						$el.slick('slickNext');
					});
			} catch (e) {
				console.error('OSWP Ticker init error:', e);
			}
		});
	}

	/* ────────────────────────────────────────────
	   Hero slider
	   ──────────────────────────────────────────── */
	function initHeroSliders() {
		$('.oswp-hero__slider').each(function () {
			var $el = $(this);
			var $wrap = $el.closest('.oswp-hero-wrapper');

			if ($wrap.hasClass('oswp-editor-preview')) return;
			if ($el.hasClass('slick-initialized')) $el.slick('unslick');

			var settings = $el.data('slick');
			if (!settings) return;

			try {
				$el.slick(settings);

				// Image counter  [current / total]
				var $cur = $wrap.find('.oswp-hero__counter-current');
				if ($cur.length) {
					$el.on('afterChange', function (_e, _slick, slide) {
						$cur.text(slide + 1);
					});
				}
			} catch (e) {
				console.error('OSWP Hero init error:', e);
			}
		});
	}

	/* ════════════════════════════════════════════
	   Lifecycle hooks
	   ════════════════════════════════════════════ */

	// Document ready
	$(document).ready(initAll);

	// Resize — just let Slick recalculate
	var resizeTimer;
	$(window).on('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			$('.slick-initialized').each(function () {
				$(this).slick('resize');
			});
		}, 250);
	});

	// Gutenberg editor — MutationObserver for SSR re-renders
	if (window.wp && window.wp.data) {
		var lastCheck = 0;
		var observer = new MutationObserver(function () {
			var now = Date.now();
			if (now - lastCheck < 300) return;
			lastCheck = now;

			var needs = false;

			needs =
				needs ||
				$(
					'.oswp-post-carousel-wrapper:not(.slick-initialized):not(.oswp-editor-preview)'
				).length > 0;

			needs =
				needs ||
				$('.oswp-ticker__items:not(.slick-initialized)').length > 0;

			needs =
				needs ||
				$('.oswp-hero__slider:not(.slick-initialized)').filter(
					function () {
						return (
							!$(this).closest('.oswp-editor-preview').length
						);
					}
				).length > 0;

			if (needs) initAll();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
			characterData: false,
		});
	}
})(jQuery);

