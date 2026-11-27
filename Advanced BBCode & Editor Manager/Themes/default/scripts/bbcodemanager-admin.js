/**
 * Advanced BBCode & Editor Manager: admin panel
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 */
(function () {
	'use strict';

	function $(s, r) { return (r || document).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
	function esc(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	// Confirmation for delete / restore / import
	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-bbm-confirm]');
		if (el && !window.confirm(el.getAttribute('data-bbm-confirm')))
			e.preventDefault();
	});

	/* Tag builder ------------------------------------------------------ */

	function initTagForm(form) {
		var params = $('#bbm_params');
		var paramTpl = $('#bbm_param_template');
		var template = $('#bbm_template');
		var chips = $('#bbm_placeholders');
		var preview = $('#bbm_preview');
		var usage = $('#bbm_usage_example');
		var counter = 1000;
		var timer = null;

		function mode() {
			var checked = $('input[name="mode"]:checked', form);
			return checked ? checked.value : 'parsed';
		}

		function paramRows() {
			return $$('.bbm_param', params).map(function (fs) {
				return {
					el: fs,
					name: ($('.bbm_param_name', fs).value || '').trim().toLowerCase(),
					type: $('.bbm_param_type', fs).value,
					def: ($('input[name$="[default]"]', fs).value || '').trim(),
					required: $('input[name$="[required]"]', fs).checked
				};
			}).filter(function (p) { return p.name !== ''; });
		}

		function syncTypes() {
			$$('.bbm_param', params).forEach(function (fs) {
				fs.setAttribute('data-type', $('.bbm_param_type', fs).value);
			});
			$('#bbm_add_param').disabled = mode() === 'closed' || $$('.bbm_param', params).length >= 6;
		}

		function renderChips() {
			var names = mode() === 'closed' ? [] : ['content'];
			paramRows().forEach(function (p) { names.push(p.name); });
			chips.innerHTML = names.map(function (n) {
				return '<button type="button" class="bbm_chip" data-insert="{' + esc(n) + '}">{' + esc(n) + '}</button>';
			}).join('');
		}

		function renderUsage() {
			var tag = ($('#bbm_tag').value || 'tag').toLowerCase();
			var ps = paramRows();
			var opt = ps.map(function (p) { return p.def; }).join('|').replace(/\|+$/, '');
			var needs = ps.some(function (p) { return p.required; });
			var open = '[' + tag + (ps.length && (opt || needs) ? '=' + opt : '') + ']';
			usage.textContent = mode() === 'closed' ? open : open + '…[/' + tag + ']';
		}

		function refresh() {
			syncTypes();
			renderChips();
			renderUsage();
			schedulePreview();
		}

		function schedulePreview() {
			clearTimeout(timer);
			timer = setTimeout(runPreview, 450);
		}

		function runPreview() {
			var data = new FormData(form);
			data.delete('icon');
			preview.classList.add('bbm_loading');

			fetch(form.getAttribute('data-preview-url'), { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (html) {
					preview.innerHTML = html;
					preview.classList.remove('bbm_loading');
				})
				.catch(function () { preview.classList.remove('bbm_loading'); });
		}

		$('#bbm_add_param').addEventListener('click', function () {
			if ($$('.bbm_param', params).length >= 6)
				return;
			params.insertAdjacentHTML('beforeend', paramTpl.innerHTML.replace(/__i__/g, 'n' + (counter++)));
			var last = params.lastElementChild;
			if (last)
				$('.bbm_param_name', last).focus();
			refresh();
		});

		params.addEventListener('click', function (e) {
			if (e.target.classList.contains('bbm_remove_param')) {
				e.target.closest('.bbm_param').remove();
				refresh();
			}
		});

		chips.addEventListener('click', function (e) {
			var text = e.target.getAttribute('data-insert');
			if (!text)
				return;
			var start = template.selectionStart, end = template.selectionEnd;
			template.value = template.value.slice(0, start) + text + template.value.slice(end);
			template.focus();
			template.selectionStart = template.selectionEnd = start + text.length;
			schedulePreview();
		});

		$$('.bbm_starter', form).forEach(function (b) {
			b.addEventListener('click', function () {
				var html = b.getAttribute('data-template');
				if (template.value.trim() !== '' && template.value !== html && !window.confirm(b.textContent + '?'))
					return;
				template.value = html;
				refresh();
			});
		});

		form.addEventListener('input', function (e) {
			if (e.target.type !== 'file')
				refresh();
		});
		form.addEventListener('change', refresh);

		refresh();
	}

	/* Toolbar builder ---------------------------------------------------- */

	function initBuilder(root) {
		var buttons = JSON.parse(root.getAttribute('data-buttons') || '[]');
		var t = JSON.parse(root.getAttribute('data-txt') || '{}');
		var input = $('#bbm_toolbar_json');
		var info = {};
		var rows;
		var dragged = null;

		buttons.forEach(function (b) { info[b.code] = b; });
		try { rows = JSON.parse(input.value) || []; } catch (e) { rows = []; }

		root.innerHTML =
			'<div class="bbm_rows"></div>' +
			'<p><button type="button" class="button bbm_add_row">' + esc(t.add_row) + '</button> ' +
			'<button type="button" class="button bbm_reset">' + esc(t.reset) + '</button></p>' +
			'<div class="bbm_pool_wrap"><strong>' + esc(t.pool) + '</strong> ' +
			'<input type="search" class="bbm_pool_filter" placeholder="' + esc(t.filter) + '">' +
			'<div class="bbm_pool" data-zone="pool"></div></div>' +
			'<p class="smalltext">' + esc(t.empty) + '</p>';

		var rowsEl = $('.bbm_rows', root);
		var pool = $('.bbm_pool', root);
		var filter = $('.bbm_pool_filter', root);

		function chip(code, inRow) {
			var b = info[code];
			var el = document.createElement('span');
			el.className = 'bbm_chip' + (code === '|' ? ' bbm_chip_sep' : '') + (b && b.custom ? ' bbm_chip_custom' : '');
			el.draggable = true;
			el.tabIndex = 0;
			el.setAttribute('data-code', code);
			el.setAttribute('role', 'button');
			el.title = code === '|' ? t.separator : code;
			el.appendChild(document.createTextNode(code === '|' ? '⎮ ' + t.separator : (b ? b.label : code)));

			if (inRow) {
				var x = document.createElement('button');
				x.type = 'button';
				x.className = 'bbm_chip_x';
				x.setAttribute('aria-label', t.remove);
				x.textContent = '×';
				el.appendChild(x);
			}

			return el;
		}

		function save() {
			rows = $$('.bbm_row', rowsEl).map(function (r) {
				return $$('.bbm_chip', r).map(function (c) { return c.getAttribute('data-code'); });
			});
			input.value = JSON.stringify(rows.filter(function (r) { return r.length; }));
			renderPool();
		}

		function renderRows() {
			rowsEl.innerHTML = '';
			if (!rows.length)
				rows = [[]];

			rows.forEach(function (row, i) {
				var r = document.createElement('div');
				r.className = 'bbm_row';
				r.setAttribute('data-zone', 'row');
				r.setAttribute('aria-label', t.row + ' ' + (i + 1));
				row.forEach(function (code) { r.appendChild(chip(code, true)); });

				var del = document.createElement('button');
				del.type = 'button';
				del.className = 'bbm_row_x';
				del.setAttribute('aria-label', t.remove);
				del.textContent = '×';
				r.appendChild(del);

				rowsEl.appendChild(r);
			});

			save();
		}

		function renderPool() {
			var used = {};
			rows.forEach(function (r) { r.forEach(function (c) { used[c] = true; }); });
			var q = filter.value.trim().toLowerCase();

			pool.innerHTML = '';
			pool.appendChild(chip('|', false));
			buttons.forEach(function (b) {
				if (used[b.code])
					return;
				if (q && b.label.toLowerCase().indexOf(q) === -1 && b.code.indexOf(q) === -1)
					return;
				pool.appendChild(chip(b.code, false));
			});
		}

		function lastRow() {
			var all = $$('.bbm_row', rowsEl);
			return all[all.length - 1];
		}

		function addToRow(code, row) {
			row = row || lastRow();
			row.insertBefore(chip(code, true), $('.bbm_row_x', row));
			save();
		}

		function afterElement(zone, x, y) {
			var items = $$('.bbm_chip', zone).filter(function (c) { return c !== dragged; });
			for (var i = 0; i < items.length; i++) {
				var box = items[i].getBoundingClientRect();
				if (y < box.top + box.height / 2 && x < box.left + box.width / 2 || y < box.top)
					return items[i];
				if (y <= box.bottom && x < box.left + box.width / 2)
					return items[i];
			}
			return null;
		}

		root.addEventListener('dragstart', function (e) {
			var c = e.target.closest && e.target.closest('.bbm_chip');
			if (!c)
				return;
			dragged = c;
			c.classList.add('bbm_dragging');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', c.getAttribute('data-code'));
		});

		root.addEventListener('dragend', function () {
			if (dragged)
				dragged.classList.remove('bbm_dragging');
			dragged = null;
			$$('.bbm_drop', root).forEach(function (z) { z.classList.remove('bbm_drop'); });
		});

		root.addEventListener('dragover', function (e) {
			var zone = e.target.closest && e.target.closest('[data-zone]');
			if (!zone || !dragged)
				return;
			e.preventDefault();
			$$('.bbm_drop', root).forEach(function (z) { if (z !== zone) z.classList.remove('bbm_drop'); });
			zone.classList.add('bbm_drop');
		});

		root.addEventListener('drop', function (e) {
			var zone = e.target.closest && e.target.closest('[data-zone]');
			if (!zone || !dragged)
				return;
			e.preventDefault();
			zone.classList.remove('bbm_drop');

			var code = dragged.getAttribute('data-code');
			var fromPool = !!dragged.closest('.bbm_pool');

			if (zone.getAttribute('data-zone') === 'pool') {
				if (!fromPool)
					dragged.remove();
			}
			else {
				var node = fromPool ? chip(code, true) : dragged;
				var after = afterElement(zone, e.clientX, e.clientY);
				zone.insertBefore(node, after || $('.bbm_row_x', zone));
			}

			save();
		});

		root.addEventListener('click', function (e) {
			if (e.target.classList.contains('bbm_chip_x')) {
				e.target.parentNode.remove();
				save();
			}
			else if (e.target.classList.contains('bbm_row_x')) {
				e.target.parentNode.remove();
				save();
				if (!$$('.bbm_row', rowsEl).length)
					renderRows();
			}
			else if (e.target.classList.contains('bbm_add_row')) {
				if ($$('.bbm_row', rowsEl).length < 6) {
					rows.push([]);
					var keep = rows;
					renderRows();
					rows = keep;
				}
			}
			else if (e.target.classList.contains('bbm_reset')) {
				rows = [[]];
				renderRows();
			}
			else {
				var c = e.target.closest('.bbm_chip');
				if (c && c.closest('.bbm_pool'))
					addToRow(c.getAttribute('data-code'));
			}
		});

		// Keyboard: Enter adds from the pool; Delete removes; Alt+arrows move.
		root.addEventListener('keydown', function (e) {
			var c = e.target.closest && e.target.closest('.bbm_chip');
			if (!c)
				return;

			if (c.closest('.bbm_pool')) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					addToRow(c.getAttribute('data-code'));
				}
				return;
			}

			if (e.key === 'Delete' || e.key === 'Backspace') {
				e.preventDefault();
				c.remove();
				save();
			}
			else if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
				e.preventDefault();
				var sib = e.key === 'ArrowLeft' ? c.previousElementSibling : c.nextElementSibling;
				if (sib && sib.classList.contains('bbm_chip')) {
					if (e.key === 'ArrowLeft')
						c.parentNode.insertBefore(c, sib);
					else
						c.parentNode.insertBefore(sib, c);
					c.focus();
					save();
				}
			}
		});

		filter.addEventListener('input', renderPool);

		renderRows();
	}

	/* Allowed tags -------------------------------------------------------- */

	function initAllowed() {
		var all = $('#bbm_allow_all');
		var list = $('#bbm_allowed_list');
		if (!all || !list)
			return;

		function sync() {
			list.classList.toggle('bbm_disabled', all.checked);
			$$('input[type="checkbox"]', list).forEach(function (cb) { cb.disabled = all.checked; });
		}

		all.addEventListener('change', sync);
		$$('[data-bbm-check]', list).forEach(function (b) {
			b.addEventListener('click', function () {
				var on = b.getAttribute('data-bbm-check') === '1';
				$$('input[name="allowed[]"]', list).forEach(function (cb) { cb.checked = on; });
			});
		});
		sync();
	}

	function init() {
		var form = $('#bbm_tag_form');
		if (form)
			initTagForm(form);

		var builder = $('#bbm_builder');
		if (builder)
			initBuilder(builder);

		initAllowed();
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
