/* Tag/prefix form enhancement. The separate topic editor works without JS. */
(function () {
  'use strict';
  function ready() {
    var cfg = window.boredTopicTagsComposer;
    if (!cfg) return;
    var form = document.querySelector('form#postmodify, form[name="postmodify"], form[action*="action=post2"]');
    if (!form || form.querySelector('[name="tp_meta_marker"]')) return;
    var subject = form.querySelector('input[name="subject"]');
    if (!subject) return;
    var box = document.createElement('fieldset');
    box.className = 'tp-composer-box';
    var legend = document.createElement('legend');
    legend.textContent = cfg.label;
    box.appendChild(legend);
    if (cfg.prefixEnabled) {
      var prefixLabel = document.createElement('label');
      prefixLabel.textContent = cfg.prefixLabel + ' ';
      var select = document.createElement('select');
      select.name = 'tp_prefix';
      var blank = document.createElement('option');
      blank.value = '0'; blank.textContent = 'No prefix';
      select.appendChild(blank);
      (cfg.prefixes || []).forEach(function (p) {
        var option = document.createElement('option');
        option.value = String(p.id);
        option.textContent = p.name;
        select.appendChild(option);
      });
      prefixLabel.appendChild(select);
      box.appendChild(prefixLabel);
    }
    if (cfg.tagsEnabled) {
      var tagsLabel = document.createElement('label');
      tagsLabel.textContent = cfg.tagsLabel + ' (max ' + cfg.maxTags + ') ';
      var input = document.createElement('input');
      input.name = 'tp_tags'; input.type = 'text'; input.maxLength = 350;
      input.placeholder = 'e.g. support, tutorial, solved';
      input.className = 'tp-long-input';
      tagsLabel.appendChild(input);
      box.appendChild(tagsLabel);
    }
    var marker = document.createElement('input');
    marker.type = 'hidden'; marker.name = 'tp_meta_marker'; marker.value = '1';
    box.appendChild(marker);
    var row = subject.closest('dl, .input_text, .input-group, .postarea, div') || subject.parentNode;
    if (row && row.parentNode) row.parentNode.insertBefore(box, row.nextSibling);
    else form.appendChild(box);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
}());
