(function () {
	'use strict';

	if (typeof window.rwgcmConditionBuilder === 'undefined') {
		return;
	}

	var cfg = window.rwgcmConditionBuilder;
	var fields = cfg.fields || {};
	var groups = cfg.groups || {};
	var sources = cfg.sources || {};
	var operatorLabels = cfg.operatorLabels || {};

	function el(tag, className, html) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (html !== undefined) {
			node.innerHTML = html;
		}
		return node;
	}

	function option(value, label, selected) {
		var opt = document.createElement('option');
		opt.value = value;
		opt.textContent = label;
		if (selected) {
			opt.selected = true;
		}
		return opt;
	}

	function buildFieldSelect(selectedField) {
		var select = el('select', 'rwgcm-cond-field regular-text');
		select.name = 'rwgcm_cond_field[]';
		select.appendChild(option('', cfg.i18n.chooseField || '(choose condition)', false));

		Object.keys(groups).forEach(function (groupKey) {
			var og = document.createElement('optgroup');
			og.label = groups[groupKey];
			Object.keys(fields).forEach(function (fieldKey) {
				var field = fields[fieldKey];
				if (!field || field.group !== groupKey) {
					return;
				}
				og.appendChild(option(field.id, field.label, selectedField === field.id || selectedField === field.target));
			});
			if (og.children.length) {
				select.appendChild(og);
			}
		});
		return select;
	}

	function buildOperatorSelect(fieldDef, selectedOp) {
		var select = el('select', 'rwgcm-cond-operator');
		select.name = 'rwgcm_cond_operator[]';
		var ops = fieldDef && fieldDef.operators ? fieldDef.operators : ['is'];
		ops.forEach(function (op) {
			select.appendChild(option(op, operatorLabels[op] || op, selectedOp === op));
		});
		return select;
	}

	function buildValueInput(fieldDef, currentValue, currentLabel) {
		var wrap = el('div', 'rwgcm-cond-value-wrap');
		var hiddenLabel = el('input');
		hiddenLabel.type = 'hidden';
		hiddenLabel.name = 'rwgcm_cond_label[]';
		hiddenLabel.value = currentLabel || '';
		hiddenLabel.className = 'rwgcm-cond-label-hidden';
		wrap.appendChild(hiddenLabel);

		if (!fieldDef) {
			var text = el('input');
			text.type = 'text';
			text.name = 'rwgcm_cond_value[]';
			text.className = 'regular-text';
			text.value = currentValue || '';
			wrap.appendChild(text);
			return wrap;
		}

		var type = fieldDef.value_type || 'text';
		var sourceKey = fieldDef.value_source || '';
		var source = sourceKey && sources[sourceKey] ? sources[sourceKey] : null;

		if (source && (type === 'country_select' || type === 'currency_select' || type === 'product_cat_select' || type === 'product_tag_select' || type === 'role_select' || type === 'select' || type === 'boolean_select')) {
			var select = el('select', 'rwgcm-cond-value regular-text');
			select.name = 'rwgcm_cond_value[]';
			select.appendChild(option('', cfg.i18n.chooseValue || '(choose value)', false));
			Object.keys(source).forEach(function (key) {
				select.appendChild(option(key, source[key], String(currentValue) === String(key)));
			});
			select.addEventListener('change', function () {
				hiddenLabel.value = select.selectedIndex > 0 ? select.options[select.selectedIndex].text : '';
			});
			if (currentValue && !hiddenLabel.value && source[String(currentValue)]) {
				hiddenLabel.value = source[String(currentValue)];
			}
			wrap.appendChild(select);
			return wrap;
		}

		var input = el('input');
		input.type = type === 'numeric' ? 'number' : 'text';
		input.name = 'rwgcm_cond_value[]';
		input.className = 'regular-text';
		input.value = currentValue || '';
		input.addEventListener('change', function () {
			hiddenLabel.value = input.value;
		});
		wrap.appendChild(input);
		return wrap;
	}

	function syncRow(row) {
		var fieldSelect = row.querySelector('.rwgcm-cond-field');
		if (!fieldSelect) {
			return;
		}
		var fieldId = fieldSelect.value;
		var fieldDef = fields[fieldId] || null;
		var opCell = row.querySelector('.rwgcm-cond-operator-cell');
		var valCell = row.querySelector('.rwgcm-cond-value-cell');
		if (!opCell || !valCell) {
			return;
		}
		var currentOp = row.getAttribute('data-operator') || 'is';
		var currentVal = row.getAttribute('data-value') || '';
		var currentLabel = row.getAttribute('data-label') || '';
		opCell.innerHTML = '';
		valCell.innerHTML = '';
		opCell.appendChild(buildOperatorSelect(fieldDef, currentOp));
		valCell.appendChild(buildValueInput(fieldDef, currentVal, currentLabel));
		row.removeAttribute('data-operator');
		row.removeAttribute('data-value');
		row.removeAttribute('data-label');
	}

	function initRow(row) {
		var fieldSelect = row.querySelector('.rwgcm-cond-field');
		if (fieldSelect) {
			fieldSelect.addEventListener('change', function () {
				syncRow(row);
			});
		}
		syncRow(row);
	}

	function addRow(tableBody) {
		var row = el('tr', 'rwgcm-condition-row');
		var fieldCell = el('td');
		fieldCell.appendChild(buildFieldSelect(''));
		var opCell = el('td', 'rwgcm-cond-operator-cell');
		var valCell = el('td', 'rwgcm-cond-value-cell');
		var removeCell = el('td');
		var removeBtn = el('button', 'button-link-delete', cfg.i18n.remove || 'Remove');
		removeBtn.type = 'button';
		removeBtn.addEventListener('click', function () {
			row.remove();
		});
		removeCell.appendChild(removeBtn);
		row.appendChild(fieldCell);
		row.appendChild(opCell);
		row.appendChild(valCell);
		row.appendChild(removeCell);
		tableBody.appendChild(row);
		initRow(row);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var table = document.getElementById('rwgcm-conditions-table');
		if (!table) {
			return;
		}
		var tbody = table.querySelector('tbody');
		if (!tbody) {
			return;
		}
		tbody.querySelectorAll('.rwgcm-condition-row').forEach(initRow);
		var addBtn = document.getElementById('rwgcm-add-condition');
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				addRow(tbody);
			});
		}
	});
})();
