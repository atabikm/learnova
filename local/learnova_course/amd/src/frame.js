// AMD module: local_learnova_course/frame
// Shared helpers for the Course Management panels that embed a real Moodle page
// (Course Settings, Reports): load it cache-busted, and size the iframe to its
// document CONTENT so there is no inner scrollbar and the management page
// scrolls as one surface.
//
// The panel-specific behaviour (what to do once a frame has loaded) stays in
// the panel's own module — this only carries what every embed needs.
define([], function() {
    'use strict';

    // Measuring body.scrollHeight (not documentElement) matters: the embed CSS
    // forces intrinsic heights, so the body wraps its content while the
    // documentElement can still stretch to the iframe. We also only write when
    // the value actually changed — writing the height must not itself trigger
    // another resize, or a rounding wobble would loop the page taller forever.
    function fit(frame) {
        var doc;
        try {
            doc = frame.contentDocument;
        } catch (e) {
            return; // Cross-origin — shouldn't happen for a same-site embed.
        }
        if (!doc || !doc.body) {
            return;
        }
        var target = doc.body.scrollHeight;
        if (Math.abs((frame._cmHeight || 0) - target) <= 1) {
            return; // Stable — avoid a feedback loop.
        }
        frame._cmHeight = target;
        frame.style.height = target + 'px';
    }

    // Keep the frame sized as its content changes (collapsible sections, filter
    // results). Reactions are batched into an animation frame and guarded
    // against re-entrancy so a burst of mutations can't runaway-grow the page.
    function observe(frame) {
        var doc;
        try {
            doc = frame.contentDocument;
        } catch (e) {
            return;
        }
        if (!window.ResizeObserver || !doc || !doc.body) {
            return;
        }
        var pending = false;
        new window.ResizeObserver(function() {
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function() {
                pending = false;
                fit(frame);
            });
        }).observe(doc.body);
    }

    // Point the frame at a URL, cache-busted so a reopened panel always gets the
    // current theme-CSS revision. The veil (is-loading) is lifted by the caller
    // once it has decided the loaded document is the one it wanted.
    function load(frame, src) {
        if (!src) {
            return;
        }
        frame.dataset.cmLoaded = '1';
        frame.classList.add('is-loading');
        frame.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 'cmcb=' + Date.now();
    }

    return {
        fit: fit,
        observe: observe,
        load: load
    };
});
