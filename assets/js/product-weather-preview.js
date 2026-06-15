(function ($) {
	'use strict';

	function selectedProductFacets() {
		var out = [];
		$('input[name="rwgcm_weather_facets[]"]:checked').each(function () {
			out.push(String($(this).val()));
		});
		return out;
	}

	function activeVisitorFacets() {
		if ($('#rwgcm-weather-preview-simulate').is(':checked')) {
			var sim = [];
			$('input[name="rwgcm_preview_visitor_facets[]"]:checked').each(function () {
				sim.push(String($(this).val()));
			});
			return sim;
		}
		var live = window.rwgcmProductWeatherPreview && window.rwgcmProductWeatherPreview.visitorFacets;
		return Array.isArray(live) ? live.slice() : [];
	}

	function updatePreview() {
		var $status = $('#rwgcm-weather-preview-status');
		if (!$status.length) {
			return;
		}
		var product = selectedProductFacets();
		var visitor = activeVisitorFacets();
		var i18n = (window.rwgcmProductWeatherPreview && window.rwgcmProductWeatherPreview.i18n) || {};

		if (!product.length) {
			$status.text(i18n.noProductFacets || '');
			$status.removeClass('rwgcm-weather-preview--match rwgcm-weather-preview--nomatch');
			return;
		}
		if (!visitor.length) {
			$status.text(i18n.noVisitorFacets || '');
			$status.removeClass('rwgcm-weather-preview--match rwgcm-weather-preview--nomatch');
			return;
		}

		var overlap = product.filter(function (slug) {
			return visitor.indexOf(slug) !== -1;
		});

		if (overlap.length) {
			var labels = overlap.map(function (slug) {
				var map = (window.rwgcmProductWeatherPreview && window.rwgcmProductWeatherPreview.labels) || {};
				return map[slug] || slug;
			});
			$status
				.addClass('rwgcm-weather-preview--match')
				.removeClass('rwgcm-weather-preview--nomatch')
				.text(
					(i18n.match || 'Would match storefront for: %s').replace('%s', labels.join(', '))
				);
		} else {
			$status
				.addClass('rwgcm-weather-preview--nomatch')
				.removeClass('rwgcm-weather-preview--match')
				.text(i18n.noMatch || 'No overlap with visitor weather — product would not boost or match weather rules.');
		}
	}

	$(function () {
		var $box = $('.rwgcm-weather-preview');
		if (!$box.length) {
			return;
		}

		$box.on('change', 'input', updatePreview);

		$('#rwgcm-weather-preview-simulate').on('change', function () {
			$('.rwgcm-weather-preview-simulate-fields').toggle($(this).is(':checked'));
			updatePreview();
		});

		updatePreview();
	});
})(jQuery);
