/* Questions & Answers by BoredSkull */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading')
			fn();
		else
			document.addEventListener('DOMContentLoaded', fn);
	}

	function showError(el, text) {
		var tip = document.createElement('div');
		tip.className = 'qa_vote_error';
		tip.setAttribute('role', 'alert');
		tip.textContent = text;
		var r = el.getBoundingClientRect();
		tip.style.left = (window.scrollX + r.right + 8) + 'px';
		tip.style.top = (window.scrollY + r.top) + 'px';
		document.body.appendChild(tip);
		setTimeout(function () { tip.remove(); }, 3500);
	}

	ready(function () {
		document.addEventListener('click', function (e) {
			// Confirmations.
			var c = e.target.closest('[data-qa-confirm]');
			if (c && !window.confirm(c.getAttribute('data-qa-confirm'))) {
				e.preventDefault();
				return;
			}

			// Voting without reloading.
			var btn = e.target.closest('.qa_vote');
			if (!btn || !window.fetch)
				return;

			var box = btn.closest('.qa_votebox');
			if (!box)
				return;
			// Avoid racing responses changing the visible vote state out of order.
			if (box.querySelector('.qa_busy')) {
				e.preventDefault();
				return;
			}
			e.preventDefault();
			var url = box.getAttribute('data-url') + ';v=' + btn.getAttribute('data-v') + ';js=1';
			var votes = box.querySelectorAll('.qa_vote');
			votes.forEach(function (v) { v.classList.add('qa_busy'); });

			fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.ok) {
						showError(btn, data.error || 'Error');
						return;
					}
					var score = box.querySelector('.qa_score');
					score.textContent = data.score;
					score.classList.toggle('qa_pos', data.score > 0);
					score.classList.toggle('qa_neg', data.score < 0);
					votes.forEach(function (v) {
						var on = (v.getAttribute('data-v') === 'up' && data.vote > 0) || (v.getAttribute('data-v') === 'down' && data.vote < 0);
						v.classList.toggle('qa_voted', on);
						v.setAttribute('aria-pressed', on ? 'true' : 'false');
					});
				})
				.catch(function () { window.location = btn.href; })
				.finally(function () { votes.forEach(function (v) { v.classList.remove('qa_busy'); }); });
		});
	});
})();
