// AMD module: theme_learnovatheme/focus_pdf
// In-page PDF viewer for focus mode.
//
// Core hands a File resource to an <object> and the browser draws it with its
// own viewer — its own toolbar, its own paging, none of it ours. This module
// draws the document instead, with PDF.js (vendored in theme/learnovatheme/js/
// pdfjs), so the chrome around it is the Learnova one.
//
// The chrome itself — toolbar, scroll area, status line — is Mustache; see
// templates/theme_learnovatheme/focusmode.mustache. The only nodes built here
// are the per-page canvas and text layer, which are rendering output rather
// than page structure and cannot come from a template.
//
// If anything goes wrong (library blocked, document unreadable, encrypted) the
// viewer stands down and core's <object> — still in the DOM, only hidden — is
// unhidden in its place, so the learner always ends up with something that
// shows the file.
define([], function() {
    'use strict';

    // Zoom ladder the +/- buttons step through, mirroring the <select> options.
    var ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2];
    var MIN_SCALE = 0.1;
    var MAX_SCALE = 5;

    // How much of the document, in viewport heights, is rendered ahead of and
    // behind what is on screen. Large enough that scrolling rarely meets a
    // blank page, small enough that a long document is never all in memory.
    var PRERENDER = '150% 0px';

    // Canvases kept alive at once. Beyond this the ones furthest from the page
    // being read are dropped; they re-render when they come back into view.
    var MAX_LIVE = 12;

    var state = null;

    /**
     * Load PDF.js.
     *
     * It ships as ES modules, which Moodle's AMD loader cannot consume, so it
     * is pulled in with a dynamic import() instead of being listed as a
     * dependency above.
     *
     * @param {String} url
     * @return {Promise} Resolves with the library namespace.
     */
    function loadLibrary(url) {
        return import(url);
    }

    /**
     * Give up on the in-page viewer and hand the file back to the browser.
     *
     * @param {String} reason Logged, not shown — the notice text is in the template.
     */
    function standDown(reason) {
        if (state && state.down) {
            return;
        }
        window.console.warn('Learnova focus-mode PDF viewer: ' + reason);
        if (state && state.root) {
            state.down = true;
            state.root.hidden = true;
        }
        var notice = document.querySelector('[data-region="fm-pdf-fallback"]');
        if (notice) {
            notice.hidden = false;
        }
        // Core's embed is emitted on every render and only hidden by this class,
        // so dropping it is all that is needed to get the browser viewer back.
        var native = document.querySelector('.fm-native--replaced');
        if (native) {
            native.classList.remove('fm-native--replaced');
        }

        // Hand scrolling back to the column: it was taken away because the
        // viewer scrolled its own page area, and the browser's embed does not.
        var media = document.querySelector('.fm-media--pdf');
        if (media) {
            media.classList.add('is-fallback');
        }
        var body = document.getElementById('fmContentBody');
        if (body) {
            body.classList.remove('fm-content-body--pdf');
        }
    }

    /**
     * Space a page can occupy, inside the scroll area's own padding.
     *
     * @return {Object} {width, height} in CSS pixels.
     */
    function available() {
        var styles = window.getComputedStyle(state.scroll);
        return {
            width: state.scroll.clientWidth
                - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight),
            height: state.scroll.clientHeight
                - parseFloat(styles.paddingTop) - parseFloat(styles.paddingBottom)
        };
    }

    /**
     * Turn the current zoom mode into a scale factor.
     *
     * The fit modes are measured against the first page: pages of differing
     * size within one document are rare, and letting each page pick its own
     * scale would make the document change size as it is scrolled.
     *
     * @return {Number}
     */
    function currentScale() {
        if (state.mode !== 'width' && state.mode !== 'page') {
            return state.mode;
        }
        var space = available();
        var base = state.base;
        if (space.width <= 0 || !base) {
            return 1;
        }
        var scale = space.width / base.width;
        if (state.mode === 'page' && space.height > 0) {
            scale = Math.min(scale, space.height / base.height);
        }
        return Math.min(Math.max(scale, MIN_SCALE), MAX_SCALE);
    }

    /**
     * Reserve the on-screen box for every page at the current scale.
     *
     * Placeholders use the first page's proportions; a page that turns out to
     * be a different size corrects its own box when it renders. This is what
     * lets the scrollbar be right from the start without reading every page
     * up front.
     */
    function layoutPages() {
        state.pages.forEach(function(entry) {
            var size = entry.size || state.base;
            entry.wrapper.style.width = Math.floor(size.width * state.scale) + 'px';
            entry.wrapper.style.height = Math.floor(size.height * state.scale) + 'px';
            entry.wrapper.style.setProperty('--scale-factor', state.scale);
        });
    }

    /**
     * Throw away a page's rendered output, keeping its reserved box.
     *
     * @param {Object} entry
     */
    function release(entry) {
        if (entry.task) {
            entry.task.cancel();
            entry.task = null;
        }
        if (entry.textlayer) {
            entry.textlayer.cancel();
            entry.textlayer = null;
        }
        // Zeroing the canvas is what actually frees the pixel buffer; clearRect
        // would leave it allocated.
        entry.canvas.width = 0;
        entry.canvas.height = 0;
        entry.text.textContent = '';
        entry.drawn = null;
        entry.rendering = false;
    }

    /**
     * Drop the rendered pages furthest from the one being read.
     */
    function trim() {
        var live = state.pages.filter(function(entry) {
            return entry.drawn !== null && !entry.rendering;
        });
        if (live.length <= MAX_LIVE) {
            return;
        }
        live.sort(function(a, b) {
            return Math.abs(b.num - state.current) - Math.abs(a.num - state.current);
        });
        live.slice(0, live.length - MAX_LIVE).forEach(release);
    }

    /**
     * Draw one page, plus the selectable text on top of it.
     *
     * @param {Object} entry
     * @return {Promise}
     */
    function renderPage(entry) {
        if (entry.rendering || entry.drawn === state.scale) {
            return Promise.resolve();
        }
        release(entry);
        entry.rendering = true;
        var scale = state.scale;

        return state.pdf.getPage(entry.num).then(function(page) {
            if (scale !== state.scale) {
                // Zoomed again while this page was being fetched.
                entry.rendering = false;
                return null;
            }
            var viewport = page.getViewport({scale: scale});

            // First sight of this page's real proportions; correct the box if
            // it does not match the first page.
            entry.size = {width: viewport.width / scale, height: viewport.height / scale};
            entry.wrapper.style.width = Math.floor(viewport.width) + 'px';
            entry.wrapper.style.height = Math.floor(viewport.height) + 'px';
            entry.wrapper.style.setProperty('--scale-factor', scale);

            // Draw at device resolution and scale back down in CSS, otherwise
            // the page is visibly soft on any HiDPI screen.
            var dpr = window.devicePixelRatio || 1;
            entry.canvas.width = Math.floor(viewport.width * dpr);
            entry.canvas.height = Math.floor(viewport.height * dpr);
            entry.canvas.style.width = Math.floor(viewport.width) + 'px';
            entry.canvas.style.height = Math.floor(viewport.height) + 'px';

            entry.task = page.render({
                canvasContext: entry.canvas.getContext('2d'),
                viewport: viewport,
                transform: dpr === 1 ? null : [dpr, 0, 0, dpr, 0, 0]
            });

            return entry.task.promise.then(function() {
                entry.task = null;
                entry.drawn = scale;
                entry.rendering = false;

                // Text layer: transparent spans positioned over the canvas, so
                // the page can be selected, copied and searched with the
                // browser's own Find. Failing here costs only that.
                entry.textlayer = new state.lib.TextLayer({
                    textContentSource: page.streamTextContent(),
                    container: entry.text,
                    viewport: viewport
                });
                return entry.textlayer.render().catch(function() {
                    return null;
                });
            }).then(function() {
                trim();
                return null;
            });
        }).catch(function(error) {
            entry.rendering = false;
            // A cancelled render is the expected outcome of zooming mid-draw.
            if (!error || error.name !== 'RenderingCancelledException') {
                window.console.warn('Learnova focus-mode PDF viewer: page '
                    + entry.num + ' failed to render', error);
            }
            return null;
        });
    }

    /**
     * Render whatever is on screen (plus the pre-render margin) at the current
     * scale. Called on load, on scroll and after every zoom change.
     */
    function renderVisible() {
        state.pages.forEach(function(entry) {
            if (entry.visible) {
                renderPage(entry);
            }
        });
    }

    /**
     * Update the toolbar's page indicator from the scroll position.
     *
     * The page being read is the last one whose top edge has passed a line a
     * third of the way down the viewport — the same rule the browser's own
     * viewers use, and it keeps the number stable while a tall page is crossed.
     */
    function updateIndicator() {
        var line = state.scroll.getBoundingClientRect().top + state.scroll.clientHeight / 3;
        var current = 1;
        state.pages.forEach(function(entry) {
            if (entry.wrapper.getBoundingClientRect().top <= line) {
                current = entry.num;
            }
        });
        if (current !== state.current) {
            state.current = current;
            state.count.textContent = current + ' / ' + state.pages.length;
        }
    }

    /**
     * Apply a new zoom mode and redraw.
     *
     * @param {String|Number} mode 'width', 'page' or an explicit scale.
     */
    function setZoom(mode) {
        state.mode = mode;
        var scale = currentScale();
        if (scale === state.scale) {
            return;
        }

        // Keep the reading position: remember where the current page sits
        // relative to the top of the viewport, and put it back afterwards.
        var anchor = state.pages[state.current - 1];
        var offset = anchor
            ? anchor.wrapper.getBoundingClientRect().top - state.scroll.getBoundingClientRect().top
            : 0;

        state.scale = scale;
        state.pages.forEach(release);
        layoutPages();

        if (anchor) {
            // Measured rather than derived from offsetTop: the wrapper's
            // offsetParent is not guaranteed to be the scroll box.
            var moved = anchor.wrapper.getBoundingClientRect().top
                - state.scroll.getBoundingClientRect().top;
            state.scroll.scrollTop += moved - offset;
        }
        renderVisible();
        updateIndicator();
    }

    /**
     * Step to the next zoom on the ladder, from wherever the fit modes left us.
     *
     * @param {Number} direction 1 to zoom in, -1 to zoom out.
     */
    function stepZoom(direction) {
        var scale = state.scale;
        var next = direction > 0
            ? ZOOMS.find(function(z) {
                return z > scale + 0.001;
            })
            : ZOOMS.slice().reverse().find(function(z) {
                return z < scale - 0.001;
            });
        if (next === undefined) {
            return;
        }
        state.select.value = String(next);
        setZoom(next);
    }

    /**
     * Print the original file rather than the canvases, so the printout is the
     * document at full resolution instead of a screenshot of it.
     */
    function printDocument() {
        var url = state.root.getAttribute('data-url');
        var frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.visibility = 'hidden';
        frame.src = url;
        frame.addEventListener('load', function() {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                // Safari will not drive a framed PDF's print dialogue; open the
                // file so the learner can use the browser's own control.
                window.open(url, '_blank', 'noopener');
            }
        });
        document.body.appendChild(frame);
    }

    /**
     * Build the page boxes and start rendering.
     *
     * @param {Object} pdf The PDF.js document proxy.
     * @return {Promise}
     */
    function buildPages(pdf) {
        state.pdf = pdf;

        return pdf.getPage(1).then(function(first) {
            var viewport = first.getViewport({scale: 1});
            state.base = {width: viewport.width, height: viewport.height};

            var fragment = document.createDocumentFragment();
            for (var num = 1; num <= pdf.numPages; num++) {
                var wrapper = document.createElement('div');
                wrapper.className = 'fm-pdf-page';
                wrapper.setAttribute('data-page', num);

                var canvas = document.createElement('canvas');
                canvas.className = 'fm-pdf-canvas';

                // "textLayer" is PDF.js's own class name and the one its
                // positioning CSS (mirrored in post.scss) is written against.
                var text = document.createElement('div');
                text.className = 'textLayer';

                wrapper.appendChild(canvas);
                wrapper.appendChild(text);
                fragment.appendChild(wrapper);

                state.pages.push({
                    num: num,
                    wrapper: wrapper,
                    canvas: canvas,
                    text: text,
                    size: null,
                    drawn: null,
                    rendering: false,
                    visible: false,
                    task: null,
                    textlayer: null
                });
            }
            state.list.appendChild(fragment);
            state.status.hidden = true;
            state.count.textContent = '1 / ' + pdf.numPages;

            state.scale = currentScale();
            layoutPages();

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(record) {
                    var entry = state.pages[parseInt(record.target.getAttribute('data-page'), 10) - 1];
                    if (!entry) {
                        return;
                    }
                    entry.visible = record.isIntersecting;
                    if (entry.visible) {
                        renderPage(entry);
                    }
                });
            }, {root: state.scroll, rootMargin: PRERENDER});

            state.pages.forEach(function(entry) {
                observer.observe(entry.wrapper);
            });
            return null;
        });
    }

    /**
     * Wire the toolbar and the listeners that keep the view in step with its
     * container.
     */
    function bind() {
        state.root.addEventListener('click', function(e) {
            var trigger = e.target.closest('[data-fm-action]');
            if (!trigger) {
                return;
            }
            var action = trigger.getAttribute('data-fm-action');
            if (action === 'pdf-zoom-in') {
                stepZoom(1);
            } else if (action === 'pdf-zoom-out') {
                stepZoom(-1);
            } else if (action === 'pdf-print') {
                e.preventDefault();
                printDocument();
            }
        });

        state.select.addEventListener('change', function() {
            var value = state.select.value;
            setZoom(value === 'width' || value === 'page' ? value : parseFloat(value));
        });

        var ticking = false;
        state.scroll.addEventListener('scroll', function() {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(function() {
                ticking = false;
                updateIndicator();
            });
        });

        // The fit modes are measured against the container, so anything that
        // resizes it — window, sidebar, description strip — changes the scale.
        var pending = null;
        var refit = function() {
            window.clearTimeout(pending);
            pending = window.setTimeout(function() {
                if (state.mode === 'width' || state.mode === 'page') {
                    setZoom(state.mode);
                }
            }, 150);
        };
        window.addEventListener('resize', refit);
        if (window.ResizeObserver) {
            new ResizeObserver(refit).observe(state.scroll);
        }
    }

    return {
        init: function() {
            var root = document.querySelector('[data-region="fm-pdf"]');
            if (!root) {
                return;
            }

            state = {
                root: root,
                scroll: root.querySelector('[data-region="fm-pdf-scroll"]'),
                list: root.querySelector('[data-region="fm-pdf-pages"]'),
                count: root.querySelector('[data-region="fm-pdf-count"]'),
                status: root.querySelector('[data-region="fm-pdf-status"]'),
                select: root.querySelector('[data-region="fm-pdf-zoom"]'),
                lib: null,
                pdf: null,
                pages: [],
                base: null,
                // Fit width is the sane default for reading: the page is as
                // large as it can be without any horizontal scrolling.
                mode: 'width',
                scale: 1,
                current: 1,
                down: false
            };

            loadLibrary(root.getAttribute('data-lib')).then(function(lib) {
                state.lib = lib;
                lib.GlobalWorkerOptions.workerSrc = root.getAttribute('data-worker');

                var task = lib.getDocument({
                    url: root.getAttribute('data-url'),
                    // Documents that use the 14 standard fonts without embedding
                    // them need these; without the URL they render with wrong
                    // metrics or not at all.
                    standardFontDataUrl: root.getAttribute('data-fonts')
                });
                // An encrypted document is the browser viewer's business — it
                // has a password prompt and we do not.
                task.onPassword = function() {
                    task.destroy();
                    standDown('document is password protected');
                };
                return task.promise;
            }).then(function(pdf) {
                if (!pdf) {
                    return null;
                }
                bind();
                return buildPages(pdf);
            }).catch(function(error) {
                standDown((error && error.message) || 'could not be opened');
                return null;
            });
        }
    };
});
