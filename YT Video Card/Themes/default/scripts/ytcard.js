/**
 * YouTube Video Card for SMF 2.1
 *
 * The [youtube] tag renders a player plus an empty ".ytcard_body". This script asks the forum
 * (action=ytcard) for the video details and fills the body in with the title, description and
 * comments. Everything from YouTube is inserted as text, never as HTML.
 */
(function () {
	'use strict';

	var cfg = window.ytcard_cfg;
	if (!cfg || !cfg.url || !window.fetch)
		return;

	var txt = cfg.txt || {};
	var requests = {};

	function t(key) {
		return Object.prototype.hasOwnProperty.call(txt, key) ? txt[key] : '';
	}

	function make(tag, className, text) {
		var node = document.createElement(tag);
		if (className)
			node.className = className;
		if (text !== undefined && text !== null)
			node.textContent = text;
		return node;
	}

	function number(n) {
		var v = Number(n);
		return isFinite(v) ? v.toLocaleString() : '';
	}

	function count(key, n) {
		return t(key).replace('%s', number(n));
	}

	function date(iso) {
		var d = new Date(iso);
		return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: 'numeric'});
	}

	function link(href, text, className) {
		var a = make('a', className, text);
		a.href = href;
		a.target = '_blank';
		a.rel = 'nofollow noopener noreferrer ugc';
		return a;
	}

	// Plain text in, text nodes and anchors out.
	function linkify(parent, text) {
		var re = /https?:\/\/[^\s<>"']+/g;
		var last = 0;
		var m;

		while ((m = re.exec(text)) !== null) {
			var url = m[0];
			var trail = '';
			var punctuation = url.match(/[.,;:!?)\]]+$/);

			if (punctuation) {
				trail = punctuation[0];
				url = url.slice(0, url.length - trail.length);
			}

			if (m.index > last)
				parent.appendChild(document.createTextNode(text.slice(last, m.index)));

			if (url.length > 8)
				parent.appendChild(link(url, url));
			else
				parent.appendChild(document.createTextNode(url));

			if (trail)
				parent.appendChild(document.createTextNode(trail));

			last = m.index + m[0].length;
		}

		if (last < text.length)
			parent.appendChild(document.createTextNode(text.slice(last)));
	}

	function note(body, text) {
		body.appendChild(make('span', 'ytcard_note', text));
	}

	function renderComment(c) {
		var li = make('li', 'ytcard_comment');

		if (cfg.avatars && c.avatar && /^https:\/\//.test(c.avatar)) {
			var img = make('img', 'ytcard_avatar');
			img.src = c.avatar;
			img.alt = '';
			img.width = 32;
			img.height = 32;
			img.loading = 'lazy';
			img.referrerPolicy = 'no-referrer';
			li.appendChild(img);
		}

		var main = make('div', 'ytcard_comment_main');
		var head = make('div', 'ytcard_comment_head');

		head.appendChild(c.author_url ? link(c.author_url, c.author, 'ytcard_author') : make('span', 'ytcard_author', c.author));

		var bits = [];
		if (c.published && date(c.published))
			bits.push(date(c.published));
		if (c.likes)
			bits.push(count('likes', c.likes));
		if (c.replies)
			bits.push(count('replies', c.replies));

		if (bits.length)
			head.appendChild(document.createTextNode(' · ' + bits.join(' · ')));

		main.appendChild(head);
		main.appendChild(make('div', 'ytcard_comment_text', c.text));
		li.appendChild(main);

		return li;
	}

	function render(card, d) {
		var body = card.querySelector('.ytcard_body');
		if (!body)
			return;

		var id = card.getAttribute('data-ytcard');
		var watch = 'https://www.youtube.com/watch?v=' + encodeURIComponent(id);
		var v = d && d.ok ? d.video : null;

		body.textContent = '';

		// Nothing usable came back: keep a plain link to the video.
		if (!v) {
			body.appendChild(link(watch, t('watch'), 'ytcard_fallback bbc_link'));
			return;
		}

		body.appendChild(link(watch, v.title || t('watch'), 'ytcard_title'));

		var meta = make('div', 'ytcard_meta');
		var bits = [];

		if (v.channel)
			bits.push(v.channel_url ? link(v.channel_url, v.channel) : make('span', '', v.channel));
		if (v.published && date(v.published))
			bits.push(make('span', '', date(v.published)));
		if (v.views !== null && v.views !== undefined)
			bits.push(make('span', '', count('views', v.views)));
		if (v.likes !== null && v.likes !== undefined)
			bits.push(make('span', '', count('likes', v.likes)));

		bits.forEach(function (bit, i) {
			if (i)
				meta.appendChild(document.createTextNode(' · '));
			meta.appendChild(bit);
		});

		if (bits.length)
			body.appendChild(meta);

		if (v.description) {
			var desc = make('div', 'ytcard_desc');
			var text = make('div', 'ytcard_desc_text ytcard_collapsed');
			var toggle = make('button', 'ytcard_toggle', t('show_more'));

			toggle.type = 'button';
			toggle.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			linkify(text, v.description);

			toggle.addEventListener('click', function () {
				var collapsed = text.classList.toggle('ytcard_collapsed');
				toggle.textContent = collapsed ? t('show_more') : t('show_less');
				toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			});

			desc.appendChild(text);
			desc.appendChild(toggle);
			body.appendChild(desc);

			// Only offer "Show more" when something is actually cut off.
			if (!text.clientHeight || text.scrollHeight > text.clientHeight + 2)
				toggle.hidden = false;
		}

		switch (d.comments_status) {
			case 'ok':
				var section = make('div', 'ytcard_comments');
				var title = t('comments');
				var list = make('ul', 'ytcard_comments_list');

				if (v.comment_count !== null && v.comment_count !== undefined)
					title += ' (' + number(v.comment_count) + ')';

				section.appendChild(make('div', 'ytcard_comments_title', title));
				d.comments.forEach(function (c) {
					list.appendChild(renderComment(c));
				});
				section.appendChild(list);
				section.appendChild(link(watch, t('all_comments'), 'ytcard_more'));
				body.appendChild(section);
				break;

			case 'none':
				note(body, t('no_comments'));
				break;

			case 'disabled':
				note(body, t('comments_disabled'));
				break;

			case 'error':
				note(body, t('comments_error'));
				break;

			case 'unavailable':
				// No API key configured: send people to YouTube for the rest.
				body.appendChild(link(watch, t('more_on_youtube'), 'ytcard_more'));
				break;
		}
	}

	function request(id, sig) {
		if (!requests[id]) {
			var url = cfg.url + ';v=' + encodeURIComponent(id) + ';s=' + encodeURIComponent(sig) +
				';' + cfg.sessVar + '=' + encodeURIComponent(cfg.sessId);

			requests[id] = fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}})
				.then(function (response) { return response.json(); })
				.catch(function () { return null; });
		}

		return requests[id];
	}

	function load(card) {
		request(card.getAttribute('data-ytcard'), card.getAttribute('data-sig')).then(function (d) {
			render(card, d);
		});
	}

	var observer = null;
	if ('IntersectionObserver' in window) {
		observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					observer.unobserve(entry.target);
					load(entry.target);
				}
			});
		}, {rootMargin: '400px 0px'});
	}

	function scan() {
		var cards = document.querySelectorAll('.ytcard[data-ytcard]:not([data-ytcard-ready])');

		Array.prototype.forEach.call(cards, function (card) {
			var body = card.querySelector('.ytcard_body');

			card.setAttribute('data-ytcard-ready', '1');

			if (body && t('loading')) {
				body.textContent = '';
				note(body, t('loading'));
			}

			if (observer)
				observer.observe(card);
			else
				load(card);
		});
	}

	// Posts also arrive after page load (previews, quick edit, loaded pages).
	var timer = null;
	function schedule() {
		if (timer)
			return;

		timer = setTimeout(function () {
			timer = null;
			scan();
		}, 250);
	}

	if ('MutationObserver' in window)
		new MutationObserver(schedule).observe(document.documentElement, {childList: true, subtree: true});

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', scan);
	else
		scan();
}());
