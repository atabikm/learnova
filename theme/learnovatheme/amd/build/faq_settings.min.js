// AMD module: theme_learnovatheme/faq_settings
// Progressively enhances the FAQ admin-setting (admin_setting_faqitems) into a
// dynamic add / remove / reorder form. The backing textarea holds a JSON array
// of {q, a}; this module renders one editable row per entry, keeps the textarea
// in sync (so the value persists on submit) and enforces the max-item cap.
define([], function() {
    'use strict';

    function str(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function enhance(container) {
        var textarea = container.querySelector('.learnova-faqset__data');
        var rows = container.querySelector('.learnova-faqset__rows');
        var addBtn = container.querySelector('.learnova-faqset__add');
        var note = container.querySelector('.learnova-faqset__note');
        if (!textarea || !rows || !addBtn) {
            return;
        }

        var max = parseInt(container.dataset.max, 10) || 10;
        var labels = {
            remove: container.dataset.removeLabel || 'Remove',
            up: container.dataset.upLabel || 'Move up',
            down: container.dataset.downLabel || 'Move down',
            questionPh: container.dataset.questionPh || 'Question',
            answerPh: container.dataset.answerPh || 'Answer'
        };

        var data;
        try {
            data = JSON.parse(textarea.value || '[]');
        } catch (e) {
            data = [];
        }
        if (!Array.isArray(data)) {
            data = [];
        }

        function rowEls() {
            return rows.querySelectorAll('.learnova-faqset__row');
        }

        function serialize() {
            var items = [];
            rowEls().forEach(function(row) {
                items.push({
                    q: row.querySelector('.learnova-faqset__q').value,
                    a: row.querySelector('.learnova-faqset__a').value
                });
            });
            textarea.value = JSON.stringify(items);
            refresh();
        }

        function refresh() {
            var count = rowEls().length;
            var full = count >= max;
            addBtn.disabled = full;
            if (note) {
                note.hidden = !full;
            }
            rowEls().forEach(function(row, i) {
                row.querySelector('.learnova-faqset__num').textContent = '#' + (i + 1);
            });
        }

        function addRow(item) {
            item = item || {q: '', a: ''};
            var row = document.createElement('div');
            row.className = 'learnova-faqset__row';
            row.innerHTML =
                '<div class="learnova-faqset__row-head">' +
                    '<span class="learnova-faqset__num"></span>' +
                    '<span class="learnova-faqset__actions">' +
                        '<button type="button" class="btn btn-link btn-sm learnova-faqset__up" title="' +
                            str(labels.up) + '" aria-label="' + str(labels.up) + '">&uarr;</button>' +
                        '<button type="button" class="btn btn-link btn-sm learnova-faqset__down" title="' +
                            str(labels.down) + '" aria-label="' + str(labels.down) + '">&darr;</button>' +
                        '<button type="button" class="btn btn-link btn-sm learnova-faqset__remove">' +
                            str(labels.remove) + '</button>' +
                    '</span>' +
                '</div>' +
                '<input type="text" class="form-control learnova-faqset__q">' +
                '<textarea class="form-control learnova-faqset__a" rows="2"></textarea>';
            // Set user data via value/placeholder (never innerHTML) to avoid injection.
            var q = row.querySelector('.learnova-faqset__q');
            var a = row.querySelector('.learnova-faqset__a');
            q.placeholder = str(labels.questionPh);
            a.placeholder = str(labels.answerPh);
            q.value = str(item.q);
            a.value = str(item.a);
            rows.appendChild(row);
        }

        // Initial render from the stored JSON.
        rows.textContent = '';
        data.slice(0, max).forEach(addRow);
        refresh();

        addBtn.addEventListener('click', function() {
            if (rowEls().length >= max) {
                return;
            }
            addRow();
            serialize();
        });

        rows.addEventListener('click', function(e) {
            var row;
            if (e.target.closest('.learnova-faqset__remove')) {
                row = e.target.closest('.learnova-faqset__row');
                if (row) {
                    row.remove();
                    serialize();
                }
            } else if (e.target.closest('.learnova-faqset__up')) {
                row = e.target.closest('.learnova-faqset__row');
                if (row && row.previousElementSibling) {
                    rows.insertBefore(row, row.previousElementSibling);
                    serialize();
                }
            } else if (e.target.closest('.learnova-faqset__down')) {
                row = e.target.closest('.learnova-faqset__row');
                if (row && row.nextElementSibling) {
                    rows.insertBefore(row.nextElementSibling, row);
                    serialize();
                }
            }
        });

        rows.addEventListener('input', function(e) {
            if (e.target.closest('.learnova-faqset__row')) {
                serialize();
            }
        });

        // Safety net: re-sync on submit in case a programmatic change slipped by.
        var form = container.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }

        // Hide the raw JSON textarea now that the form is live.
        textarea.hidden = true;
    }

    return {
        init: function() {
            document.querySelectorAll('.learnova-faqset').forEach(enhance);
        }
    };
});
