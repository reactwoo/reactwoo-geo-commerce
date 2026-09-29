/**
 * Fill Elementor Atomic weather widgets in the editor canvas.
 *
 * The canvas paints Twig. This script replaces that shell with the same HTML
 * the classic widget prints, including inside same-origin preview iframes.
 */
(function () {
	'use strict';

	var cfg = window.rwgcmAtomicPreview || {};
	if (!cfg.rest || !cfg.nonce) {
		return;
	}

	function scalar(value, fallback) {
		if (value == null) {
			return fallback;
		}
		if (typeof value === 'object') {
			if (Object.prototype.hasOwnProperty.call(value, 'value')) {
				return scalar(value.value, fallback);
			}
			return fallback;
		}
		return value;
	}

	function readSettings(el) {
		var raw = el.getAttribute('data-rwgcm-settings') || '{}';
		var parsed = {};
		try {
			parsed = JSON.parse(raw);
		} catch (e) {
			parsed = {};
		}
		var out = {};
		Object.keys(parsed).forEach(function (key) {
			out[key] = scalar(parsed[key], '');
		});
		return out;
	}

	function ensureStyle(doc, id, cssText) {
		if (!doc || doc.getElementById(id)) {
			return;
		}
		var style = doc.createElement('style');
		style.id = id;
		style.textContent = cssText;
		(doc.head || doc.documentElement).appendChild(style);
	}

	function ensureLink(doc, href) {
		if (!doc || !href) {
			return;
		}
		var links = doc.querySelectorAll('link[rel="stylesheet"]');
		for (var i = 0; i < links.length; i++) {
			if (links[i].href === href) {
				return;
			}
		}
		var link = doc.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		(doc.head || doc.documentElement).appendChild(link);
	}

	function paint(el) {
		var widget = el.getAttribute('data-rwgcm-atomic') || '';
		if (!widget || el.getAttribute('data-rwgcm-loading') === '1') {
			return;
		}
		var settings = readSettings(el);
		var signature = widget + ':' + JSON.stringify(settings);
		if (el.getAttribute('data-rwgcm-filled') === signature) {
			return;
		}
		el.setAttribute('data-rwgcm-loading', '1');
		var doc = el.ownerDocument;
		ensureStyle(
			doc,
			'rwgcm-atomic-shell-style',
			'.rwgcm-atomic-shell{padding:16px;border:1px dashed #cbd5e1;border-radius:12px;font:14px/1.4 sans-serif;color:#334155}'
		);
		(cfg.styles || []).forEach(function (href) {
			ensureLink(doc, href);
		});
		fetch(cfg.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify({ widget: widget, settings: settings })
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				el.removeAttribute('data-rwgcm-loading');
				if (!data || typeof data.html !== 'string') {
					return;
				}
				if (data.html) {
					el.innerHTML = data.html;
				} else {
					el.innerHTML = '<p class="rwgcm-atomic-shell__empty">Nothing to show for this visitor.</p>';
				}
				el.setAttribute('data-rwgcm-filled', signature);
			})
			.catch(function () {
				el.removeAttribute('data-rwgcm-loading');
			});
	}

	function boot(doc) {
		if (!doc || !doc.querySelectorAll) {
			return;
		}
		var nodes = doc.querySelectorAll('[data-rwgcm-atomic]');
		for (var i = 0; i < nodes.length; i++) {
			paint(nodes[i]);
		}
	}

	function scan(rootDoc) {
		boot(rootDoc);
		var frames = rootDoc.querySelectorAll('iframe');
		for (var i = 0; i < frames.length; i++) {
			try {
				if (frames[i].contentDocument) {
					boot(frames[i].contentDocument);
					watch(frames[i].contentDocument);
				}
			} catch (e) {
				/* cross-origin frame */
			}
		}
	}

	var watched = typeof WeakSet === 'function' ? new WeakSet() : null;

	function watch(doc) {
		if (!doc || !doc.documentElement || typeof MutationObserver !== 'function') {
			return;
		}
		if (watched) {
			if (watched.has(doc)) {
				return;
			}
			watched.add(doc);
		}
		var timer = 0;
		var observer = new MutationObserver(function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				boot(doc);
			}, 80);
		});
		observer.observe(doc.documentElement, { childList: true, subtree: true });
	}

	function start() {
		scan(document);
		watch(document);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
