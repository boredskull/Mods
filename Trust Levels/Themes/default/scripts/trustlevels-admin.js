/**
 * Trust Levels: levels editor
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 */
(function () {
	'use strict';

	function init() {
		var list = document.getElementById('tl_levels');
		var tpl = document.getElementById('tl_level_template');
		var add = document.getElementById('tl_add_level');
		if (!list || !tpl || !add)
			return;

		var counter = 1000;
		var max = 10;

		function cards() {
			return Array.prototype.slice.call(list.querySelectorAll('.tl_level_card'));
		}

		function renumber() {
			var all = cards();
			all.forEach(function (card, i) {
				var num = card.querySelector('.tl_card_num');
				if (num)
					num.textContent = i;
				if (i === 0)
					card.setAttribute('data-first', '1');
				else
					card.removeAttribute('data-first');
				card.querySelector('.tl_up').disabled = i === 0;
				card.querySelector('.tl_down').disabled = i === all.length - 1;
				card.querySelector('.tl_remove').disabled = all.length <= 2;
			});
			add.disabled = all.length >= max;
		}

		add.addEventListener('click', function () {
			if (cards().length >= max)
				return;
			list.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, 'n' + (counter++)));
			renumber();
			var last = list.lastElementChild;
			if (last)
				last.querySelector('input[type="text"]').focus();
		});

		list.addEventListener('click', function (e) {
			var card = e.target.closest('.tl_level_card');
			if (!card)
				return;

			if (e.target.closest('.tl_remove')) {
				if (cards().length > 2)
					card.remove();
			}
			else if (e.target.closest('.tl_up') && card.previousElementSibling)
				list.insertBefore(card, card.previousElementSibling);
			else if (e.target.closest('.tl_down') && card.nextElementSibling)
				list.insertBefore(card.nextElementSibling, card);
			else
				return;

			renumber();
		});

		renumber();
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
