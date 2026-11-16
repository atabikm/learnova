// AMD module: local_learnova_course/competencies
// Interactivity for the Course Management "Competencies" panel:
//   - filter the card grid by the activity a competency is attached to;
//   - the "Add competencies to course" slide-in: switch framework, search, tick
//     competencies, then add them through the NATIVE web service.
//
// Adding is core_competency_add_competency_to_course — the same call Moodle's
// own picker makes — so capability checks, duplicate handling and events are
// Moodle's, not ours.
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    function slide(root) {
        return {
            panel: root.querySelector('[data-region="cp-slide"]'),
            veil: root.querySelector('[data-region="cp-veil"]')
        };
    }

    // Show only the cards attached to the chosen activity (0 = all).
    function filterCards(root, cmid) {
        var shown = 0;
        root.querySelectorAll('.lm-cp-card').forEach(function(card) {
            var cmids = (card.getAttribute('data-cmids') || '').split(' ');
            var match = !cmid || cmids.indexOf(cmid) !== -1;
            card.hidden = !match;
            shown += match ? 1 : 0;
        });
        var nomatch = root.querySelector('[data-region="cp-nomatch"]');
        if (nomatch) {
            nomatch.hidden = shown > 0;
        }
    }

    // Show the chosen framework's list, and inside it only the rows matching
    // the search text.
    function filterPicker(root) {
        var fwid = root.querySelector('[data-action="cp-framework"]').value;
        var needle = (root.querySelector('[data-action="cp-search"]').value || '').toLowerCase();

        root.querySelectorAll('[data-region="cp-fw"]').forEach(function(group) {
            var on = group.getAttribute('data-fw') === fwid;
            group.hidden = !on;
            if (!on) {
                return;
            }
            group.querySelectorAll('.lm-cp-pick').forEach(function(row) {
                var name = (row.getAttribute('data-name') || '').toLowerCase();
                row.hidden = needle !== '' && name.indexOf(needle) === -1;
            });
        });
    }

    function close(root) {
        var s = slide(root);
        s.panel.classList.remove('is-open');
        s.veil.classList.remove('is-open');
    }

    function save(root) {
        var s = slide(root);
        var courseid = parseInt(s.panel.getAttribute('data-courseid'), 10);
        var calls = [];

        s.panel.querySelectorAll('[data-region="cp-pick"]').forEach(function(box) {
            if (box.checked && !box.disabled) {
                calls.push({
                    methodname: 'core_competency_add_competency_to_course',
                    args: {courseid: courseid, competencyid: parseInt(box.value, 10)}
                });
            }
        });
        if (!calls.length) {
            close(root);
            return;
        }
        Promise.all(Ajax.call(calls)).then(function() {
            // The grid, the summary and the filter options all change — reload.
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    }

    return {
        init: function() {
            var root = document.querySelector('[data-cm-panel="competencies"]');
            if (!root || root.dataset.lvCpInit) {
                return;
            }
            root.dataset.lvCpInit = '1';

            root.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="cp-add"]')) {
                    var s = slide(root);
                    s.veil.classList.add('is-open');
                    s.panel.classList.add('is-open');
                    return;
                }
                if (e.target.closest('[data-action="cp-close"]')
                        || e.target.closest('[data-region="cp-veil"]')) {
                    close(root);
                    return;
                }
                if (e.target.closest('[data-action="cp-save"]')) {
                    save(root);
                }
            });

            root.addEventListener('change', function(e) {
                if (e.target.matches('[data-action="cp-filter"]')) {
                    filterCards(root, e.target.value === '0' ? '' : e.target.value);
                } else if (e.target.matches('[data-action="cp-framework"]')) {
                    filterPicker(root);
                }
            });

            root.addEventListener('input', function(e) {
                if (e.target.matches('[data-action="cp-search"]')) {
                    filterPicker(root);
                }
            });

            document.addEventListener('keydown', function(e) {
                var s = slide(root);
                if (e.key === 'Escape' && s.panel && s.panel.classList.contains('is-open')) {
                    close(root);
                }
            });
        }
    };
});
