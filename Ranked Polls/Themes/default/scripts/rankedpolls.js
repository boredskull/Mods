/**
 * Ranked Polls
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 *
 * - "Ranked-choice" option on the poll form
 * - Ranking ballot: click, drag and drop, up/down buttons, keyboard
 * - Round-by-round results viewer
 */
(function () {
	'use strict';

	function $(s, r) { return (r || document).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
	function esc(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	function fmt(s) {
		var args = Array.prototype.slice.call(arguments, 1);
		return String(s).replace(/%(\d+)\$[sd]/g, function (m, n) { return args[n - 1]; }).replace(/%[sd]/g, function () { return args.shift(); });
	}

	/* Poll form ------------------------------------------------------- */

	function rowOf(input) {
		var dd = input && input.closest('dd');
		return dd ? [dd, dd.previousElementSibling] : [];
	}

	function initForm(cfg) {
		var hide = $('input[name="poll_hide"]');
		var question = $('input[name="question"]');
		if (!hide && !question)
			return;

		var t = cfg.txt;
		var box = document.createElement('dl');
		box.className = 'settings rp_form';
		var html =
			'<dt><label for="rp_ranked"><strong>' + esc(t.ranked) + '</strong></label><br><span class="smalltext">' + esc(t.ranked_desc) + '</span></dt>' +
			'<dd><input type="hidden" name="rp_present" value="1"><input type="checkbox" id="rp_ranked" name="rp_ranked" value="1"' + (cfg.checked ? ' checked' : '') + (cfg.locked ? ' disabled' : '') + '>' +
			(t.note ? ' <span class="smalltext">' + esc(t.note) + '</span>' : '') + '</dd>';

		if (cfg.anonymousMode === 'optional')
			html += '<dt class="rp_anon_row"><label for="rp_anonymous">' + esc(t.anonymous) + '</label><br><span class="smalltext">' + esc(t.anonymous_desc) + '</span></dt>' +
				'<dd class="rp_anon_row"><input type="checkbox" id="rp_anonymous" name="rp_anonymous" value="1"' + (cfg.anonymous ? ' checked' : '') + (cfg.locked ? ' disabled' : '') + '></dd>';
		else if (cfg.anonymousMode === 'always')
			html += '<dt class="rp_anon_row"></dt><dd class="rp_anon_row smalltext">' + esc(t.always_anonymous) + '</dd>';

		box.innerHTML = html;

		// After the "show results" options, or after the question.
		var anchor = hide ? hide.closest('dl') || hide.closest('fieldset') : question.closest('dl') || question.closest('fieldset') || question.parentNode;
		anchor.parentNode.insertBefore(box, anchor.nextSibling);

		// Irrelevant for ranked polls: votes per member and guest voting.
		var irrelevant = rowOf($('input[name="poll_max_votes"]')).concat(rowOf($('input[name="poll_guest_vote"]')));
		var ranked = $('#rp_ranked', box);

		function sync() {
			irrelevant.forEach(function (el) { if (el) el.hidden = ranked.checked; });
			$$('.rp_anon_row', box).forEach(function (el) { el.hidden = !ranked.checked; });
		}

		ranked.addEventListener('change', sync);
		sync();
	}

	/* Ballot ------------------------------------------------------------ */

	function initBallot(form) {
		var data;
		try { data = JSON.parse(form.getAttribute('data-rp-ballot')); } catch (e) { return; }

		var t = data.txt;
		var byId = {};
		data.choices.forEach(function (c) { byId[c.id] = c; });

		var fallback = $('.rp_fallback', form);
		var error = $('.rp_ballot_error', form);
		fallback.hidden = true;
		$$('select', fallback).forEach(function (s) { s.disabled = true; });

		var ui = document.createElement('div');
		ui.className = 'rp_ballot';
		ui.innerHTML =
			'<p class="smalltext rp_help">' + esc(t.help) + '</p>' +
			'<div class="rp_cols">' +
				'<div class="rp_col"><h5>' + esc(t.yours) + '</h5><ol class="rp_ranked" data-zone="ranked"></ol>' +
				'<p class="rp_empty smalltext">' + esc(t.empty) + '</p></div>' +
				'<div class="rp_col"><h5>' + esc(t.options) + '</h5><ul class="rp_pool" data-zone="pool"></ul>' +
				'<p class="rp_all smalltext" hidden>' + esc(t.all_ranked) + '</p>' +
				'<button type="button" class="button rp_clear">' + esc(t.clear) + '</button></div>' +
			'</div>' +
			'<div class="rp_live" aria-live="polite"></div>';
		form.insertBefore(ui, error);

		var rankedEl = $('.rp_ranked', ui);
		var poolEl = $('.rp_pool', ui);
		var live = $('.rp_live', ui);
		var ranking = (data.ranking || []).filter(function (id) { return byId[id]; });
		var dragged = null;

		function label(id) {
			var d = document.createElement('div');
			d.innerHTML = byId[id].html;
			return d.textContent.trim();
		}

		function say(msg) {
			live.textContent = '';
			setTimeout(function () { live.textContent = msg; }, 30);
		}

		function render(focusId, focusSel) {
			rankedEl.innerHTML = ranking.map(function (id, i) {
				return '<li draggable="true" tabindex="0" data-id="' + id + '">' +
					'<span class="rp_pos">' + (i + 1) + '</span>' +
					'<span class="rp_handle" aria-hidden="true">&#8942;&#8942;</span>' +
					'<span class="rp_item_label">' + byId[id].html + '</span>' +
					'<span class="rp_tools">' +
						'<button type="button" class="rp_up" aria-label="' + esc(t.up + ': ' + label(id)) + '"' + (i === 0 ? ' disabled' : '') + '>&#9650;</button>' +
						'<button type="button" class="rp_down" aria-label="' + esc(t.down + ': ' + label(id)) + '"' + (i === ranking.length - 1 ? ' disabled' : '') + '>&#9660;</button>' +
						'<button type="button" class="rp_remove" aria-label="' + esc(t.remove + ': ' + label(id)) + '">&times;</button>' +
					'</span></li>';
			}).join('');

			var unranked = data.choices.filter(function (c) { return ranking.indexOf(c.id) === -1; });
			poolEl.innerHTML = unranked.map(function (c) {
				return '<li draggable="true" data-id="' + c.id + '"><button type="button" class="rp_add" aria-label="' + esc(t.add + ': ' + label(c.id)) + '">' +
					'<span class="rp_plus" aria-hidden="true">+</span><span class="rp_item_label">' + c.html + '</span></button></li>';
			}).join('');

			$('.rp_empty', ui).hidden = ranking.length > 0;
			$('.rp_all', ui).hidden = unranked.length > 0;
			error.hidden = true;

			if (focusId !== undefined) {
				var li = $('li[data-id="' + focusId + '"]', rankedEl);
				var target = li && (focusSel ? $(focusSel, li) : li);
				if (target && !target.disabled)
					target.focus();
				else if (li)
					li.focus();
			}
		}

		function move(id, delta, focusSel) {
			var i = ranking.indexOf(id), j = i + delta;
			if (i < 0 || j < 0 || j >= ranking.length)
				return;
			ranking.splice(i, 1);
			ranking.splice(j, 0, id);
			render(id, focusSel);
			say(fmt(t.moved, label(id), j + 1));
		}

		ui.addEventListener('click', function (e) {
			var li = e.target.closest('li[data-id]');
			if (e.target.closest('.rp_clear')) {
				ranking = [];
				render();
				return;
			}
			if (!li)
				return;
			var id = +li.getAttribute('data-id');

			if (e.target.closest('.rp_add')) {
				ranking.push(id);
				render();
				var next = $('.rp_add', poolEl);
				if (next) next.focus();
				say(fmt(t.added, label(id), ranking.length));
			}
			else if (e.target.closest('.rp_up'))
				move(id, -1, '.rp_up');
			else if (e.target.closest('.rp_down'))
				move(id, 1, '.rp_down');
			else if (e.target.closest('.rp_remove')) {
				ranking.splice(ranking.indexOf(id), 1);
				render();
				say(fmt(t.removed, label(id)));
			}
		});

		// Keyboard: Alt+Up/Down moves the focused item, Delete removes it.
		rankedEl.addEventListener('keydown', function (e) {
			var li = e.target.closest('li[data-id]');
			if (!li || e.target !== li)
				return;
			var id = +li.getAttribute('data-id');

			if (e.altKey && e.key === 'ArrowUp') { e.preventDefault(); move(id, -1); }
			else if (e.altKey && e.key === 'ArrowDown') { e.preventDefault(); move(id, 1); }
			else if (e.key === 'Delete' || e.key === 'Backspace') {
				e.preventDefault();
				ranking.splice(ranking.indexOf(id), 1);
				render();
				say(fmt(t.removed, label(id)));
			}
		});

		// Drag and drop
		ui.addEventListener('dragstart', function (e) {
			var li = e.target.closest && e.target.closest('li[data-id]');
			if (!li)
				return;
			dragged = +li.getAttribute('data-id');
			li.classList.add('rp_dragging');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', String(dragged));
		});
		ui.addEventListener('dragend', function () {
			dragged = null;
			$$('.rp_dragging, .rp_drop', ui).forEach(function (el) { el.classList.remove('rp_dragging', 'rp_drop'); });
		});
		ui.addEventListener('dragover', function (e) {
			var zone = e.target.closest && e.target.closest('[data-zone]');
			if (zone && dragged !== null) {
				e.preventDefault();
				zone.classList.add('rp_drop');
			}
		});
		ui.addEventListener('dragleave', function (e) {
			var zone = e.target.closest && e.target.closest('[data-zone]');
			if (zone && !zone.contains(e.relatedTarget))
				zone.classList.remove('rp_drop');
		});
		ui.addEventListener('drop', function (e) {
			var zone = e.target.closest && e.target.closest('[data-zone]');
			if (!zone || dragged === null)
				return;
			e.preventDefault();

			var id = dragged;
			var from = ranking.indexOf(id);
			if (from !== -1)
				ranking.splice(from, 1);

			if (zone.getAttribute('data-zone') === 'ranked') {
				var pos = ranking.length;
				var items = $$('li[data-id]', rankedEl).filter(function (li) { return +li.getAttribute('data-id') !== id; });
				for (var i = 0; i < items.length; i++) {
					var box = items[i].getBoundingClientRect();
					if (e.clientY < box.top + box.height / 2) {
						pos = ranking.indexOf(+items[i].getAttribute('data-id'));
						break;
					}
				}
				ranking.splice(pos, 0, id);
				render();
				say(fmt(t.moved, label(id), pos + 1));
			}
			else {
				render();
				say(fmt(t.removed, label(id)));
			}
		});

		form.addEventListener('submit', function (e) {
			if (ranking.length < data.min) {
				e.preventDefault();
				error.textContent = t.too_few;
				error.hidden = false;
				return;
			}
			$$('input[name="ranking[]"]', form).forEach(function (el) { el.remove(); });
			ranking.forEach(function (id) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'ranking[]';
				input.value = id;
				form.appendChild(input);
			});
		});

		render();
	}

	/* Results rounds ------------------------------------------------------ */

	function initRounds(wrap) {
		var rounds = $$('.rp_round', wrap);
		if (rounds.length < 2)
			return;

		var current = rounds.length - 1;
		var nav = document.createElement('div');
		nav.className = 'rp_round_nav';
		nav.setAttribute('role', 'tablist');
		nav.innerHTML = '<button type="button" class="button rp_prev" aria-label="' + esc(wrap.getAttribute('data-prev')) + '">&#9664;</button>' +
			rounds.map(function (r, i) {
				return '<button type="button" role="tab" class="rp_tab" data-i="' + i + '">' + esc(fmt(wrap.getAttribute('data-label'), i + 1)) + '</button>';
			}).join('') +
			'<button type="button" class="button rp_next" aria-label="' + esc(wrap.getAttribute('data-next')) + '">&#9654;</button>';
		wrap.insertBefore(nav, rounds[0]);

		function show(i) {
			current = Math.max(0, Math.min(rounds.length - 1, i));
			rounds.forEach(function (r, j) { r.hidden = j !== current; });
			$$('.rp_tab', nav).forEach(function (b, j) {
				b.setAttribute('aria-selected', j === current ? 'true' : 'false');
				b.classList.toggle('rp_tab_active', j === current);
			});
			$('.rp_prev', nav).disabled = current === 0;
			$('.rp_next', nav).disabled = current === rounds.length - 1;
		}

		nav.addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b)
				return;
			if (b.classList.contains('rp_prev')) show(current - 1);
			else if (b.classList.contains('rp_next')) show(current + 1);
			else show(+b.getAttribute('data-i'));
		});
		nav.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') { show(current - 1); e.preventDefault(); }
			if (e.key === 'ArrowRight') { show(current + 1); e.preventDefault(); }
		});

		show(current);
	}

	function init() {
		if (window.rpFormConfig)
			initForm(window.rpFormConfig);

		var block = $('#rp_block');
		if (block) {
			// Sit under the topic title, where SMF shows its own polls.
			var head = $('#display_head');
			if (head && head.parentNode)
				head.parentNode.insertBefore(block, head.nextSibling);

			$$('.rp_ballot_form', block).forEach(initBallot);
			$$('[data-rp-rounds]', block).forEach(initRounds);
		}

		document.addEventListener('click', function (e) {
			var el = e.target.closest('[data-rp-confirm]');
			if (el && !window.confirm(el.getAttribute('data-rp-confirm')))
				e.preventDefault();
		});
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
