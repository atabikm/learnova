// AMD module: local_learnova_course/management
// Interactivity for the Course Management page (management.php):
//   - switch the main-area panel when a sidebar menu item is clicked (show/hide,
//     no reload), scrolling back to the top so the new panel starts at its header
//   - the Course Settings panel embeds the native /course/edit.php form in an
//     iframe (chrome-less clembed shell, restyled as cards): lazy-load it on
//     first view, auto-size it to its content so the page scrolls as one, and
//     after a save/cancel reload it back to the form (staying in the panel).
// All structure is rendered server-side by management.mustache; the loading and
// sizing of embedded frames is shared with the Reports panel via
// local_learnova_course/frame.
define(['local_learnova_course/frame'], function(Frame) {
    'use strict';

    function loadFrame(frame) {
        Frame.load(frame, frame.getAttribute('data-src'));
    }

    function initFrame(frame) {
        frame.addEventListener('load', function() {
            var href, doc;
            try {
                href = frame.contentWindow.location.href;
                doc = frame.contentDocument;
            } catch (e) {
                return;
            }
            if (!href || href === 'about:blank') {
                return;
            }
            // Saved or cancelled: the native form has redirected away to the
            // course view, which renders with its full chrome (no embed marker
            // class). Take the whole page there — "save and return to the
            // course", like the landing edit panels — rather than showing a
            // whole Moodle page inside the panel. The frame stays veiled
            // (is-loading, set on submit) so that redirect never flashes.
            //
            // The marker is what makes this work for ANY embedded form, not
            // just /course/edit.php: matching on the URL would bounce the page
            // the moment a panel embedded a different form.
            if (doc && doc.body && !doc.body.classList.contains('learnova-embed')) {
                window.location.assign(href);
                return;
            }
            // The form itself: reveal it, size it, and keep it sized as its
            // collapsible sections open/close.
            frame.classList.remove('is-loading');
            Frame.fit(frame);
            Frame.observe(frame);
            // Re-veil on submit so the post-save redirect never flashes.
            try {
                var doc = frame.contentDocument;
                if (doc) {
                    doc.addEventListener('submit', function() {
                        frame.classList.add('is-loading');
                    }, true);
                }
            } catch (e) {
                // Ignore — the one-shot fit above already ran.
            }
        });
    }

    return {
        init: function() {
            var root = document.querySelector('.learnova-cm');
            if (!root) {
                return;
            }

            // Wire every embedded frame; load the one in the active panel now,
            // the rest on first view (lazy).
            root.querySelectorAll('[data-region="cm-frame"]').forEach(function(frame) {
                initFrame(frame);
                if (frame.closest('.learnova-cm__panel.is-show')) {
                    loadFrame(frame);
                }
            });

            root.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-cm-nav]');
                if (!btn) {
                    return;
                }
                var key = btn.getAttribute('data-cm-nav');

                root.querySelectorAll('[data-cm-nav]').forEach(function(item) {
                    item.classList.toggle('is-on', item === btn);
                });
                var active = null;
                root.querySelectorAll('[data-cm-panel]').forEach(function(panel) {
                    var on = panel.getAttribute('data-cm-panel') === key;
                    panel.classList.toggle('is-show', on);
                    if (on) {
                        active = panel;
                    }
                });

                if (active) {
                    // Lazy-load a not-yet-loaded frame the first time its panel
                    // opens.
                    var frame = active.querySelector('[data-region="cm-frame"]');
                    if (frame && !frame.dataset.cmLoaded) {
                        loadFrame(frame);
                    }
                    // Panels that lazy-load their own content (Reports) listen
                    // for this instead of watching the class change.
                    active.dispatchEvent(new CustomEvent('learnova-cm:panelshown', {bubbles: true}));
                }

                // The window is the scroll container (the sidebar scrolls
                // independently via position: sticky).
                window.scrollTo(0, 0);
            });
        }
    };
});
