// AMD module: theme_learnovatheme/site_admin
// Behaviour for the Site administration outline rendered by the theme override
// of core/settings_link_page:
//
//   1. Collapse/expand of the category cards. The Plugins tab alone has 28
//      cards, so being able to fold one away matters.
//   2. Keeping the sidebar's active item in step with the visible pane.
//   3. The in-page viewer: an admin page picked from the cards opens in a panel
//      beside the sidebar (iframe, chrome-less render from layout/drawers.php)
//      instead of navigating the whole window away. The URL tracks it as
//      ?open=<page>, so refresh, back/forward and sharing the link all work.
//
// Pane switching is done here rather than by Bootstrap's tab plugin, because
// two tablists point at the same panes: this sidebar, and core's secondary
// navigation (hidden by SCSS but still in the DOM, since Boost's loader needs
// its triggers to resolve #link<key> deep links).
//
// Bootstrap deactivates the previous pane by looking for .active among the
// clicked trigger's *own* siblings, so with two navs its bookkeeping goes stale
// the moment a deep link activates a pane through the other one — leaving two
// panes active at once. Setting every pane's state explicitly from one place
// sidesteps that entirely.
//
// Deep links still land correctly: the loader clicks the hidden secondary-nav
// trigger, Bootstrap fires shown.bs.tab, and syncFromPanes() picks it up.
//
// Cards render expanded from Mustache, so the page is complete and usable with
// JS disabled — save for the bookmark bar, which is per-user data the outline's
// fixed template context cannot carry, so it is resolved server-side (see
// theme_learnovatheme\site_admin::bookmarks) and painted here into a Mustache
// placeholder. Label-only chips, by request.
define([], function() {
    'use strict';

    var COLLAPSED = 'is-collapsed';
    var ACTIVE = 'is-on';

    function toggle(section) {
        var header = section.querySelector('.lsa-section__hd');
        var collapsed = section.classList.toggle(COLLAPSED);
        if (header) {
            header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function panes(root) {
        return root.querySelectorAll('.tab-content > .tab-pane');
    }

    /** Point the sidebar at whichever pane is currently active. */
    function syncFromPanes(root) {
        var pane = root.querySelector('.tab-content > .tab-pane.active');
        var id = pane ? pane.id : null;

        root.querySelectorAll('.lsa-sbi').forEach(function(item) {
            var on = id !== null && item.getAttribute('href') === '#' + id;
            item.classList.toggle(ACTIVE, on);
            item.setAttribute('aria-selected', on ? 'true' : 'false');
            item.setAttribute('tabindex', on ? '0' : '-1');
        });
    }

    /**
     * Show exactly one pane. Sole owner of pane visibility on this page.
     *
     * Resolves the target before touching anything, so an id with no pane
     * leaves the current one alone instead of blanking the page. That is a live
     * case, not a defensive one: stock Boost renders a pane for every category
     * including short-branch ones, this outline does not, so an older
     * #linkbadges-style link arrives here pointing at nothing.
     *
     * @return {boolean} whether a matching pane was found.
     */
    function activate(root, id) {
        if (!id) {
            return false;
        }

        var all = panes(root);
        var target = null;

        all.forEach(function(pane) {
            if (pane.id === id) {
                target = pane;
            }
        });

        if (!target) {
            return false;
        }

        all.forEach(function(pane) {
            var on = pane === target;
            pane.classList.toggle('active', on);
            pane.classList.toggle('show', on);
        });

        syncFromPanes(root);
        return true;
    }

    /**
     * Paint the bookmark bar from the server-resolved list and reveal it.
     * Left hidden (as it renders from Mustache) when there is nothing to show.
     * Labels via textContent, so a bookmarked page's name cannot inject markup.
     */
    function renderBookmarks(root, bookmarks) {
        var bar = root.querySelector('[data-region="lsa-bookmarks"]');
        var list = root.querySelector('[data-region="lsa-bookmarks-list"]');
        if (!bar || !list || !bookmarks || !bookmarks.length) {
            return;
        }

        bookmarks.forEach(function(bm) {
            if (!bm || !bm.url || !bm.label) {
                return;
            }
            var chip = document.createElement('a');
            chip.className = 'lsa-bookmark';
            chip.href = bm.url;
            chip.textContent = bm.label;
            list.appendChild(chip);
        });

        if (list.children.length) {
            bar.hidden = false;
        }
    }

    // ── In-page viewer ───────────────────────────────────────────────────────

    var VIEWING = 'lsa-viewing';

    /** Same-origin URL inside this Moodle, or null. */
    function internalUrl(href) {
        var url;
        try {
            url = new URL(href, window.location.href);
        } catch (e) {
            return null;
        }
        var www = new URL(M.cfg.wwwroot + '/');
        if (url.origin !== www.origin || url.pathname.indexOf(www.pathname) !== 0) {
            return null;
        }
        url.searchParams.delete('lsaembed');
        return url;
    }

    /** Path + query + hash relative to wwwroot: the ?open= value. */
    function toRel(url) {
        var base = new URL(M.cfg.wwwroot + '/').pathname.replace(/\/$/, '');
        return url.pathname.slice(base.length) + url.search + url.hash;
    }

    function withEmbed(url) {
        var u = new URL(url.href);
        u.searchParams.set('lsaembed', '1');
        return u.href;
    }

    function isOutline(url) {
        return /\/admin\/search\.php$/.test(url.pathname);
    }

    /** The outline card item (if any) pointing at this page. */
    function findItem(root, url) {
        var rel = toRel(url).replace(/#.*$/, '');
        var hit = null;
        root.querySelectorAll('.tab-content .lsa-item').forEach(function(a) {
            if (hit) {
                return;
            }
            var u = internalUrl(a.getAttribute('href'));
            if (u && toRel(u).replace(/#.*$/, '') === rel) {
                hit = a;
            }
        });
        return hit;
    }

    function activePaneId(root) {
        var pane = root.querySelector('.tab-content > .tab-pane.active');
        return pane ? pane.id : '';
    }

    function tabLabel(root, paneid) {
        var item = root.querySelector('.lsa-sbi[href="#' + paneid + '"] .lsa-sbi__lbl');
        return item ? item.textContent.trim() : '';
    }

    function stateUrl(rel, paneid) {
        var u = new URL(window.location.href);
        u.search = '';
        if (rel) {
            u.searchParams.set('open', rel);
        }
        u.hash = paneid ? '#' + paneid : '';
        return u.pathname + u.search + u.hash;
    }

    function Viewer(root) {
        this.root = root;
        this.el = root.querySelector('[data-region="lsa-viewer"]');
        this.frame = root.querySelector('[data-region="lsa-viewer-frame"]');
        this.title = root.querySelector('[data-region="lsa-viewer-title"]');
        this.crumb = root.querySelector('[data-region="lsa-viewer-tab"]');
        this.newtab = root.querySelector('[data-region="lsa-viewer-newtab"]');
        this.current = null;
        this.retried = null;
        this.basetitle = document.title;
        this.frame.addEventListener('load', this.onLoad.bind(this));
    }

    Viewer.prototype.isOpen = function() {
        return !this.el.hidden;
    };

    Viewer.prototype.setLoading = function(on) {
        this.el.classList.toggle('is-loading', on);
    };

    /** Point breadcrumb, sidebar and new-tab link at the given page. */
    Viewer.prototype.describe = function(url, label) {
        var item = findItem(this.root, url);
        if (item) {
            var pane = item.closest('.tab-pane');
            if (pane && pane.id !== activePaneId(this.root)) {
                activate(this.root, pane.id);
            }
            label = label || item.textContent.trim();
        }
        if (label) {
            this.title.textContent = label;
        }
        this.crumb.textContent = tabLabel(this.root, activePaneId(this.root));
        this.newtab.href = url.href;
    };

    /**
     * Show a page in the viewer.
     *
     * @param {URL} url target (internal)
     * @param {string} label title to show until the page names itself
     * @param {string} mode 'push' | 'replace' | 'none' — history handling
     */
    Viewer.prototype.open = function(url, label, mode) {
        this.current = url;
        this.retried = null;
        this.title.textContent = label || '';
        this.describe(url, label);
        this.el.hidden = false;
        document.body.classList.add(VIEWING);
        this.setLoading(true);
        this.frame.contentWindow.location.replace(withEmbed(url));

        var state = {lsa: 1, open: toRel(url), tab: activePaneId(this.root), pushed: mode === 'push'};
        if (mode === 'push') {
            window.history.pushState(state, '', stateUrl(state.open, state.tab));
        } else if (mode === 'replace') {
            window.history.replaceState(state, '', stateUrl(state.open, state.tab));
        }
        window.scrollTo(0, 0);
    };

    /** Back to the card overview. Unloads the page so it stops running. */
    Viewer.prototype.close = function(mode) {
        if (!this.isOpen()) {
            return;
        }
        this.el.hidden = true;
        document.body.classList.remove(VIEWING);
        this.setLoading(false);
        this.current = null;
        this.frame.contentWindow.location.replace('about:blank');
        document.title = this.basetitle;
        if (mode === 'replace') {
            var tab = activePaneId(this.root);
            window.history.replaceState({lsa: 1, open: null, tab: tab}, '', stateUrl(null, tab));
        }
    };

    Viewer.prototype.onLoad = function() {
        var win = this.frame.contentWindow;
        var doc, href;
        try {
            doc = this.frame.contentDocument;
            href = win.location.href;
        } catch (e) {
            // Cross-origin (an external link followed in the panel): hand it
            // to the window rather than show something we cannot track.
            this.close('none');
            return;
        }
        if (!href || href === 'about:blank' || !this.isOpen()) {
            return;
        }

        var url = internalUrl(href);
        if (!url) {
            window.location.assign(href);
            return;
        }

        // A link back to the outline itself (or a settings search) belongs to
        // the window, not nested inside the panel.
        if (isOutline(url)) {
            if (url.search && url.search !== '?') {
                window.location.assign(url.href);
            } else {
                var self = this;
                window.setTimeout(function() {
                    if (url.hash) {
                        activate(self.root, url.hash.slice(1));
                    }
                    self.close('replace');
                }, 0);
            }
            return;
        }

        if (!doc || !doc.body || !doc.body.classList.contains('learnova-embed')) {
            // Rendered with full chrome. Either the browser sent no
            // Sec-Fetch-Dest (ask once more, explicitly), or the page is not
            // one that can live in the panel (login, maintenance, focus mode):
            // let it take the window.
            if (this.retried !== url.href && doc && doc.querySelector('#page.drawers')) {
                this.retried = url.href;
                win.location.replace(withEmbed(url));
                return;
            }
            window.location.assign(url.href);
            return;
        }

        this.setLoading(false);
        this.current = url;
        var pagetitle = (doc.title || '').split(/\s+\|\s+/)[0].trim();
        this.describe(url, findItem(this.root, url) ? null : pagetitle);
        if (pagetitle) {
            document.title = pagetitle + ' | ' + this.basetitle;
        }

        // Keep the address bar on the page actually shown, so a refresh or a
        // copied link reopens it — not the one first clicked.
        var st = window.history.state || {};
        window.history.replaceState({lsa: 1, open: toRel(url), tab: activePaneId(this.root), pushed: !!st.pushed},
            '', stateUrl(toRel(url), activePaneId(this.root)));

        // Show progress while the panel navigates on its own (form submit,
        // in-page link), until the next load event.
        var viewer = this;
        win.addEventListener('beforeunload', function() {
            viewer.setLoading(true);
        });
        // Links meant for a new window still get one; everything else stays.
        doc.addEventListener('click', function(e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (a && a.target === '_top') {
                e.preventDefault();
                window.location.assign(a.href);
            }
        });
    };

    return {
        init: function(config) {
            var root = document.querySelector('.learnova-siteadmin');
            if (!root) {
                return;
            }

            renderBookmarks(root, (config && config.bookmarks) || []);

            var viewer = root.querySelector('[data-region="lsa-viewer"]') ? new Viewer(root) : null;

            // Delegated so the handlers keep working if a pane is re-rendered.
            root.addEventListener('click', function(e) {
                var item = e.target.closest('.lsa-sbi');
                if (item && root.contains(item)) {
                    e.preventDefault();
                    var id = (item.getAttribute('href') || '').slice(1);
                    if (activate(root, id)) {
                        if (viewer && viewer.isOpen()) {
                            viewer.close('none');
                            window.history.pushState({lsa: 1, open: null, tab: id}, '', stateUrl(null, id));
                        } else {
                            // Keep the URL shareable, matching what Boost's
                            // loader does for core's tabs.
                            window.history.replaceState({lsa: 1, open: null, tab: id}, '', stateUrl(null, id));
                        }
                    }
                    return;
                }

                var header = e.target.closest('.lsa-section__hd');
                if (header && root.contains(header)) {
                    e.preventDefault();
                    toggle(header.closest('.lsa-section'));
                    return;
                }

                if (!viewer) {
                    return;
                }

                var action = e.target.closest('[data-action]');
                if (action && root.contains(action)) {
                    var what = action.getAttribute('data-action');
                    if (what === 'lsa-close') {
                        e.preventDefault();
                        // Not history.back(): once the panel has navigated on
                        // its own (form save, in-page link) the iframe owns the
                        // newest history entries, so back would only step the
                        // panel to its previous page. Close explicitly and
                        // record the overview as a new entry instead.
                        viewer.close('none');
                        var tabid = activePaneId(root);
                        window.history.pushState({lsa: 1, open: null, tab: tabid}, '', stateUrl(null, tabid));
                    } else if (what === 'lsa-reload' && viewer.current) {
                        e.preventDefault();
                        viewer.setLoading(true);
                        viewer.frame.contentWindow.location.replace(withEmbed(viewer.current));
                    }
                    return;
                }

                // Cards and bookmark chips: open beside the sidebar. Modified
                // clicks (new tab/window) keep their native behaviour.
                var link = e.target.closest('.lsa-item, .lsa-bookmark');
                if (link && root.contains(link)) {
                    if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    var url = internalUrl(link.href);
                    if (!url || isOutline(url)) {
                        return;
                    }
                    e.preventDefault();
                    viewer.open(url, link.textContent.trim(), 'push');
                }
            });

            if (viewer) {
                window.addEventListener('popstate', function(e) {
                    var st = e.state;
                    var params = new URLSearchParams(window.location.search);
                    var open = (st && st.lsa) ? st.open : params.get('open');
                    var tab = (st && st.lsa) ? st.tab : window.location.hash.slice(1);
                    if (tab) {
                        activate(root, tab);
                    }
                    var url = open ? internalUrl(M.cfg.wwwroot + open) : null;
                    if (url && !isOutline(url)) {
                        if (!viewer.current || viewer.current.href !== url.href) {
                            viewer.open(url, '', 'none');
                        }
                    } else {
                        viewer.close('none');
                    }
                });
            }

            // A deep link is delivered by Boost's loader clicking core's hidden
            // secondary-nav trigger, so the pane changes without us; adopt it.
            document.addEventListener('shown.bs.tab', function() {
                syncFromPanes(root);
            });

            // Belt and braces: honour #link<key> ourselves too, in case the
            // loader's lookup hits our trigger instead of core's.
            var hash = window.location.hash;
            if (!(hash && activate(root, hash.slice(1)))) {
                syncFromPanes(root);
            }

            // ?open=<page>: reopen the page the viewer was showing.
            var open = new URLSearchParams(window.location.search).get('open');
            if (viewer && open) {
                var target = internalUrl(M.cfg.wwwroot + (open.charAt(0) === '/' ? '' : '/') + open);
                if (target && !isOutline(target)) {
                    viewer.open(target, '', 'replace');
                }
            }
        }
    };
});
