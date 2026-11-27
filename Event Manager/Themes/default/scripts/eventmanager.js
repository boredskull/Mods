/* Event Manager by BoredSkull */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading')
			fn();
		else
			document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		// Confirmation for links and buttons with data-confirm.
		document.addEventListener('click', function (e) {
			var el = e.target.closest('[data-confirm]');
			if (el && !window.confirm(el.getAttribute('data-confirm')))
				e.preventDefault();
		});

		// Required answer: only when saying going or maybe.
		document.querySelectorAll('.em_rsvp_form').forEach(function (form) {
			var input = form.querySelector('input[data-required]');
			if (!input)
				return;
			form.querySelectorAll('button[name="answer"]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					input.required = btn.value === 'going' || btn.value === 'maybe';
				});
			});
		});

		// Check-in without reloading the page.
		document.querySelectorAll('.em_checkin').forEach(function (box) {
			box.addEventListener('change', function () {
				var url = box.getAttribute('data-url') + (box.checked ? 'checkin' : 'uncheck') + ';js=1';
				box.disabled = true;
				fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (r) { return r.json(); })
					.then(function (data) { box.checked = !!data.checked_in; })
					.catch(function () { box.checked = !box.checked; })
					.finally(function () { box.disabled = false; });
			});
		});

		// Filter the attendee table.
		var filter = document.querySelector('.em_filter');
		if (filter)
			filter.addEventListener('input', function () {
				var q = filter.value.trim().toLowerCase();
				document.querySelectorAll('.em_table tbody tr[data-name]').forEach(function (row) {
					row.style.display = !q || row.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
				});
			});
	});
})();
