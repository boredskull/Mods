/* Profile Wall for SMF 2.1.x - live character counters for plain textareas. */
(function () {
	function bind(area) {
		var max = parseInt(area.getAttribute('data-pw-max'), 10);
		var counter = area.parentNode.querySelector('.pw_counter');
		if (!max || !counter)
			return;

		var form = area.form;
		var update = function () {
			var left = max - area.value.length;
			counter.textContent = left + ' / ' + max;
			counter.classList.toggle('pw_over', left < 0);
		};
		area.addEventListener('input', update);
		update();

		if (form)
			form.addEventListener('submit', function (e) {
				if (area.value.length > max) {
					e.preventDefault();
					area.focus();
				}
			});
	}

	function init() {
		var areas = document.querySelectorAll('textarea[data-pw-max]');
		for (var i = 0; i < areas.length; i++)
			bind(areas[i]);

		// Open the reply box and focus it when summary is clicked.
		var boxes = document.querySelectorAll('.pw_reply_box');
		for (var j = 0; j < boxes.length; j++)
			boxes[j].addEventListener('toggle', function () {
				if (this.open) {
					var t = this.querySelector('textarea');
					if (t) t.focus();
				}
			});
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
