// AMD module: theme_learnovatheme/catalogue
// Interactivity-only helpers for the Course Catalogue:
//   - collapse/expand a filter card via its header chevron
//   - navigate when the sort dropdown changes (each option carries its URL)
// All real filtering/sorting happens server-side via links/URLs; this module
// never injects structural HTML.
define([], function() {
    'use strict';

    return {
        init: function() {
            // Collapse / expand a filter card.
            document.addEventListener('click', function(e) {
                var head = e.target.closest('[data-lc-toggle]');
                if (!head) {
                    return;
                }
                var body = head.nextElementSibling;
                var chev = head.querySelector('.learnova-filter-card__chevron');
                if (body) {
                    var open = body.style.display !== 'none';
                    body.style.display = open ? 'none' : 'block';
                    if (chev) {
                        chev.classList.toggle('is-open', !open);
                    }
                }
            });

            // Sort dropdown: go to the selected option's URL.
            document.addEventListener('change', function(e) {
                var sel = e.target.closest('[data-lc-sort]');
                if (sel && sel.value) {
                    window.location.href = sel.value;
                }
            });
        }
    };
});
