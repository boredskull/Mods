/**
 * Advanced BBCode & Editor Manager: editor enhancements
 * BoredSkull https://boredskull.net (c) 2026, ISC licence
 *
 * - Makes custom tags round-trip in WYSIWYG mode
 * - Keyboard shortcuts for custom buttons
 * - Searchable command palette (Alt+/)
 * - Mobile-friendly toolbar
 */
(function () {
	'use strict';

	var cfg = {};
	var configReady = false;
	var formatsDone = false;

	function readConfig() {
		if (configReady)
			return true;
		if (!window.bbmConfig)
			return false;
		cfg = window.bbmConfig;
		cfg.formats = Array.isArray(cfg.formats) ? cfg.formats : [];
		cfg.shortcuts = Array.isArray(cfg.shortcuts) ? cfg.shortcuts : [];
		cfg.icons = cfg.icons || {};
		cfg.txt = cfg.txt || {};
		configReady = true;
		return true;
	}

	function esc(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	/* WYSIWYG formats ------------------------------------------------- */

	function registerFormats() {
		// Do not mark the registry complete before the inline configuration arrives.
		if (!readConfig())
			return false;
		if (formatsDone)
			return true;
		if (!window.sceditor || !sceditor.formats || !sceditor.formats.bbcode)
			return false;

		cfg.formats.forEach(function (f) {
			var el = f.block ? 'div' : 'span';
			var def = {
				tags: {},
				isInline: !f.block,
				allowsEmpty: true,
				format: function (element, content) {
					var opt = element.getAttribute('data-bbm-option') || '';
					if (opt && /[\s\]]/.test(opt))
						opt = '"' + opt.replace(/"/g, '') + '"';

					return '[' + f.tag + (opt ? '=' + opt : '') + ']' + (f.closed ? '' : content + '[/' + f.tag + ']');
				},
				html: function (token, attrs, content) {
					var opt = attrs && attrs.defaultattr ? String(attrs.defaultattr) : '';

					return '<' + el + ' class="bbm-wysiwyg' + (f.block ? ' bbm-wysiwyg-block' : '') + '" data-bbm-tag="' + esc(f.tag) +
						'" data-bbm-option="' + esc(opt) + '" data-bbm-label="' + esc(f.label + (opt ? ': ' + opt : '')) + '">' +
						(f.closed ? '[' + esc(f.tag) + ']' : content) + '</' + el + '>';
				}
			};
			def.tags[el] = { 'data-bbm-tag': [f.tag] };
			if (f.closed)
				def.isSelfClosing = true;

			try {
				sceditor.formats.bbcode.set(f.tag, def);
			} catch (e) {}
		});

		formatsDone = true;
		return true;
	}

	/* Shortcuts --------------------------------------------------------- */

	function comboFromEvent(e) {
		var key = '';
		if (/^Key[A-Z]$/.test(e.code))
			key = e.code.slice(3).toLowerCase();
		else if (/^Digit[0-9]$/.test(e.code))
			key = e.code.slice(5);
		else if (/^Numpad[0-9]$/.test(e.code))
			key = e.code.slice(6);
		else if (/^F([1-9]|1[0-2])$/.test(e.code))
			key = e.code.toLowerCase();
		else if (e.code === 'Slash')
			key = '/';
		else
			return '';

		var mods = [];
		if (e.ctrlKey) mods.push('ctrl');
		if (e.altKey) mods.push('alt');
		if (e.shiftKey) mods.push('shift');
		if (e.metaKey) mods.push('meta');

		return mods.length ? mods.join('+') + '+' + key : '';
	}

	function clickButton(container, code) {
		var button = container.querySelector('.sceditor-button-' + code);
		if (!button || button.classList.contains('disabled'))
			return false;

		button.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
		button.click();
		return true;
	}

	/* Command palette --------------------------------------------------- */

	function openPalette(container, instance) {
		var toolbar = container.querySelector('.sceditor-toolbar');
		if (!toolbar || document.querySelector('.bbm-palette'))
			return;

		var commands = [];
		Array.prototype.forEach.call(toolbar.querySelectorAll('a.sceditor-button'), function (a) {
			var match = /sceditor-button-([\w-]+)/.exec(a.className);
			if (!match || match[1] === 'bbm_palette')
				return;
			commands.push({ code: match[1], label: a.getAttribute('title') || a.getAttribute('aria-label') || match[1] });
		});

		var overlay = document.createElement('div');
		overlay.className = 'bbm-palette';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', cfg.txt.palette || 'Commands');
		overlay.innerHTML = '<div class="bbm-palette-box"><input type="search" class="bbm-palette-input" autocomplete="off" placeholder="' +
			esc(cfg.txt.search || 'Search commands') + '" aria-controls="bbm-palette-list"><ul class="bbm-palette-list" id="bbm-palette-list" role="listbox"></ul>' +
			'<div class="bbm-palette-hint">' + esc(cfg.txt.hint || '') + '</div></div>';
		document.body.appendChild(overlay);

		var input = overlay.querySelector('input');
		var list = overlay.querySelector('ul');
		var shown = [];
		var active = 0;

		function render() {
			var q = input.value.trim().toLowerCase();
			shown = commands.filter(function (c) {
				return !q || c.label.toLowerCase().indexOf(q) !== -1 || c.code.toLowerCase().indexOf(q) !== -1;
			});
			active = Math.min(active, Math.max(0, shown.length - 1));
			list.innerHTML = shown.length ? shown.map(function (c, i) {
				return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="active" aria-selected="true"' : '') + '>' + esc(c.label) + '<small>' + esc(c.code.replace(/^bbm_/, '')) + '</small></li>';
			}).join('') : '<li class="bbm-palette-empty">' + esc(cfg.txt.none || 'No matches') + '</li>';
			var el = list.querySelector('.active');
			if (el && el.scrollIntoView)
				el.scrollIntoView({ block: 'nearest' });
		}

		function close() {
			overlay.remove();
			try { instance.focus(); } catch (e) {}
		}

		function run(i) {
			var c = shown[i];
			close();
			if (c)
				setTimeout(function () { clickButton(container, c.code); }, 0);
		}

		input.addEventListener('input', function () { active = 0; render(); });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { active = Math.min(shown.length - 1, active + 1); render(); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
			else if (e.key === 'Enter') { run(active); e.preventDefault(); }
			else if (e.key === 'Escape') { close(); e.preventDefault(); }
		});
		list.addEventListener('mousedown', function (e) {
			var li = e.target.closest('li[data-i]');
			if (li) { e.preventDefault(); run(+li.getAttribute('data-i')); }
		});
		overlay.addEventListener('mousedown', function (e) {
			if (e.target === overlay) close();
		});

		render();
		input.focus();
	}

	function addPaletteButton(container, instance) {
		var toolbar = container.querySelector('.sceditor-toolbar');
		if (!toolbar || toolbar.querySelector('.sceditor-button-bbm_palette'))
			return;

		var group = document.createElement('div');
		group.className = 'sceditor-group bbm-palette-group';
		var a = document.createElement('a');
		a.className = 'sceditor-button sceditor-button-bbm_palette';
		a.href = '#';
		a.setAttribute('role', 'button');
		a.setAttribute('title', (cfg.txt.palette || 'Commands') + ' (Alt+/)');
		a.innerHTML = '<div>&#8984;</div>';
		a.addEventListener('mousedown', function (e) { e.preventDefault(); });
		a.addEventListener('click', function (e) {
			e.preventDefault();
			openPalette(container, instance);
		});
		group.appendChild(a);

		var rows = toolbar.querySelectorAll('.sceditor-row, .sceditor-toolbar-row');
		(rows.length ? rows[rows.length - 1] : toolbar).appendChild(group);
	}

	/* Per-editor setup --------------------------------------------------- */

	function getContainer(instance, textarea) {
		// SMF's SCEditor wrapper is not necessarily a descendant of the textarea's
		// parent. Use the editor's own content area first, as SMF itself does.
		try {
			var content = instance.getContentAreaContainer();
			if (content && content.closest)
				return content.closest('.sceditor-container');
		} catch (e) {}
		return textarea.parentNode && textarea.parentNode.querySelector('.sceditor-container');
	}

	function labelCustomButtons(container) {
		// SCEditor hides the inner div's text for sprite icons. A custom tag
		// without an uploaded icon otherwise looks like an empty grey button.
		var buttons = container.querySelectorAll('a.sceditor-button');
		Array.prototype.forEach.call(buttons, function (button) {
			var match = /(?:^|\s)sceditor-button-(bbm_[a-z0-9_]+)(?:\s|$)/i.exec(button.className);
			if (!match || match[1] === 'bbm_palette')
				return;
			var code = match[1];
			if (cfg.icons && cfg.icons[code])
				return;
			var div = button.querySelector('div');
			if (!div)
				return;
			var shortLabel = code.slice(4, 6).toUpperCase();
			div.textContent = shortLabel;
			div.style.setProperty('background', 'none', 'important');
			div.style.setProperty('text-indent', '0', 'important');
			div.style.setProperty('font', 'bold 10px/16px sans-serif', 'important');
			div.style.setProperty('text-align', 'center', 'important');
			div.style.setProperty('color', '#333', 'important');
			button.classList.add('bbm-button-labelled');
			if (!button.getAttribute('aria-label'))
				button.setAttribute('aria-label', button.getAttribute('title') || code.slice(4));
		});
	}

	function setup(instance, textarea) {
		var container = getContainer(instance, textarea);
		if (!container)
			return false;

		labelCustomButtons(container);

		if (cfg.mobile)
			container.classList.add('bbm-mobile');

		if (cfg.palette)
			addPaletteButton(container, instance);

		var map = {};
		cfg.shortcuts.forEach(function (s) { map[s.keys] = s.code; });

		instance.bind('keydown', function (e) {
			var combo = comboFromEvent(e);
			if (!combo)
				return;

			if (cfg.palette && combo === 'alt+/') {
				e.preventDefault();
				openPalette(container, instance);
				return false;
			}

			if (map[combo] && clickButton(container, map[combo])) {
				e.preventDefault();
				return false;
			}
		});
		return true;
	}

	function scan() {
		if (!window.sceditor || typeof sceditor.instance !== 'function')
			return;

		Array.prototype.forEach.call(document.querySelectorAll('textarea'), function (ta) {
			if (ta.bbmReady)
				return;

			var instance = null;
			try { instance = sceditor.instance(ta); } catch (e) {}

			if (instance) {
				// Even if configuration is late, a scan should not leave blank
				// custom controls on-screen.
				var container = getContainer(instance, ta);
				if (container)
					labelCustomButtons(container);
				if (readConfig() && setup(instance, ta))
					ta.bbmReady = true;
			}
		});
	}

	// Formats must be registered before the editor converts its content.
	// The configuration may arrive later, so don't finalise an empty registry.
	registerFormats();

	function start() {
		readConfig();
		registerFormats();
		var tries = 0;
		var timer = setInterval(function () {
			readConfig();
			registerFormats();
			scan();
			if (++tries > 60)
				clearInterval(timer);
		}, 250);
		scan();
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', start);
	else
		start();
})();
