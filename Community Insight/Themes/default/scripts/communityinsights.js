/* Community Insights: optional drag and keyboard-friendly widget ordering. */
(function () {
  'use strict';
  function setup() {
    var list = document.getElementById('ci-widget-list');
    if (!list) return;
    var dragged = null;
    // A preset replaces the widget choices on save, so any manual change switches back to Custom.
    var preset = document.getElementById('ci-preset');
    function custom() { if (preset) preset.value = 'custom'; }
    list.addEventListener('change', custom);
    list.addEventListener('dragstart', function (e) {
      var el = e.target.closest('.ci-widget-choice');
      if (!el) return;
      dragged = el;
      if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', function (e) { if (e.target.closest('.ci-widget-choice')) e.preventDefault(); });
    list.addEventListener('drop', function (e) {
      var target = e.target.closest('.ci-widget-choice');
      if (dragged && target && dragged !== target) {
        e.preventDefault();
        var r = target.getBoundingClientRect();
        list.insertBefore(dragged, e.clientY > r.top + r.height / 2 ? target.nextSibling : target);
        custom();
      }
      dragged = null;
    });
    list.addEventListener('dragend', function () { dragged = null; });
    list.addEventListener('click', function (e) {
      var button = e.target.closest('button');
      if (!button) return;
      var row = button.closest('.ci-widget-choice');
      if (!row) return;
      if (button.classList.contains('ci-up') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (button.classList.contains('ci-down') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      custom();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup); else setup();
}());
