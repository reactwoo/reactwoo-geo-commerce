(function () {
	'use strict';

	function el(tag, className) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		return node;
	}

	function buildActionFields(type, index, data) {
		data = data || {};
		var wrap = el('div', 'rwgcm-action-fields');
		switch (type) {
			case 'price_adjustment':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.mode || 'Mode') +
					' <select name="rwgcm_action_pa_mode[' + index + ']">' +
					'<option value="percent"' + (data.mode === 'percent' ? ' selected' : '') + '>' + (window.rwgcmRuleBuilder?.i18n?.percent || 'Percent') + '</option>' +
					'<option value="fixed_line"' + (data.mode === 'fixed_line' ? ' selected' : '') + '>' + (window.rwgcmRuleBuilder?.i18n?.fixed || 'Fixed amount') + '</option>' +
					'</select></label> ' +
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.value || 'Value') +
					' <input type="text" name="rwgcm_action_pa_value[' + index + ']" value="' + (data.value || 0) + '" /></label>';
				break;
			case 'product_badge':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.badgeText || 'Badge text') +
					' <input type="text" class="regular-text" name="rwgcm_action_text[' + index + ']" value="' + (data.text || '') + '" /></label>';
				break;
			case 'product_notice':
			case 'shipping_notice':
			case 'stock_message':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.message || 'Message') +
					' <input type="text" class="large-text" name="rwgcm_action_text[' + index + ']" value="' + (data.text || '') + '" /></label>';
				break;
			case 'product_overlay':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.overlayField || 'Field') +
					' <select name="rwgcm_action_overlay_field[' + index + ']">' +
					'<option value="title">Title</option><option value="short_description">Short description</option><option value="description">Description</option><option value="badge">Badge</option><option value="cta">CTA</option>' +
					'</select></label> ' +
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.overlayValue || 'Value') +
					' <input type="text" class="large-text" name="rwgcm_action_overlay_value[' + index + ']" value="' + (data.value || '') + '" /></label>';
				break;
			case 'product_visibility':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.visibility || 'Visibility') +
					' <select name="rwgcm_action_visibility_mode[' + index + ']">' +
					'<option value="show"' + (data.mode !== 'hide' ? ' selected' : '') + '>Show</option>' +
					'<option value="hide"' + (data.mode === 'hide' ? ' selected' : '') + '>Hide</option>' +
					'</select></label>';
				break;
			case 'cta_override':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.cta || 'CTA HTML') +
					' <input type="text" class="large-text" name="rwgcm_action_cta_value[' + index + ']" value="' + (data.value || '') + '" /></label>';
				break;
			case 'custom_html':
				wrap.innerHTML =
					'<label>' + (window.rwgcmRuleBuilder?.i18n?.html || 'HTML') +
					' <textarea class="large-text" rows="3" name="rwgcm_action_html_value[' + index + ']">' + (data.value || '') + '</textarea></label>';
				break;
			default:
				break;
		}
		return wrap;
	}

	function syncSummary() {
		var box = document.getElementById('rwgcm-rule-summary-preview');
		if (!box || typeof window.rwgcmRuleBuilder === 'undefined' || !window.rwgcmRuleBuilder.summarySeed) {
			return;
		}
		var parts = [];
		document.querySelectorAll('#rwgcm-conditions-table tbody .rwgcm-condition-row').forEach(function (row) {
			var field = row.querySelector('.rwgcm-cond-field');
			var op = row.querySelector('.rwgcm-cond-operator');
			var val = row.querySelector('.rwgcm-cond-value');
			if (!field || !field.value || !val || !val.value) {
				return;
			}
			var fieldLabel = field.options[field.selectedIndex] ? field.options[field.selectedIndex].text : field.value;
			var opLabel = op && op.options[op.selectedIndex] ? op.options[op.selectedIndex].text : 'is';
			var valLabel = val.options && val.selectedIndex > 0 ? val.options[val.selectedIndex].text : val.value;
			parts.push(fieldLabel.toLowerCase() + ' ' + opLabel + ' ' + valLabel);
		});
		var actionParts = [];
		document.querySelectorAll('#rwgcm-actions-list .rwgcm-action-row').forEach(function (row) {
			var type = row.querySelector('.rwgcm-action-type');
			if (type && type.value) {
				actionParts.push(type.options[type.selectedIndex].text.toLowerCase());
			}
		});
		var when = parts.length ? parts.join(' and ') : window.rwgcmRuleBuilder.i18n.noConditions || 'always';
		var then = actionParts.length ? actionParts.join(', ') : window.rwgcmRuleBuilder.i18n.noActions || 'nothing';
		box.textContent = (window.rwgcmRuleBuilder.i18n.summaryPrefix || 'If') + ' ' + when + ', ' + then + '.';
	}

	function initActionRow(row, index) {
		var typeSelect = row.querySelector('.rwgcm-action-type');
		var fieldsWrap = row.querySelector('.rwgcm-action-fields-wrap');
		if (!typeSelect || !fieldsWrap) {
			return;
		}
		var data = {};
		try {
			data = JSON.parse(row.getAttribute('data-action') || '{}');
		} catch (e) {
			data = {};
		}
		fieldsWrap.innerHTML = '';
		fieldsWrap.appendChild(buildActionFields(typeSelect.value, index, data));
		typeSelect.addEventListener('change', function () {
			fieldsWrap.innerHTML = '';
			fieldsWrap.appendChild(buildActionFields(typeSelect.value, index, {}));
			syncSummary();
		});
		row.querySelectorAll('input,select,textarea').forEach(function (input) {
			input.addEventListener('change', syncSummary);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var list = document.getElementById('rwgcm-actions-list');
		if (!list) {
			return;
		}
		var index = list.querySelectorAll('.rwgcm-action-row').length;
		list.querySelectorAll('.rwgcm-action-row').forEach(function (row, i) {
			initActionRow(row, i);
		});
		var addBtn = document.getElementById('rwgcm-add-action');
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				var row = el('div', 'rwgcm-action-row');
				var typeSelect = el('select', 'rwgcm-action-type');
				typeSelect.name = 'rwgcm_action_type[]';
				if (window.rwgcmRuleBuilder && window.rwgcmRuleBuilder.actionOptions) {
					Object.keys(window.rwgcmRuleBuilder.actionOptions).forEach(function (key) {
						var opt = el('option');
						opt.value = key;
						opt.textContent = window.rwgcmRuleBuilder.actionOptions[key];
						typeSelect.appendChild(opt);
					});
				}
				var fieldsWrap = el('div', 'rwgcm-action-fields-wrap');
				var removeBtn = el('button', 'button-link-delete');
				removeBtn.type = 'button';
				removeBtn.textContent = window.rwgcmRuleBuilder?.i18n?.remove || 'Remove';
				removeBtn.addEventListener('click', function () {
					row.remove();
					syncSummary();
				});
				row.appendChild(typeSelect);
				row.appendChild(fieldsWrap);
				row.appendChild(removeBtn);
				list.appendChild(row);
				initActionRow(row, index);
				index += 1;
				syncSummary();
			});
		}
		document.querySelectorAll('#rwgcm-conditions-table').forEach(function (table) {
			table.addEventListener('change', syncSummary);
		});
		syncSummary();
	});
})();
