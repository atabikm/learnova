// AMD module: local_learnova_course/tabframe
// Behaviour for the shared tabbed component (templates/tabframe.mustache): a
// .lm-subtabs bar over tab bodies of two kinds, mixed freely in one bar —
//   * local tabs, whose markup is ours and sits in the page already;
//   * embed tabs, which point the shared iframe at a real Moodle page
//     (chrome-less, ?clembed=1) and size it to its content.
//
// Used by the Course Management Grades, Reports and Course Reuse panels. Embed
// tabs load lazily — only once their panel is actually opened — so the
// management page never pays for a page the user hasn't asked for.
define(['local_learnova_course/frame'], function(Frame) {
    'use strict';

    function isLocal(tab) {
        return tab.getAttribute('data-tf-local') === '1';
    }

    // Show one tab: its state, its note, and either its own pane or the frame.
    function show(root, key) {
        var tab = root.querySelector('[data-tf-tab="' + key + '"]');
        if (!tab) {
            return;
        }
        var local = isLocal(tab);
        var frame = root.querySelector('[data-region="tf-frame"]');
        var embed = root.querySelector('[data-region="tf-embed"]');

        root.querySelectorAll('[data-tf-tab]').forEach(function(item) {
            item.classList.toggle('is-on', item === tab);
        });
        root.querySelectorAll('[data-tf-note]').forEach(function(note) {
            note.hidden = note.getAttribute('data-tf-note') !== key;
        });
        root.querySelectorAll('[data-tf-pane]').forEach(function(pane) {
            pane.hidden = pane.getAttribute('data-tf-pane') !== key;
        });

        if (embed) {
            embed.hidden = local;
        }
        if (local || !frame) {
            return;
        }
        // Drop the previous page's measured height so the new one starts fresh
        // instead of inheriting a taller frame.
        frame.style.height = '';
        frame._cmHeight = 0;
        Frame.load(frame, tab.getAttribute('data-url'));
    }

    // Load the selected tab's page, once — only if it is an embed tab.
    function loadActive(root) {
        var frame = root.querySelector('[data-region="tf-frame"]');
        if (!frame || frame.dataset.cmLoaded) {
            return;
        }
        var tab = root.querySelector('[data-tf-tab].is-on');
        if (tab && !isLocal(tab)) {
            Frame.load(frame, tab.getAttribute('data-url'));
        }
    }

    function initRoot(root) {
        if (root.dataset.lvTfInit) {
            return;
        }
        root.dataset.lvTfInit = '1';

        var frame = root.querySelector('[data-region="tf-frame"]');
        if (frame) {
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
                // The loaded page is outside the embedded flow — a finished
                // backup landing on the course, a report link to a profile — so
                // it rendered with its full chrome (no embed marker class).
                // Showing a whole Moodle page inside the panel would be nonsense:
                // take the top window there instead.
                if (doc && doc.body && !doc.body.classList.contains('learnova-embed')) {
                    window.location.assign(href);
                    return;
                }
                frame.classList.remove('is-loading');
                Frame.fit(frame);
                Frame.observe(frame);
            });
        }

        root.addEventListener('click', function(e) {
            var tab = e.target.closest('[data-tf-tab]');
            if (tab) {
                show(root, tab.getAttribute('data-tf-tab'));
            }
        });

        // The component sits in one of many panels: load on first open (or now,
        // if it is the panel the page opened with).
        var panel = root.closest('[data-cm-panel]');
        if (panel) {
            panel.addEventListener('learnova-cm:panelshown', function() {
                loadActive(root);
            });
            if (panel.classList.contains('is-show')) {
                loadActive(root);
            }
        } else {
            loadActive(root);
        }
    }

    return {
        init: function() {
            document.querySelectorAll('[data-region="lm-tabframe"]').forEach(initRoot);
        }
    };
});
