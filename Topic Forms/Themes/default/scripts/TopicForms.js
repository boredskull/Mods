(function () {
    'use strict';
    function init() {
        const editor = document.getElementById('bstf_editor');
        if (!editor) return;
        const rows = document.getElementById('bstf_rows');
        const add = document.getElementById('bstf_add');
        const prefix = editor.elements.bstf_prefix_row;
        const picker = document.getElementById('bstf_switch');
        const original = picker.elements.bstf_board.value;
        let dirty = false;
        let next = rows.children.length;
        function allowNavigation() {
            return !dirty || window.confirm(document.getElementById('bstf_discard').value);
        }
        picker.elements.bstf_board.addEventListener('change', function () {
            if (allowNavigation()) picker.submit();
            else this.value = original;
        });
        ['bstf_load', 'bstf_move'].forEach(function (id) {
            const form = document.getElementById(id);
            if (form) form.addEventListener('submit', function (event) {
                if (!allowNavigation()) event.preventDefault();
            });
        });
        function refresh() {
            const selected = prefix.value;
            prefix.options.length = 1;
            Array.from(rows.children).forEach(function (row) {
                const choice = row.querySelector('.bstf-type').value === 'select';
                const label = row.querySelector('.bstf-label').value.trim();
                row.querySelector('.bstf-options').hidden = !choice;
                if (choice && label) prefix.add(new Option(label, row.dataset.index));
                row.querySelector('[data-move="up"]').disabled = !row.previousElementSibling;
                row.querySelector('[data-move="down"]').disabled = !row.nextElementSibling;
            });
            prefix.value = selected;
            if (prefix.selectedIndex < 0) prefix.selectedIndex = 0;
            add.disabled = rows.children.length >= 20;
        }
        // Enter in the layout name must save the layout, not publish the board form
        // (the board Save button is the form's default submit button).
        const layoutName = editor.elements.layout_name;
        const layoutButton = editor.querySelector('button[name="bstf_action"][value="layout"]');
        if (layoutName && layoutButton) layoutName.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            if (editor.requestSubmit) editor.requestSubmit(layoutButton);
            else layoutButton.click();
        });
        editor.addEventListener('input', function () { dirty = true; });
        editor.addEventListener('change', function () { dirty = true; });
        rows.addEventListener('input', refresh);
        rows.addEventListener('change', refresh);
        rows.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('[data-index]');
            if (button.classList.contains('bstf-remove')) row.remove();
            else if (button.dataset.move === 'up') rows.insertBefore(row, row.previousElementSibling);
            else if (button.dataset.move === 'down') rows.insertBefore(row.nextElementSibling, row);
            dirty = true;
            refresh();
        });
        add.addEventListener('click', function () {
            if (rows.children.length >= 20) return;
            const markup = document.getElementById('bstf_new_row').innerHTML;
            rows.insertAdjacentHTML('beforeend', markup.replace(/%IDX%/g, next++));
            dirty = true;
            refresh();
            rows.lastElementChild.querySelector('.bstf-label').focus();
        });
        refresh();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
