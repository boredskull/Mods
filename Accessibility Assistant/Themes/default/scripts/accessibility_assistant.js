/* BoredSkull Accessibility Assistant 1.3.1 - ISC License.
 * Authoring heuristics and private device-level reading preferences.
 * Not a WCAG compliance checker; never blocks submitting a post.
 */
(function () {
  'use strict';
  var cfg = window.boredAccessibility || {};
  var KEY = 'boredAccessibilityPrefs.v1';
  var prefs = ['largeText', 'spacedText', 'underlineLinks', 'reduceMotion', 'focusVisible', 'lineHeight', 'wideSpacing', 'narrowReading', 'serifText'];

  function readPrefs() {
    try {
      var data = JSON.parse(window.localStorage.getItem(KEY) || '{}');
      if (!data || typeof data !== 'object' || Array.isArray(data)) return {};
      return data;
    } catch (_) { return {}; }
  }
  // Integration API. Other mods can register local advisory rules; no network calls.
  var pluginRules = [];
  window.BoredAccessibilityAPI = Object.freeze({
    version: '1.3.1',
    addRule: function (id, title, detail, reference, test) {
      if (!/^[a-z][a-z0-9_]{0,63}$/.test(id) || typeof test !== 'function' ||
          typeof title !== 'string' || typeof detail !== 'string' ||
          title.length > 160 || detail.length > 1200 || pluginRules.length >= 30 ||
          pluginRules.some(function (rule) { return rule.id === id; })) return false;
      pluginRules.push({id:id, title:title, detail:detail, reference:String(reference || '').slice(0, 160), test:test});
      return true;
    },
    getPreferences: function () { return Object.assign({}, readPrefs()); }
  });
  function applyPrefs(data) {
    prefs.forEach(function (name) {
      document.documentElement.classList.toggle('baa-pref-' + name, data[name] === true);
    });
  }
  function storePrefs(data) {
    try { window.localStorage.setItem(KEY, JSON.stringify(data)); return true; }
    catch (_) { return false; }
  }
  function el(name, cls, text) {
    var node = document.createElement(name);
    if (cls) node.className = cls;
    if (typeof text === 'string') node.textContent = text;
    return node;
  }
  function contrast(hex, bg) {
    function luminance(color) {
      var s = color.replace(/^#/, '');
      if (/^[0-9a-f]{3}$/i.test(s)) s = s.replace(/./g, '$&$&');
      if (!/^[0-9a-f]{6}$/i.test(s)) return null;
      var rgb = [0, 2, 4].map(function (i) {
        var c = parseInt(s.slice(i, i + 2), 16) / 255;
        return c <= .04045 ? c / 12.92 : Math.pow((c + .055) / 1.055, 2.4);
      });
      return .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2];
    }
    var a = luminance(hex), b = luminance(bg || '#ffffff');
    if (a === null || b === null) return null;
    return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
  }
  function tagAttributes(raw) {
    // Bounded, quote-aware parsing of genuine, space-delimited BBCode parameters.
    if (raw.length > 4096) return {};
    var attrs = {}, i = 0, len = raw.length;
    while (i < len) {
      while (i < len && /\s/.test(raw[i])) i++;
      if (i === len) break;
      if (!/[a-z]/i.test(raw[i])) return {};
      var start = i;
      while (i < len && /[a-z0-9_-]/i.test(raw[i])) i++;
      var name = raw.slice(start, i).toLowerCase();
      while (i < len && /\s/.test(raw[i])) i++;
      if (raw[i] !== '=') return {};
      i++;
      while (i < len && /\s/.test(raw[i])) i++;
      if (i >= len) return {};
      var quote = raw[i] === '"' || raw[i] === "'" ? raw[i++] : '';
      start = i;
      var value;
      if (quote) {
        while (i < len && raw[i] !== quote) i++;
        if (i >= len) return {};
        value = raw.slice(start, i++);
        if (i < len && !/\s/.test(raw[i])) return {};
      } else {
        while (i < len && !/\s/.test(raw[i])) i++;
        value = raw.slice(start, i);
        if (!value || /["']/.test(value)) return {};
      }
      if (Object.prototype.hasOwnProperty.call(attrs, name)) return {};
      attrs[name] = value;
    }
    return attrs;
  }
  function check(text) {
    // Limited BBCode heuristics, not a WCAG conformance test.
    text = String(text || '');
    var limit = cfg.inputLimit || 200000;
    var truncated = text.length > limit;
    if (truncated) text = text.slice(0, limit);
    var findings = [], seen = Object.create(null);
    function add(id, example) {
      if (!seen[id]) {
        seen[id] = true;
        findings.push({ id: id, example: example || '' });
      }
    }
    if (truncated) add('input_truncated');
    // A single forward scan avoids repeatedly retrying unclosed [url] tags.
    var tags = /\[(\/?)\b(img|url)(?=[\]\s=])([^\]]{0,4096})\]/gi;
    var urls = [], match;
    while ((match = tags.exec(text)) !== null) {
      var type = match[2].toLowerCase(), closing = match[1] === '/';
      if (type === 'img' && !closing) {
        var attrs = tagAttributes(match[3]);
        if (!Object.prototype.hasOwnProperty.call(attrs, 'alt')) add('image_alt');
        else if (/^(?:image|picture|photo|screenshot|file|img[0-9_.-]*)$/i.test(attrs.alt.trim())) add('image_generic');
      } else if (type === 'url') {
        if (!closing) {
          if (urls.length < 64) urls.push(tags.lastIndex);
        } else if (urls.length) {
          var start = urls.pop(), length = match.index - start;
          if (length >= 0 && length <= 512) {
            var caption = text.slice(start, match.index).replace(/\[[^\]]{0,256}\]/g, '').trim();
            if (/^(?:here|click here|read more|link|more|this)$/i.test(caption)) add('link_generic');
          }
        }
      }
    }
    var colors = /\[color\s*=\s*#([0-9a-f]{3}|[0-9a-f]{6})\]/ig;
    while ((match = colors.exec(text)) !== null) {
      var ratio = contrast('#' + match[1]);
      if (ratio !== null && ratio < 4.5) add('color_contrast', '#' + match[1] + ' ' + (cfg.onWhite || 'on white') + ' ≈ ' + ratio.toFixed(2) + ':1');
    }
    if (/\[size\s*=\s*(?:xx-small|x-small|(?:[1-8](?:\.[0-9]+)?)\s*(?:pt|px))\]/i.test(text)) add('text_size');
    if (/\[(?:youtube|video|audio|flash)(?:[=\s\]])/i.test(text)) add('media_captions');
    if (/\[table(?:[=\s\]])/i.test(text)) add('table_structure');
    if (/\[(?:attach|attachimg)(?:[=\s\]])/i.test(text)) add('attachment_alt');
    if (/\[(?:move|marquee|flash)(?:[=\s\]])/i.test(text)) add('motion');
    pluginRules.forEach(function (rule) {
      try {
        if (rule.test(text) === true) {
          if (!cfg.checks) cfg.checks = {};
          cfg.checks[rule.id] = { title:rule.title, detail:rule.detail, wcag:rule.reference };
          add(rule.id);
        }
      } catch (_) { /* Third-party rules must never interrupt posting. */ }
    });
    return findings;
  }
  function initPrefs() {
    var form = document.getElementById('baa-preferences');
    if (!form) return;
    var feedback = document.getElementById('baa-settings-feedback');
    var data = readPrefs();
    prefs.forEach(function (name) {
      var input = form.querySelector('[data-baa-pref="' + name + '"]');
      if (!input) return;
      input.checked = data[name] === true;
      input.addEventListener('change', function () {
        data[name] = input.checked;
        applyPrefs(data);
        if (feedback) feedback.textContent = storePrefs(data) ? (cfg.settingsSaved || 'Preferences saved.') : (cfg.settingsBlocked || 'Preferences applied, but this browser blocked local storage.');
      });
    });
    var presets = document.getElementById('baa-presets');
    if (presets) presets.addEventListener('change', function () {
      var selected = presets.value;
      if (selected === 'custom') return;
      data = {};
      if (selected === 'reading') data = {largeText:true, lineHeight:true, wideSpacing:true, narrowReading:true, underlineLinks:true};
      if (selected === 'visibility') data = {largeText:true, focusVisible:true, underlineLinks:true, spacedText:true};
      if (selected === 'motion') data = {reduceMotion:true, focusVisible:true};
      prefs.forEach(function (name) {
        var control = form.querySelector('[data-baa-pref="' + name + '"]');
        if (control) control.checked = data[name] === true;
      });
      applyPrefs(data);
      if (feedback) feedback.textContent = storePrefs(data) ? (cfg.settingsSaved || 'Saved.') : (cfg.settingsBlocked || 'Storage unavailable.');
    });
    var reset = document.getElementById('baa-reset-prefs');
    if (reset) reset.addEventListener('click', function () {
      data = {};
      prefs.forEach(function (name) {
        var control = form.querySelector('[data-baa-pref="' + name + '"]');
        if (control) control.checked = false;
      });
      applyPrefs(data);
      if (feedback) feedback.textContent = storePrefs(data) ? (cfg.settingsSaved || 'Preferences saved.') : (cfg.settingsReset || 'Preferences reset for this page.');
    });
  }
  function initContrast() {
    var button = document.getElementById('baa-contrast-button');
    var fg = document.getElementById('baa-fg');
    var bg = document.getElementById('baa-bg');
    var output = document.getElementById('baa-contrast-result');
    if (!button || !fg || !bg || !output) return;
    button.addEventListener('click', function () {
      var ratio = contrast(fg.value.trim(), bg.value.trim());
      var preview = document.getElementById('baa-contrast-preview');
      if (preview) {
        preview.style.color = ratio === null ? '' : fg.value.trim();
        preview.style.backgroundColor = ratio === null ? '' : bg.value.trim();
      }
      if (ratio === null) {
        output.textContent = (cfg.invalidColours || 'Enter two valid hex colours, for example #333333 and #ffffff.');
        return;
      }
      var message = cfg.contrastOutput || '{ratio}:1 contrast ratio. WCAG 2.2 AA: {normal}, {large}. AAA normal text: {aaa}. {note}';
      output.textContent = message
        .replace('{ratio}', ratio.toFixed(2))
        .replace('{normal}', ratio >= 4.5 ? (cfg.contrastNormalPass || 'normal text threshold met') : (cfg.contrastNormalFail || 'below normal-text threshold'))
        .replace('{large}', ratio >= 3 ? (cfg.contrastLargePass || 'large text threshold met') : (cfg.contrastLargeFail || 'below large-text threshold'))
        .replace('{aaa}', ratio >= 7 ? (cfg.contrastAAAPass || 'threshold met') : (cfg.contrastAAAFail || 'below threshold'))
        .replace('{note}', cfg.contrastCaution || 'Actual page styling and exceptions still need manual verification.');
    });
  }
  function skipLink() {
    if (!cfg.skipEnabled) return;
    var target = document.querySelector('#main_content_section, #content_section, main[role="main"], main');
    if (!target || !target.id) return; // Never link to an absent or moving target.
    var link = el('a', 'baa-skip', cfg.skipLabel || 'Skip to main content');
    link.href = '#' + target.id;
    link.addEventListener('click', function () {
      target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    });
    document.body.insertBefore(link, document.body.firstChild);
  }
  function getPostText(textarea) {
    // SCEditor can store the most recent changes outside of the textarea.
    if (window.sceditor && typeof window.sceditor.instance === 'function') {
      try {
        var instance = window.sceditor.instance(textarea);
        if (instance && typeof instance.val === 'function') return instance.val();
      } catch (_) { /* fall back to textarea */ }
    }
    return textarea.value;
  }
  function renderResults(target, findings) {
    target.textContent = '';
    var title = el('h3', '', cfg.resultTitle || 'Review suggestions');
    target.appendChild(title);
    if (findings.length === 0) {
      target.appendChild(el('p', '', cfg.noFindings || 'No concerns detected by limited checks.'));
      return;
    }
    var list = el('ul', 'baa-issues');
    findings.forEach(function (finding) {
      var definition = cfg.checks && cfg.checks[finding.id];
      if (!definition) return;
      var item = el('li');
      var heading = el('strong', '', definition.title);
      item.appendChild(heading);
      item.appendChild(el('span', 'smalltext', ' (' + definition.wcag + ')'));
      item.appendChild(el('p', '', definition.detail));
      if (finding.example) item.appendChild(el('p', 'smalltext', finding.example));
      list.appendChild(item);
    });
    target.appendChild(list);
  }
  function initQuickPanel() {
    if (!cfg.quickEnabled) return;
    var button = el('button', 'baa-quick-button', cfg.quickTitle || 'Accessibility options');
    button.type = 'button'; button.setAttribute('aria-expanded', 'false');
    var panel = el('div', 'baa-quick-panel'); panel.hidden = true;
    panel.id = 'baa-quick-panel'; button.setAttribute('aria-controls', panel.id);
    var options = [
      ['largeText','Larger text'], ['underlineLinks','Underline links'],
      ['spacedText','More spacing'], ['reduceMotion','Reduced movement'],
      ['focusVisible','Visible focus']
    ];
    options.forEach(function (entry, index) {
      entry[1] = cfg.quickOptions && cfg.quickOptions[index] || entry[1];
      var label = el('label','baa-checkbox');
      var input = el('input'); input.type = 'checkbox'; input.checked = readPrefs()[entry[0]] === true;
      input.addEventListener('change', function () {
        var data = readPrefs(); data[entry[0]] = input.checked; applyPrefs(data); storePrefs(data);
      });
      label.appendChild(input); label.appendChild(el('span','',entry[1])); panel.appendChild(label);
    });
    var page = el('a','',cfg.quickTitle || 'Accessibility options');
    page.href = cfg.actionUrl || '#'; panel.appendChild(page);
    button.addEventListener('click', function () {
      panel.hidden = !panel.hidden;
      button.setAttribute('aria-expanded', String(!panel.hidden));
      if (!panel.hidden) panel.querySelector('input').focus();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) { panel.hidden = true; button.setAttribute('aria-expanded','false'); button.focus(); }
    });
    document.body.appendChild(button); document.body.appendChild(panel);
  }
  function initReadingMode() {
    if (!cfg.readingEnabled) return;
    var article = document.querySelector('#forumposts, .post_wrapper, .post');
    if (!article) return;
    var button = el('button','button baa-reading-toggle',cfg.readingTitle || 'Focus reading mode');
    button.type = 'button'; button.setAttribute('aria-pressed','false');
    button.addEventListener('click', function () {
      var enabled = document.documentElement.classList.toggle('baa-reading');
      button.setAttribute('aria-pressed', String(enabled));
    });
    article.parentNode.insertBefore(button, article);
  }
  function initComposer() {
    if (!cfg.composerEnabled || !window.boredAccessibilityComposer) return;
    var input = document.querySelector('textarea[name="message"]');
    if (!input || input.closest('.baa-post-check')) return;
    // Insert after the textarea's enclosing area rather than altering native posting buttons.
    var host = input.closest('.editor_wrapper') || input.closest('.editor_container') || input.parentElement;
    if (!host || document.getElementById('baa-post-check')) return;
    var pane = el('section', 'baa-post-check');
    pane.id = 'baa-post-check';
    pane.setAttribute('aria-label', cfg.composerLabel || 'Accessibility posting assistant');
    var button = el('button', 'button', cfg.label || 'Check accessibility');
    button.type = 'button';
    var result = el('div', 'baa-composer-results');
    result.id = 'baa-post-results';
    result.setAttribute('role', 'status');
    result.setAttribute('aria-live', 'polite');
    result.setAttribute('aria-atomic', 'true');
    button.setAttribute('aria-controls', result.id);
    button.addEventListener('click', function () {
      renderResults(result, check(getPostText(input)));
    });
    var liveLabel = el('label', 'baa-checkbox');
    var liveCheckbox = el('input'); liveCheckbox.type = 'checkbox';
    liveCheckbox.checked = !!cfg.liveEnabled && readPrefs().liveComposer !== false;
    liveLabel.appendChild(liveCheckbox);
    liveLabel.appendChild(el('span','',cfg.liveLabel || 'Check as you write (optional)'));
    var timeoutId = null;
    function refreshLive() {
      if (!liveCheckbox.checked) return;
      if (timeoutId !== null) window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(function () { renderResults(result, check(getPostText(input))); }, 650);
    }
    input.addEventListener('input', refreshLive);
    input.addEventListener('change', refreshLive);
    liveCheckbox.addEventListener('change', function () {
      var data = readPrefs(); data.liveComposer = liveCheckbox.checked; storePrefs(data);
      if (liveCheckbox.checked) refreshLive(); else result.textContent = '';
    });
    pane.appendChild(button);
    if (cfg.liveEnabled) pane.appendChild(liveLabel);
    pane.appendChild(el('p','smalltext',cfg.altHelp || 'Use meaningful image descriptions where supported.'));
    pane.appendChild(el('p', 'smalltext', cfg.composerHelp || 'Suggestions only. Posting is never blocked.'));
    pane.appendChild(result);
    host.insertAdjacentElement('afterend', pane);
  }
  // Extensions listen for this event and register rules before the checker initializes.
  try { document.dispatchEvent(new Event('boredAccessibilityReady')); } catch (_) { /* Older clients keep built-in checks. */ }
  applyPrefs(readPrefs());
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initPrefs(); initContrast(); skipLink(); initComposer(); initQuickPanel(); initReadingMode();
    });
  } else {
    initPrefs(); initContrast(); skipLink(); initComposer(); initQuickPanel(); initReadingMode();
  }
  // Deterministic hook for small isolated tests, not a public API guarantee.
  if (typeof window !== 'undefined') window.baaAccessibilityCheck = check;
}());
