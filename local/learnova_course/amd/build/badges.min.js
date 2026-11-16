// AMD module: local_learnova_course/badges
// Interactivity for the Course Management "Badges" panel:
//   - "+ New Badge" and a row's Edit slide in the NATIVE badge form (the same
//     slide-in component the course landing and Question Banks panels use);
//   - clicking a badge row opens that badge's overview — the entry point Focus
//     Mode will take over once its own story lands.
//
// URLs come from data attributes, never from JS literals: Mustache escapes & to
// &amp; in both, but only in an attribute does the HTML parser decode it back.
define(['core/str'], function(Str) {
    'use strict';

    var BADGE_FORM = '/badges/edit.php';

    function parts(root) {
        return {
            slide: root.querySelector('[data-region="bd-slide"]'),
            veil: root.querySelector('[data-region="bd-veil"]'),
            frame: root.querySelector('[data-region="bd-slide-frame"]'),
            title: root.querySelector('[data-region="bd-slide-title"]')
        };
    }

    function close(root) {
        var p = parts(root);
        p.slide.classList.remove('is-open');
        p.veil.classList.remove('is-open');
        p.frame.src = 'about:blank';
    }

    function openForm(root, url, title) {
        var p = parts(root);
        if (title) {
            p.title.textContent = title;
        }
        p.frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'cb=' + Date.now();
        p.veil.classList.add('is-open');
        p.slide.classList.add('is-open');
    }

    return {
        init: function() {
            var root = document.querySelector('[data-cm-panel="badges"]');
            if (!root || root.dataset.lvBdInit) {
                return;
            }
            root.dataset.lvBdInit = '1';
            var p = parts(root);

            // A saved or cancelled badge form leaves edit.php — the list behind
            // the panel has changed, so take the page back to it.
            p.frame.addEventListener('load', function() {
                var href;
                try {
                    href = p.frame.contentWindow.location.href;
                } catch (e) {
                    return;
                }
                if (!href || href === 'about:blank') {
                    return;
                }
                if (href.indexOf(BADGE_FORM) === -1) {
                    window.location.reload();
                }
            });

            root.addEventListener('click', function(e) {
                var add = e.target.closest('[data-action="bd-add"]');
                if (add) {
                    Str.get_string('bd_new_title', 'local_learnova_course').then(function(title) {
                        openForm(root, add.getAttribute('data-url'), title);
                        return null;
                    }).catch(function() {
                        openForm(root, add.getAttribute('data-url'), null);
                    });
                    return;
                }

                var edit = e.target.closest('[data-action="bd-edit"]');
                if (edit) {
                    openForm(root, edit.getAttribute('data-url'), edit.getAttribute('data-name'));
                    return;
                }

                if (e.target.closest('[data-action="bd-close"]')
                        || e.target.closest('[data-region="bd-veil"]')) {
                    close(root);
                    return;
                }

                // Anywhere else on the row: the badge's own overview.
                var row = e.target.closest('[data-action="bd-open"]');
                if (row) {
                    window.location.assign(row.getAttribute('data-url'));
                }
            });

            root.addEventListener('keydown', function(e) {
                var row = e.target.closest('[data-action="bd-open"]');
                if (row && (e.key === 'Enter' || e.key === ' ')) {
                    e.preventDefault();
                    window.location.assign(row.getAttribute('data-url'));
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && p.slide.classList.contains('is-open')) {
                    close(root);
                }
            });
        }
    };
});
