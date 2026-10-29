(function () {
	'use strict';

	function endpoint() {
		if (window.rwgcmCacheSafePrice && window.rwgcmCacheSafePrice.endpoint) {
			return String(window.rwgcmCacheSafePrice.endpoint);
		}
		return '';
	}

	function readItems(nodes) {
		var items = [];
		var i;
		for (i = 0; i < nodes.length; i++) {
			var raw = nodes[i].getAttribute('data-rwgcm-price-ajax') || '';
			var query = {};
			try {
				query = JSON.parse(raw);
			} catch (e) {
				query = {};
			}
			if (!query || typeof query !== 'object') {
				query = {};
			}
			delete query.country;
			query.index = i;
			items.push(query);
		}
		return items;
	}

	function request(url, done) {
		var attempts = 0;
		function send() {
			attempts++;
			window.fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' }
			}).then(function (response) {
				if (response.status === 429 && attempts < 2) {
					var retry = parseInt(response.headers.get('Retry-After') || '1', 10);
					if (!retry || retry < 1) {
						retry = 1;
					}
					if (retry > 2) {
						retry = 2;
					}
					window.setTimeout(send, retry * 1000);
					return null;
				}
				if (!response.ok) {
					return null;
				}
				return response.json();
			}).then(function (body) {
				if (body) {
					done(body);
				}
			}).catch(function () {});
		}
		send();
	}

	function apply(nodes, body) {
		var prices = body && body.prices ? body.prices : [];
		var i;
		for (i = 0; i < nodes.length; i++) {
			var row = prices[i];
			if (!row || typeof row.html !== 'string' || row.html === '') {
				continue;
			}
			nodes[i].insertAdjacentHTML('afterend', row.html);
			if (nodes[i].parentNode) {
				nodes[i].parentNode.removeChild(nodes[i]);
			}
		}
	}

	function run() {
		var root = endpoint();
		if (!root || !window.fetch) {
			return;
		}
		var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-rwgcm-price-ajax]'));
		if (!nodes.length) {
			return;
		}
		var size = 12;
		var offset;
		for (offset = 0; offset < nodes.length; offset += size) {
			(function (group) {
				var items = readItems(group);
				var joiner = root.indexOf('?') === -1 ? '?' : '&';
				var url = root + joiner + 'items=' + encodeURIComponent(JSON.stringify(items));
				request(url, function (body) {
					apply(group, body);
				});
			})(nodes.slice(offset, offset + size));
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
