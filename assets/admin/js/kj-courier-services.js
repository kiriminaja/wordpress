(function (window, document) {
	'use strict';

	var strings = window.kiriofCourierServicesI18n;
	var sequence = 0;
	function copy(value) { return JSON.parse(JSON.stringify(value)); }
	function supported(code, row) {
		row = row || {};
		code = String(code).trim().toLowerCase();
		return code !== '' && ['ninja_inter', 'gosend', 'grab_express', 'borzo'].indexOf(code) === -1 &&
			['instant', 'international'].indexOf(String(row.type || '').trim().toLowerCase()) === -1 &&
			String(row.region || '').trim().toLowerCase() !== 'international';
	}
	function node(tag, className, text) {
		var result = document.createElement(tag);
		if (className) { result.className = className; }
		if (text !== undefined) { result.textContent = text; }
		return result;
	}
	function create(element, options) {
		options = options || {};
		var data = options.data || options;
		function allowed(code) {
			return supported(code) && !(data.couriers || []).some(function (row) {
				return String(row.code).trim().toLowerCase() === String(code).trim().toLowerCase() && !supported(code, row);
			});
		}
		function cleanSelection(value) {
			var result = Object.create(null);
			Object.keys(value || {}).forEach(function (code) { if (allowed(code)) { result[code] = value[code]; } });
			return result;
		}
		var couriers = (data.couriers || []).filter(function (row) { return supported(row.code, row); });
		var selection = Object.create(null);
		var remembered = Object.create(null);
		var rows = [];
		var disabled = false;
		var prefix = 'kiriof-services-' + (++sequence) + '-';
		var legacy = data.service_selection == null;
		var ids = (data.whitelist_ids || []).filter(allowed);
		var hadLegacyIds = (data.whitelist_ids || []).length > 0;
		if (legacy) {
			ids.forEach(function (id) {
				if (!couriers.some(function (courier) { return String(courier.code) === String(id); })) {
					couriers.push({ code: String(id), name: String(id), services: [] });
				}
			});
		}

		if (!legacy) {
			Object.keys(data.service_selection).forEach(function (code) {
				if (allowed(code) && Array.isArray(data.service_selection[code])) {
					selection[code] = data.service_selection[code].map(String);
				}
			});
			// Keep historical couriers visible even when the current catalog omits them.
			Object.keys(selection).forEach(function (code) {
				if (!couriers.some(function (courier) { return String(courier.code) === code; })) {
					couriers.push({ code: code, name: code, services: [] });
				}
			});
		}
		function selected(code) { return selection[code] || []; }
		function codes(courier) { return courier.services.map(function (service) { return service.code; }); }
		function countText(count, total) { return strings.enabledCount.replace('%1$s', count).replace('%2$s', total); }
		function checkbox(label, accessibleName) {
			var wrapper = node('label', 'kiriof-services__toggle');
			var input = node('input');
			input.type = 'checkbox';
			input.id = prefix + (++sequence);
			wrapper.htmlFor = input.id;
			input.setAttribute('aria-label', accessibleName);
			wrapper.appendChild(input);
			var track = node('span', 'kiriof-services__track');
			track.setAttribute('aria-hidden', 'true');
			wrapper.appendChild(track);
			if (label) { wrapper.appendChild(label); }
			return { label: wrapper, input: input };
		}
		function sync() {
			var enabled = 0;
			rows.forEach(function (row) {
				var values = selected(row.courier.code);
				var count = row.services.filter(function (item) { return values.indexOf(item.code) !== -1; }).length;
				row.parent.checked = values.length > 0;
				row.parent.indeterminate = count > 0 && count < row.services.length;
				row.parent.disabled = disabled;
				row.count.textContent = countText(count, row.services.length);
				row.services.forEach(function (item) {
					item.input.checked = values.indexOf(item.code) !== -1;
					item.input.disabled = disabled;
				});
				if (values.length) { enabled++; }
			});
			element.setAttribute('aria-busy', disabled ? 'true' : 'false');
			if (options.countElement) { options.countElement.textContent = countText(enabled, rows.length); }
		}
		function changed() {
			sync();
			if (typeof options.onChange === 'function') { options.onChange(api); }
		}
		element.textContent = '';
		element.classList.add('kiriof-services');
		couriers.forEach(function (raw) {
			var courier = { code: String(raw.code), name: String(raw.name || raw.code), services: raw.services || [] };
			if (!courier.services.length) { courier.services = [{ code: '*', name: strings.allServices }]; }
			courier.services = courier.services.map(function (service) {
				return { code: String(service.code), name: String(service.name || service.code), aliases: service.aliases || [] };
			});
			if (legacy && (!hadLegacyIds || ids.map(String).indexOf(courier.code) !== -1)) { selection[courier.code] = codes(courier); }
			if (!legacy && selection[courier.code]) {
				var values = selected(courier.code);
				if (values.indexOf('*') !== -1) { values = codes(courier).concat(values.filter(function (code) { return code !== '*'; })); }
				selection[courier.code] = values.map(function (code) {
					var normalized = code.toLowerCase();
					var match = courier.services.find(function (service) {
						return service.code.toLowerCase() === normalized || service.aliases.some(function (alias) { return String(alias).toLowerCase() === normalized; });
					});
					return match ? match.code : code;
				}).filter(function (code, index, list) { return list.indexOf(code) === index; });
				// Unknown saved codes are selectable fallbacks, not invisible state.
				selected(courier.code).forEach(function (code) {
					if (codes(courier).indexOf(code) === -1) { courier.services.push({ code: code, name: code, aliases: [] }); }
				});
			}
			var card = node('section', 'kiriof-services__card');
			var heading = node('div', 'kiriof-services__header');
			var identity = node('div', 'kiriof-services__identity');
			var badge = node('span', 'kiriof-services__badge', courier.code.toUpperCase());
			badge.setAttribute('aria-hidden', 'true');
			identity.appendChild(badge);
			var details = node('div');
			var title = node('h3', '', courier.name);
			title.id = prefix + 'title-' + rows.length;
			card.setAttribute('aria-labelledby', title.id);
			details.appendChild(title);
			var count = node('span', 'kiriof-services__count');
			details.appendChild(count);
			identity.appendChild(details);
			heading.appendChild(identity);
			var parent = checkbox(null, strings.enableCourier.replace('%s', courier.name));
			heading.appendChild(parent.label);
			card.appendChild(heading);
			var row = { courier: courier, parent: parent.input, count: count, services: [] };
			parent.input.addEventListener('change', function () {
				if (this.checked) { selection[courier.code] = (remembered[courier.code] || []).length ? remembered[courier.code].slice() : codes(courier); }
				else { remembered[courier.code] = selected(courier.code).slice(); delete selection[courier.code]; }
				changed();
			});
			courier.services.forEach(function (service) {
				var label = node('span', 'kiriof-services__service-name');
				label.appendChild(node('span', '', service.name));
				label.appendChild(node('small', 'kiriof-services__code', service.code));
				var toggle = checkbox(label, strings.enableService.replace('%1$s', service.name).replace('%2$s', courier.name));
				toggle.label.classList.add('kiriof-services__service');
				card.appendChild(toggle.label);
				row.services.push({ code: service.code, input: toggle.input });
				toggle.input.addEventListener('change', function () {
					var values = selected(courier.code).filter(function (code) { return code !== service.code; });
					if (this.checked) { values.push(service.code); }
					if (values.length) { selection[courier.code] = values; remembered[courier.code] = values.slice(); }
					else { remembered[courier.code] = selected(courier.code).slice(); delete selection[courier.code]; }
					changed();
				});
			});
			rows.push(row);
			element.appendChild(card);
		});
		// A temporarily unavailable legacy courier must not be silently discarded.
		if (legacy) { ids.forEach(function (id) { if (!rows.some(function (row) { return row.courier.code === String(id); })) { selection[String(id)] = ['*']; } }); }
		if (!rows.length) { element.appendChild(node('p', '', strings.noCouriers)); }
		var api = {
			getSelection: function () { return copy(selection); },
			getPayload: function () {
				var enabledIds = Object.keys(selection).filter(function (code) { return selection[code].length > 0; });
				return {
					service_selection: JSON.stringify(selection),
					whitelist_ids: enabledIds.join(','),
					whitelist_names: enabledIds.map(function (code) {
						var row = rows.find(function (item) { return item.courier.code === code; });
						return row ? row.courier.name : code;
					}).join(',')
				};
			},
			hasSelection: function () { return rows.some(function (row) { return row.services.some(function (item) { return selected(row.courier.code).indexOf(item.code) !== -1; }); }); },
			setAll: function (enabled) {
				if (disabled) { return; }
				if (!enabled) { remembered = Object.assign(Object.create(null), copy(selection)); selection = Object.create(null); }
				else { rows.forEach(function (row) { selection[row.courier.code] = codes(row.courier); }); }
				changed();
			},
			setDisabled: function (value) { disabled = !!value; sync(); },
			getState: function () { return { selection: copy(selection), remembered: copy(remembered) }; },
			setState: function (state) { selection = cleanSelection(copy(state.selection)); remembered = cleanSelection(copy(state.remembered)); sync(); }
		};
		sync();
		return api;
	}
	window.kiriofCourierServices = { create: create };
})(window, document);
