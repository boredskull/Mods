/**
 * Clubs
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-clubs-confirm]');
		if (el && !window.confirm(el.getAttribute('data-clubs-confirm')))
			e.preventDefault();
	});

	// Event form: the end date can't be before the start date.
	document.addEventListener('change', function (e) {
		if (e.target.id !== 'ev_start')
			return;
		var end = document.getElementById('ev_end');
		if (end) {
			end.min = e.target.value;
			if (end.value && end.value < e.target.value)
				end.value = e.target.value;
		}
	});
})();
