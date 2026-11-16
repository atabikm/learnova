// AMD module: local_learnova_course/activities
// Interactivity for the Course Management "Activities" panel: collapse/expand
// an activity-type card by clicking (or keyboard-activating) its header, the
// same way the Course Settings form's sections collapse.
//
// Everything else in the panel is server-rendered — the cells come from
// Moodle's activity-overview API and register their own JS (forum subscribe
// toggle, digest dropdown), so this module must not touch them.
define([], function() {
    'use strict';

    function toggle(head) {
        var card = head.closest('.lm-actgrp');
        var body = card ? card.querySelector('.lm-actgrp__body') : null;
        if (!body) {
            return;
        }
        var collapsed = head.classList.toggle('is-collapsed');
        body.hidden = collapsed;
        head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    return {
        init: function() {
            var root = document.querySelector('[data-cm-panel="activities"]');
            if (!root || root.dataset.lvActInit) {
                return;
            }
            root.dataset.lvActInit = '1';

            root.addEventListener('click', function(e) {
                var head = e.target.closest('[data-action="act-toggle"]');
                if (head) {
                    toggle(head);
                }
            });

            root.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var head = e.target.closest('[data-action="act-toggle"]');
                if (head) {
                    e.preventDefault();
                    toggle(head);
                }
            });
        }
    };
});
