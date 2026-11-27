/**
 * Activity Stream
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 *
 * - "Load more" without leaving the page
 * - Follow / unfollow without reloading
 * - Stream builder: "All boards" toggle
 */
(function () {
	'use strict';

	function $(s, r) { return (r || document).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

	function loadMore(link) {
		var timeline = $('[data-as-timeline]');
		if (!timeline)
			return;

		var original = link.textContent;
		link.textContent = link.getAttribute('data-loading') || '…';
		link.setAttribute('aria-busy', 'true');

		fetch(link.href + ';fragment=1', { credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
			.then(function (html) {
				var box = document.createElement('div');
				box.innerHTML = html;
				var frag = $('[data-as-fragment]', box) || box;

				// Join the first new day onto the last day already shown.
				var lists = $$('ol.as_items', timeline);
				var last = lists[lists.length - 1];
				var firstHead = $('h4.as_day', frag);
				if (last && firstHead && firstHead.getAttribute('data-day') === last.getAttribute('data-day')) {
					var firstList = $('ol.as_items', frag);
					while (firstList && firstList.firstChild)
						last.appendChild(firstList.firstChild);
					firstHead.remove();
					if (firstList)
						firstList.remove();
				}

				var more = $('.as_more', frag);
				if (more)
					more.remove();

				var firstNew = null;
				while (frag.firstChild) {
					var node = frag.firstChild;
					if (!firstNew && node.nodeType === 1)
						firstNew = node;
					timeline.appendChild(node);
				}

				var wrap = link.closest('.as_more');
				if (more)
					wrap.replaceWith(more);
				else
					wrap.remove();
			})
			.catch(function () {
				// Fall back to a normal page load.
				window.location.href = link.href;
			})
			.finally(function () {
				link.textContent = original;
				link.removeAttribute('aria-busy');
			});
	}

	function toggleFollow(btn) {
		var following = btn.classList.contains('as_following');
		var url = btn.getAttribute(following ? 'data-unfollow' : 'data-follow');

		btn.setAttribute('aria-busy', 'true');
		fetch(url + ';js=1', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) {
					if (data.error)
						window.alert(data.error);
					return;
				}
				btn.classList.toggle('as_following', data.following);
				btn.textContent = btn.getAttribute(data.following ? 'data-label-unfollow' : 'data-label-follow');
				btn.href = btn.getAttribute(data.following ? 'data-unfollow' : 'data-follow');
				var count = $('[data-as-followers]');
				if (count && typeof data.followers === 'number')
					count.textContent = data.followers.toLocaleString();
			})
			.catch(function () { window.location.href = url; })
			.finally(function () { btn.removeAttribute('aria-busy'); });
	}

	document.addEventListener('click', function (e) {
		var more = e.target.closest('[data-as-more]');
		if (more) {
			e.preventDefault();
			if (!more.hasAttribute('aria-busy'))
				loadMore(more);
			return;
		}

		var follow = e.target.closest('[data-as-follow]');
		if (follow) {
			e.preventDefault();
			if (!follow.hasAttribute('aria-busy'))
				toggleFollow(follow);
			return;
		}

		var confirmEl = e.target.closest('[data-as-confirm]');
		if (confirmEl && !window.confirm(confirmEl.getAttribute('data-as-confirm')))
			e.preventDefault();
	});

	function init() {
		var all = $('#as_all_boards');
		var list = $('#as_board_list');
		if (all && list) {
			var sync = function () {
				list.classList.toggle('as_disabled', all.checked);
				$$('input', list).forEach(function (cb) { cb.disabled = all.checked; });
			};
			all.addEventListener('change', sync);
			sync();
		}
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
