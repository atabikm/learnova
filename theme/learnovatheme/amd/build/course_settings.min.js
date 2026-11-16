// AMD module: theme_learnovatheme/course_settings
// Progressively enhances the curated-courses admin setting
// (admin_setting_courseitems) into a dynamic add / remove / reorder form. The
// backing textarea holds a JSON array of {courseid}; this module renders one
// editable row per entry (a course <select>), keeps the textarea in sync on
// every change, enforces the max-item cap, and prevents the same course being
// picked twice.
define([], function() {
    'use strict';

    function str(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function enhance(container) {
        var textarea = container.querySelector('.learnova-courseset__data');
        var rows     = container.querySelector('.learnova-courseset__rows');
        var addBtn   = container.querySelector('.learnova-courseset__add');
        var note     = container.querySelector('.learnova-courseset__note');
        if (!textarea || !rows || !addBtn) {
            return;
        }

        var max = parseInt(container.dataset.max, 10) || 3;
        var courseLabel = container.dataset.courseLabel || 'Course';
        var labels = {
            remove: container.dataset.removeLabel || 'Remove',
            up:     container.dataset.upLabel     || 'Move up',
            down:   container.dataset.downLabel   || 'Move down'
        };

        var courses;
        try { courses = JSON.parse(container.dataset.courses || '[]'); } catch (e) { courses = []; }

        var data;
        try { data = JSON.parse(textarea.value || '[]'); } catch (e) { data = []; }
        if (!Array.isArray(data)) { data = []; }

        function rowEls() {
            return rows.querySelectorAll('.learnova-courseset__row');
        }

        function serialize() {
            var items = [];
            rowEls().forEach(function(row) {
                var courseid = parseInt(row.querySelector('.learnova-courseset__course').value, 10);
                if (courseid > 0) {
                    items.push({ courseid: courseid });
                }
            });
            textarea.value = JSON.stringify(items);
            refresh();
        }

        function refresh() {
            var count = rowEls().length;
            addBtn.disabled = count >= max;
            if (note) { note.hidden = count < max; }
            rowEls().forEach(function(row, i) {
                row.querySelector('.learnova-courseset__num').textContent = '#' + (i + 1);
            });
            refreshCourseOptions();
        }

        function buildCourseSelect(selectedVal) {
            var sel = document.createElement('select');
            sel.className = 'form-control form-select learnova-courseset__course';
            courses.forEach(function(opt) {
                var o = document.createElement('option');
                o.value = str(opt.id);
                o.textContent = str(opt.name);
                if (str(opt.id) === str(selectedVal)) { o.selected = true; }
                sel.appendChild(o);
            });
            return sel;
        }

        // The id of the first course not yet chosen in any row, or '' if all are
        // taken. Used to seed a freshly added row.
        function firstFreeCourse() {
            var taken = {};
            rowEls().forEach(function(row) {
                var v = row.querySelector('.learnova-courseset__course').value;
                if (v && v !== '0') { taken[v] = true; }
            });
            for (var i = 0; i < courses.length; i++) {
                if (!taken[String(courses[i].id)]) { return String(courses[i].id); }
            }
            return '';
        }

        // Hides courses already chosen in other rows, so each course can be
        // picked only once. A row always keeps its own current selection.
        function refreshCourseOptions() {
            var taken = {};
            rowEls().forEach(function(row) {
                var v = row.querySelector('.learnova-courseset__course').value;
                if (v && v !== '0') { taken[v] = true; }
            });
            rowEls().forEach(function(row) {
                var sel = row.querySelector('.learnova-courseset__course');
                var own = sel.value;
                Array.prototype.forEach.call(sel.options, function(o) {
                    o.hidden = (o.value !== own && taken[o.value] === true);
                });
            });
        }

        function addRow(item) {
            item = item || {};
            var selectedCourse = item.courseid ? String(item.courseid) : firstFreeCourse();

            var row = document.createElement('div');
            row.className = 'learnova-courseset__row border rounded p-2 mb-2 bg-white';

            // Header: counter + action buttons.
            var head = document.createElement('div');
            head.className = 'd-flex align-items-center justify-content-between mb-2';
            head.innerHTML =
                '<span class="learnova-courseset__num fw-semibold text-muted small"></span>' +
                '<span>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 learnova-courseset__up"' +
                        ' title="' + str(labels.up) + '" aria-label="' + str(labels.up) + '">&uarr;</button>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 learnova-courseset__down"' +
                        ' title="' + str(labels.down) + '" aria-label="' + str(labels.down) + '">&darr;</button>' +
                    '<button type="button" class="btn btn-link btn-sm py-0 text-danger learnova-courseset__remove">' +
                        str(labels.remove) + '</button>' +
                '</span>';
            row.appendChild(head);

            // Course select.
            var courseWrap = document.createElement('div');
            var courseLbl = document.createElement('label');
            courseLbl.className = 'form-label small mb-1';
            courseLbl.textContent = courseLabel;
            courseWrap.appendChild(courseLbl);
            courseWrap.appendChild(buildCourseSelect(selectedCourse));
            row.appendChild(courseWrap);

            rows.appendChild(row);
        }

        // ── Initial render ────────────────────────────────────────────────
        rows.textContent = '';
        data.slice(0, max).forEach(addRow);
        refresh();

        addBtn.addEventListener('click', function() {
            if (rowEls().length >= max) { return; }
            addRow();
            serialize();
        });

        rows.addEventListener('click', function(e) {
            var row;
            if (e.target.closest('.learnova-courseset__remove')) {
                row = e.target.closest('.learnova-courseset__row');
                if (row) { row.remove(); serialize(); }
            } else if (e.target.closest('.learnova-courseset__up')) {
                row = e.target.closest('.learnova-courseset__row');
                if (row && row.previousElementSibling) {
                    rows.insertBefore(row, row.previousElementSibling);
                    serialize();
                }
            } else if (e.target.closest('.learnova-courseset__down')) {
                row = e.target.closest('.learnova-courseset__row');
                if (row && row.nextElementSibling) {
                    rows.insertBefore(row.nextElementSibling, row);
                    serialize();
                }
            }
        });

        rows.addEventListener('change', function(e) {
            if (e.target.closest('.learnova-courseset__course')) {
                serialize();
            }
        });

        var form = container.closest('form');
        if (form) { form.addEventListener('submit', serialize); }

        textarea.hidden = true;
    }

    return {
        init: function() {
            document.querySelectorAll('.learnova-courseset').forEach(enhance);
        }
    };
});
