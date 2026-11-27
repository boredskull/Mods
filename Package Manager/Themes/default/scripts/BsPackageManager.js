/* Package Manager Enhancements 1.0.0. No automated installation. */
(function () {
  'use strict';
  function tr(key) { return (window.bspmStrings || {})['bspm_' + key] || key; }
  function fmt(key) { var args = Array.prototype.slice.call(arguments, 1); return tr(key).replace(/%[sd]/g, function () { return String(args.shift()); }); }
  function make(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === 'string') node.textContent = text;
    return node;
  }
  function presentNotice(message, parent) {
    var node = make('p', 'bspm-notice', message);
    parent.insertBefore(node, parent.firstChild);
  }
  function enhanceUploads() {
    var input = document.querySelector('form[action*="area=packages"][action*="sa=upload"] input[type="file"][name="package"]');
    if (!input || !window.FormData || !window.XMLHttpRequest || !window.File) return;
    var form = input.form;
    if (!form || form.dataset.bspmEnhanced === 'yes') return;
    // Do not alter SMF's native form or its session token. Keep it as a
    // genuinely independent, discoverable fallback in an expandable section.
    var host = make('section', 'bspm-upload');
    var title = make('h4', 'bspm-upload-title', tr('upload_title'));
    var explanation = make('p', '', tr('upload_desc'));
    var zone = make('div', 'bspm-dropzone');
    zone.setAttribute('role', 'button');
    zone.setAttribute('tabindex', '0');
    zone.setAttribute('aria-label', tr('drop_aria'));
    zone.appendChild(make('strong', '', tr('drop_title')));
    zone.appendChild(make('p', '', tr('drop_desc')));
    var row = make('div', 'bspm-upload-controls');
    var label = make('label', '', tr('choose_files'));
    var queueInput = make('input', 'bspm-queue-input');
    queueInput.type = 'file';
    queueInput.multiple = true;
    queueInput.accept = '.zip,.tgz,.tar.gz';
    queueInput.id = 'bspm-files';
    label.htmlFor = queueInput.id;
    var upload = make('button', 'button bspm-upload-primary', tr('upload_selected'));
    upload.type = 'button';
    upload.disabled = true;
    row.appendChild(label);
    row.appendChild(queueInput);
    row.appendChild(upload);
    var queue = make('ul', 'bspm-queue');
    queue.setAttribute('aria-label', tr('queue_aria'));
    var live = make('div', 'bspm-native-help');
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    live.textContent = tr('no_selected');
    var fallback = make('details', 'bspm-fallback');
    var summary = make('summary', '', tr('original_uploader'));
    fallback.appendChild(summary);
    host.appendChild(title);
    host.appendChild(explanation);
    host.appendChild(zone);
    host.appendChild(row);
    host.appendChild(queue);
    host.appendChild(live);
    host.appendChild(fallback);
    form.parentNode.insertBefore(host, form);
    fallback.appendChild(form);
    form.dataset.bspmEnhanced = 'yes';
    var jobs = [], running = false, paused = false;
    function valid(file) { return /\.(zip|tgz|tar\.gz)$/i.test(file.name) && file.size > 0; }
    function renderJob(job) {
      var li = make('li');
      var name = make('span', 'bspm-filename', fmt('file_size', job.file.name, Math.ceil(job.file.size / 1024)));
      var progress = make('progress');
      progress.max = 100;
      progress.value = 0;
      progress.setAttribute('aria-label', fmt('progress', job.file.name));
      var status = make('span', '', tr('ready'));
      var remove = make('button', 'button', tr('remove'));
      remove.type = 'button';
      remove.addEventListener('click', function () {
        if (job.state !== 'queued' && job.state !== 'failed') return;
        var index = jobs.indexOf(job);
        if (index >= 0) jobs.splice(index, 1);
        li.remove();
        refresh();
      });
      li.appendChild(name);
      li.appendChild(progress);
      li.appendChild(status);
      li.appendChild(remove);
      queue.appendChild(li);
      job.progress = progress; job.status = status; job.li = li; job.remove = remove;
    }
    function refresh(message) {
      var pending = jobs.filter(function (j) { return j.state === 'queued'; }).length;
      var done = jobs.filter(function (j) { return j.state === 'done'; }).length;
      upload.disabled = running || pending === 0;
      upload.textContent = tr(paused && pending ? 'resume' : 'upload_selected');
      if (message) live.textContent = message;
      else if (paused && pending) live.textContent = tr('paused');
      else live.textContent = !jobs.length ? tr('no_selected') : fmt('queue_counts', pending, done);
    }
    function addFiles(files) {
      var rejected = [];
      Array.prototype.forEach.call(files, function (file) {
        if (!valid(file)) { rejected.push(file.name); return; }
        if (jobs.some(function (j) { return j.file.name === file.name && j.file.size === file.size && j.state !== 'failed'; })) return;
        var job = {file: file, state: 'queued'};
        jobs.push(job);
        renderJob(job);
      });
      refresh(rejected.length ? fmt('unsupported', rejected.join(', ')) : null);
    }
    queueInput.addEventListener('change', function () { addFiles(queueInput.files); queueInput.value = ''; });
    var dragDepth = 0;
    zone.addEventListener('dragenter', function (ev) { ev.preventDefault(); dragDepth++; zone.classList.add('bspm-dragging'); });
    zone.addEventListener('dragover', function (ev) { ev.preventDefault(); });
    zone.addEventListener('dragleave', function (ev) { ev.preventDefault(); dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) zone.classList.remove('bspm-dragging'); });
    zone.addEventListener('drop', function (ev) { ev.preventDefault(); dragDepth = 0; zone.classList.remove('bspm-dragging'); });
    zone.addEventListener('drop', function (ev) { if (ev.dataTransfer && ev.dataTransfer.files) addFiles(ev.dataTransfer.files); });
    zone.addEventListener('click', function () { queueInput.click(); });
    zone.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); queueInput.click(); }
    });
    // SMF's normal upload endpoint accepts only one $_FILES['package'] per request.
    // Upload sequentially, but never install or execute a package automatically.
    function next() {
      var job = jobs.find(function (j) { return j.state === 'queued'; });
      if (!job) { running = false; refresh(); return; }
      job.state = 'uploading';
      job.status.textContent = tr('uploading');
      job.remove.disabled = true;
      refresh(fmt('uploading_file', job.file.name));
      var data = new FormData(form);
      data.set('package', job.file, job.file.name);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);
      xhr.upload.addEventListener('progress', function (ev) {
        if (ev.lengthComputable) job.progress.value = Math.min(100, Math.round(ev.loaded * 100 / ev.total));
      });
      function fail(reason) {
        job.state = 'failed';
        job.status.textContent = tr('unconfirmed');
        job.status.className = 'bspm-error';
        job.remove.disabled = false;
        running = false; paused = true;
        refresh(fmt('pause_notice', reason));
      }
      xhr.addEventListener('load', function () {
        if (xhr.status < 200 || xhr.status >= 300) { fail(fmt('http_error', xhr.status)); return; }
        var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
        var err = doc.querySelector('.errorbox, #fatal_error, #error_box');
        if (err) { fail((err.textContent || tr('rejected')).trim().slice(0, 250)); return; }
        var anchor = doc.querySelector('a[href*="area=packages"][href*="sa=install"][href*="package="]');
        if (!anchor) { fail(tr('unconfirmed_file')); return; }
        var destination = new URL(anchor.href, window.location.href);
        if (destination.origin !== window.location.origin) { fail(tr('unexpected_link')); return; }
        job.state = 'done';
        job.progress.value = 100;
        job.status.textContent = tr('uploaded_state');
        var link = make('a', '', tr('review_install'));
        link.href = destination.href;
        job.li.appendChild(link);
        next();
      });
      xhr.addEventListener('error', function () { fail(tr('network_error')); });
      xhr.addEventListener('abort', function () { fail(tr('interrupted')); });
      try { xhr.send(data); } catch (e) { fail(tr('send_error')); }
    }
    upload.addEventListener('click', function () { if (running) return; paused = false; running = true; next(); });
    presentNotice(tr('upload_notice'), host);
  }
  function enhanceDashboard() {
    var root = document.getElementById('bspm');
    if (!root) return;
    var search = document.getElementById('bspm-search');
    var filter = document.getElementById('bspm-filter');
    var sort = document.getElementById('bspm-sort');
    var body = document.querySelector('#bspm-table tbody');
    if (!search || !filter || !body) return;
    var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));
    var cards = Array.prototype.slice.call(root.querySelectorAll('.bspm-summary [data-bspm-filter]'));
    var shortcuts = Array.prototype.slice.call(root.querySelectorAll('.bspm-pending [data-bspm-filter]'));
    var reset = document.getElementById('bspm-reset-filter');
    var panel = document.getElementById('bspm-packages');
    function chooseFilter(value, moveToLibrary) {
      if (!Array.prototype.some.call(filter.options, function(o) {return o.value === value;})) value = 'all';
      filter.value = value;
      update();
      if (moveToLibrary && panel) {
        if (typeof panel.scrollIntoView === 'function') panel.scrollIntoView({block:'start', behavior:'smooth'});
      }
    }
    cards.concat(shortcuts).forEach(function(el) {
      el.addEventListener('click',function(event) {event.preventDefault();chooseFilter(el.dataset.bspmFilter,true);});
    });
    if (reset) reset.addEventListener('click',function() {search.value='';chooseFilter('all',false);});
    function update() {
      var q = search.value.trim().toLocaleLowerCase();
      var f = filter.value, visible = 0;
      cards.forEach(function(card){
        var active = card.dataset.bspmFilter === f;
        card.setAttribute('aria-pressed', active ? 'true' : 'false');
        card.classList.toggle('bspm-stat-active', active);
      });
      if (panel) panel.dataset.activeFilter = f;
      rows.sort(function (a,b) {
        if (sort.value === 'status') return a.dataset.bspmStatus.localeCompare(b.dataset.bspmStatus) || a.dataset.bspmName.localeCompare(b.dataset.bspmName);
        var d = a.dataset.bspmName.localeCompare(b.dataset.bspmName);
        return sort.value === 'name-desc' ? -d : d;
      });
      rows.forEach(function(row) {
        var matched = (!q || row.dataset.bspmName.includes(q)) && (f === 'all' || f === row.dataset.bspmStatus);
        row.hidden = !matched; if (matched) visible++;
        body.appendChild(row);
      });
      document.getElementById('bspm-empty').hidden = visible > 0;
    }
    search.addEventListener('input', update); filter.addEventListener('change', update); sort.addEventListener('change', update);
    update();
  }
  // SMF GenericList prints its package name in the mod_name{type} column.
  // Sorting on the whole row begins with the numeric package ID and makes
  // the A-Z/Z-A controls appear broken on Browse Packages.
  function nativePackageName(row) {
    var cells = row.querySelectorAll('td');
    for (var i = 0; i < cells.length; i++) {
      if (/(?:^|\s)mod_name(?:modification|avatar|language|theme|smiley|[a-z_]*)?(?:\s|$)/i.test(cells[i].className || ''))
        return cells[i].textContent.trim().toLocaleLowerCase();
    }
    // Fallback for a third-party table template retaining SMF column order.
    return cells.length > 1 ? cells[1].textContent.trim().toLocaleLowerCase() : row.textContent.trim().toLocaleLowerCase();
  }
  function compareNativePackages(a, b, mode) {
    if (mode === 'original') return a.index - b.index;
    var delta = a.name.localeCompare(b.name, undefined, {numeric: true, sensitivity: 'base'});
    return (mode === 'reverse' ? -delta : delta) || (a.index - b.index);
  }
  function enhanceNativeBrowse() {
    if (document.getElementById('bspm')) return;
    if (/(?:[?;])bspm_classic=1(?:[;&#]|$)/.test(window.location.href)) return;
    if (!/(?:[?;])area=packages(?:[;&]|$)/.test(window.location.href)) return;
    if (/(?:[?;])sa=(?!browse(?:[;&]|$))/.test(window.location.href)) return;
    // Enhance SMF's existing tables in place. Never replace links, forms, buttons,
    // hidden fields, session tokens, or the package manager's action handlers.
    var tables = Array.prototype.slice.call(document.querySelectorAll('table.table_grid')).filter(function (t) {
      return /^packages_lists_/.test(t.id || '') && t.querySelector('tbody tr');
    });
    if (!tables.length || document.getElementById('bspm-native-browse')) return;
    var rows = [];
    tables.forEach(function (table) {
      table.classList.add('bspm-native-list');
      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
        if (!tr.querySelector('td')) return;
        tr.classList.add('bspm-native-row');
        rows.push({row:tr, text:tr.textContent.toLocaleLowerCase(), name:nativePackageName(tr), table:table});
      });
    });
    var panel = make('section','bspm-native-panel windowbg');
    panel.id = 'bspm-native-browse';
    panel.appendChild(make('h3','', tr('browse')));
    panel.appendChild(make('p','', tr('browse_desc')));
    var stats = make('div','bspm-native-stats');
    var total = make('strong','',String(rows.length));
    stats.appendChild(total);
    stats.appendChild(make('span','', tr('rows_displayed')));
    panel.appendChild(stats);
    var controls = make('div','bspm-native-controls');
    var searchLabel = make('label','', tr('search'));
    var search = make('input'); search.type = 'search'; search.placeholder = tr('browse_placeholder');
    search.id = 'bspm-native-search'; searchLabel.htmlFor = search.id;
    var searchGroup = make('div','bspm-native-field');searchGroup.appendChild(searchLabel);searchGroup.appendChild(search);
    controls.appendChild(searchGroup);
    var sortLabel = make('label','', tr('sort_packages'));
    var sort = make('select');sort.id='bspm-native-sort';sortLabel.htmlFor=sort.id;
    [['original',tr('default_order')],['name',tr('name_asc')],['reverse',tr('name_desc')]].forEach(function (option) {
      var el=make('option','',option[1]);el.value=option[0];sort.appendChild(el);
    });
    var sortGroup=make('div','bspm-native-field');sortGroup.appendChild(sortLabel);sortGroup.appendChild(sort);
    controls.appendChild(sortGroup);panel.appendChild(controls);
    var empty=make('p','bspm-native-empty',tr('search_empty'));empty.hidden=true;panel.appendChild(empty);
    var first=tables[0];
    var headings = tables.map(function (table) {
      var heading = table.previousElementSibling;
      return heading && heading.matches('.cat_bar, .title_bar, h3, h4') ? heading : null;
    });
    // insertBefore requires its reference node to be a direct child. The
    // admin form wrapper is an ancestor on stock SMF, not a sibling.
    first.parentNode.insertBefore(panel, headings[0] || first);
    // Keep rows inside their original tables, preserving SMF grouping and form structure.
    rows.forEach(function (entry,i) {entry.index=i;});
    function update() {
      var query=search.value.trim().toLocaleLowerCase(),shown=0;
      tables.forEach(function(table) {
        var entries=rows.filter(function(e){return e.table===table;});
        entries.sort(function(a,b){return compareNativePackages(a,b,sort.value);});
        var groupVisible = 0;
        entries.forEach(function(e){
          e.row.hidden=!!query && e.text.indexOf(query)===-1;
          if(!e.row.hidden){shown++;groupVisible++;}
          e.row.parentNode.appendChild(e.row);
        });
        table.hidden = groupVisible === 0;
        var heading = headings[tables.indexOf(table)];
        if (heading) heading.hidden = table.hidden;
      });
      total.textContent=String(shown);empty.hidden=shown!==0;
    }
    search.addEventListener('input',update);sort.addEventListener('change',update);
    update();
  }
  function addSafetyNotice() {
    var onTest = /(?:[?;])sa=(?:install|uninstall)(?:[;&]|$)/.test(window.location.href);
    if (!onTest || document.querySelector('.bspm-page, .bspm-notice')) return;
    var main = document.querySelector('#admin_content, #admin_form_wrapper');
    if (main) presentNotice(tr('safety_notice'),main);
  }

  // Enhance native SMF Options and File Permissions without replacing forms,
  // changing input names, or bypassing server-side permission checks.
  function enhanceNativeSettings() {
    if (!/(?:[?;])area=packages(?:[;&]|$)/.test(location.href)) return;
    var match = location.href.match(/(?:[?;])sa=([^;&]+)/);
    var action = match ? decodeURIComponent(match[1]) : '';
    if (action !== 'options' && action !== 'perms' && action !== 'permissions') return;
    var content = document.querySelector('#admin_content') || document.querySelector('#admin_form_wrapper');
    if (!content || document.getElementById('bspm-settings-intro')) return;
    var isPermissions = action !== 'options';
    var intro = make('section', 'bspm-settings-intro windowbg');
    intro.id = 'bspm-settings-intro';
    intro.appendChild(make('h3', '', isPermissions ? tr('file_permissions') : tr('options')));
    intro.appendChild(make('p', '', isPermissions
      ? tr('permissions_desc')
      : tr('options_desc')));
    if (isPermissions) {
      var note = make('p', 'bspm-settings-warning', tr('permissions_warning'));
      intro.appendChild(note);
    }
    content.insertBefore(intro, content.firstChild);
    content.classList.add('bspm-settings-page');
    Array.prototype.forEach.call(content.querySelectorAll('fieldset, .cat_bar, .title_bar, .information, table.table_grid'), function(el) {
      if (el.closest('#bspm-settings-intro')) return;
      el.classList.add('bspm-settings-section');
    });
    Array.prototype.forEach.call(content.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]), select, textarea'), function(el) {
      el.classList.add('bspm-settings-control');
    });
    // Only add a client-side filter to permission tables. Never hide form fields
    // or rows containing controls, as that can confuse bulk permission actions.
    if (isPermissions) {
      var tables = Array.prototype.slice.call(content.querySelectorAll('table.table_grid'));
      tables.forEach(function(table) {
        // Never filter a permissions table containing any interactive controls.
        if (table.querySelector('input, select, textarea, button')) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function(tr) {return tr.querySelector('td');});
        if (rows.length < 5) return;
        var label = make('label', 'bspm-perms-filter-label', tr('find_file'));
        var input = make('input', 'bspm-settings-control');input.type='search';input.placeholder=tr('filter_paths');
        input.id = 'bspm-perms-filter-' + Array.prototype.indexOf.call(tables, table);
        label.htmlFor = input.id;
        table.parentNode.insertBefore(label,table);
        table.parentNode.insertBefore(input,table);
        input.addEventListener('input',function(){
          var query=input.value.toLocaleLowerCase().trim();
          rows.forEach(function(tr){tr.hidden=!!query && tr.textContent.toLocaleLowerCase().indexOf(query)===-1;});
        });
      });
    }
  }
  function addClassicLink() {
    if (document.getElementById('bspm') || !window.bspmClassicUrl) return;
    var host = document.querySelector('#admin_content, #admin_form_wrapper');
    if (!host) return;
    var link = make('a', 'button bspm-classic-link', tr('classic'));
    link.href = window.bspmClassicUrl;
    host.insertBefore(link, host.firstChild);
  }
  function run() { addClassicLink(); enhanceUploads(); enhanceDashboard(); enhanceNativeBrowse(); enhanceNativeSettings(); addSafetyNotice(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',run); else run();
}());
