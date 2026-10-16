/*
 * MBC slideshow: turns a Gallery block with the class "mbc-slideshow" into a slideshow.
 *
 * Progressive enhancement: without this script the gallery is an ordinary gallery. The frame is
 * fixed by CSS before this runs, so nothing shifts when it starts (css/slideshow.css).
 *
 * Interval (seconds, default 5): data-interval="6" on the gallery, or, because the block editor
 * would flag a hand-added attribute as invalid, the class "mbc-slideshow--6s" (Additional CSS
 * class). Label for screen readers: data-label, else "Photos from around the church".
 *
 * Behaviour: autoplays unless the visitor prefers reduced motion; pauses while the pointer is over
 * it, while focus is inside it, while the tab is hidden or it is off screen; Pause/Play, Previous,
 * Next and dot buttons (44px or more); Left/Right arrow keys; touch or mouse swipe (horizontal
 * only, vertical scrolling is left to the browser). Only the active slide is exposed to assistive
 * technology and keyboard focus.
 */
(function () {
	'use strict';

	var DEFAULT_SECONDS = 5;
	var MIN_SECONDS = 2;
	var SWIPE_MIN = 40; // px of horizontal travel
	var SWIPE_RATIO = 1.5; // horizontal travel must beat vertical by this factor
	var reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

	var ICON = {
		prev: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15.4 5.4 14 4l-8 8 8 8 1.4-1.4L8.8 12z"/></svg>',
		next: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m8.6 5.4 1.4-1.4 8 8-8 8-1.4-1.4 6.6-6.6z"/></svg>',
		pause: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>',
		play: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 4v16l13-8z"/></svg>'
	};

	function seconds(root) {
		var value = parseFloat(root.getAttribute('data-interval'));
		if (!value) {
			var m = /(?:^|\s)mbc-slideshow--(\d+(?:\.\d+)?)s(?:\s|$)/.exec(root.className);
			value = m ? parseFloat(m[1]) : DEFAULT_SECONDS;
		}
		return Math.max(MIN_SECONDS, Math.min(60, value || DEFAULT_SECONDS));
	}

	function button(className, label, icon) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'mbc-slideshow__btn ' + className;
		b.setAttribute('aria-label', label);
		if (icon) {
			b.innerHTML = icon;
		}
		return b;
	}

	function init(root) {
		var slides = Array.prototype.filter.call(root.children, function (el) {
			return el.tagName === 'FIGURE' && el.classList.contains('wp-block-image');
		});
		if (!slides.length) {
			return;
		}
		var count = slides.length;
		var index = 0;
		var timer = null;
		var delay = seconds(root) * 1000;
		var state = { userPaused: false, hover: false, focus: false, hidden: document.hidden, inView: true };
		var multiple = count > 1;

		root.classList.add('is-enhanced');
		// A mouse drag on a photo would start the browser's own image drag and cancel our swipe.
		Array.prototype.forEach.call(root.querySelectorAll('img'), function (img) {
			img.draggable = false;
		});
		root.setAttribute('role', 'region');
		root.setAttribute('aria-roledescription', 'carousel');
		root.setAttribute('aria-label', root.getAttribute('data-label') || 'Photos from around the church');

		slides.forEach(function (slide, i) {
			slide.setAttribute('role', 'group');
			slide.setAttribute('aria-roledescription', 'slide');
			slide.setAttribute('aria-label', (i + 1) + ' of ' + count);
		});

		var prev, next, pause, dots = [];
		if (multiple) {
			prev = button('mbc-slideshow__prev', 'Previous photo', ICON.prev);
			next = button('mbc-slideshow__next', 'Next photo', ICON.next);
			pause = button('mbc-slideshow__pause', 'Pause slideshow', ICON.pause);
			var bar = document.createElement('div');
			bar.className = 'mbc-slideshow__dots';
			slides.forEach(function (slide, i) {
				var d = button('mbc-slideshow__dot', 'Show photo ' + (i + 1) + ' of ' + count, '');
				d.addEventListener('click', function () { go(i); });
				dots.push(d);
				bar.appendChild(d);
			});
			root.appendChild(prev);
			root.appendChild(next);
			root.appendChild(pause);
			root.appendChild(bar);
			prev.addEventListener('click', function () { go(index - 1); });
			next.addEventListener('click', function () { go(index + 1); });
			pause.addEventListener('click', function () {
				state.userPaused = !state.userPaused;
				update();
			});
		}

		function show(i) {
			index = (i + count) % count;
			slides.forEach(function (slide, n) {
				var on = n === index;
				slide.classList.toggle('is-active', on);
				slide.setAttribute('aria-hidden', on ? 'false' : 'true');
				if ('inert' in slide) {
					slide.inert = !on;
				}
			});
			dots.forEach(function (d, n) {
				if (n === index) {
					d.setAttribute('aria-current', 'true');
				} else {
					d.removeAttribute('aria-current');
				}
			});
		}

		function running() {
			return multiple && !reduced.matches && !state.userPaused && !state.hover && !state.focus && !state.hidden && state.inView;
		}

		function update() {
			clearTimeout(timer);
			timer = null;
			var playing = running();
			// Announce changes only when the visitor is steering (not while it plays by itself).
			root.setAttribute('aria-live', playing ? 'off' : 'polite');
			if (pause) {
				pause.hidden = reduced.matches; // no autoplay under reduced motion, so nothing to pause
				var paused = state.userPaused;
				pause.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
				pause.innerHTML = paused ? ICON.play : ICON.pause;
			}
			if (playing) {
				timer = setTimeout(function () { go(index + 1); }, delay);
			}
		}

		function go(i) {
			show(i);
			update();
		}

		// Pause while the pointer is over it or focus is inside it.
		// (Real mice only: a tap on a touch screen also fires mouse events and would pin "hover" on.
		// Likewise only keyboard focus pauses; tapping a button must not stop it for good.)
		root.addEventListener('pointerenter', function (e) {
			if (e.pointerType === 'mouse') { state.hover = true; update(); }
		});
		root.addEventListener('pointerleave', function (e) {
			if (e.pointerType === 'mouse') { state.hover = false; update(); }
		});
		root.addEventListener('focusin', function (e) {
			var keyboard = !e.target.matches || !window.CSS || !CSS.supports('selector(:focus-visible)') || e.target.matches(':focus-visible');
			if (keyboard) { state.focus = true; update(); }
		});
		root.addEventListener('focusout', function (e) {
			if (!e.relatedTarget || !root.contains(e.relatedTarget)) {
				state.focus = false;
				update();
			}
		});
		document.addEventListener('visibilitychange', function () { state.hidden = document.hidden; update(); });
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				state.inView = entries[entries.length - 1].isIntersecting;
				update();
			}, { threshold: 0.25 }).observe(root);
		}
		if (reduced.addEventListener) {
			reduced.addEventListener('change', update);
		}

		// Keyboard: Left and Right move between photos while focus is inside.
		root.addEventListener('keydown', function (e) {
			if (!multiple || e.altKey || e.ctrlKey || e.metaKey) {
				return;
			}
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				go(index - 1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				go(index + 1);
			}
		});

		// Swipe: pointer events. touch-action: pan-y (CSS) leaves vertical scrolling to the browser,
		// which cancels the pointer if the gesture turns out to be vertical.
		var start = null;
		root.addEventListener('pointerdown', function (e) {
			if (!multiple || (e.pointerType === 'mouse' && e.button !== 0) || e.target.closest('button')) {
				return;
			}
			start = { id: e.pointerId, x: e.clientX, y: e.clientY };
		});
		root.addEventListener('pointerup', function (e) {
			if (!start || start.id !== e.pointerId) {
				return;
			}
			var dx = e.clientX - start.x;
			var dy = e.clientY - start.y;
			start = null;
			if (Math.abs(dx) >= SWIPE_MIN && Math.abs(dx) > Math.abs(dy) * SWIPE_RATIO) {
				go(dx < 0 ? index + 1 : index - 1);
			}
		});
		['pointercancel', 'pointerleave'].forEach(function (type) {
			root.addEventListener(type, function () { start = null; });
		});

		show(0);
		update();
	}

	function ready() {
		Array.prototype.forEach.call(document.querySelectorAll('.mbc-slideshow'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
