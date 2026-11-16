// AMD module: theme_learnovatheme/site_admin
// Behaviour for the Site administration outline rendered by the theme override
// of core/settings_link_page:
//
//   1. Collapse/expand of the category cards. The Plugins tab alone has 28
//      cards, so being able to fold one away matters.
//   2. Keeping the sidebar's active item in step with the visible pane.
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

    return {
        init: function(config) {
            var root = document.querySelector('.learnova-siteadmin');
            if (!root) {
                return;
            }

            renderBookmarks(root, (config && config.bookmarks) || []);

            // Delegated so both handlers keep working if a pane is re-rendered.
            root.addEventListener('click', function(e) {
                var item = e.target.closest('.lsa-sbi');
                if (item && root.contains(item)) {
                    e.preventDefault();
                    if (activate(root, (item.getAttribute('href') || '').slice(1))
                            && window.history.replaceState) {
                        // Keep the URL shareable, matching what Boost's loader
                        // does for core's tabs.
                        window.history.replaceState(null, '', item.getAttribute('href'));
                    }
                    return;
                }

                var header = e.target.closest('.lsa-section__hd');
                if (header && root.contains(header)) {
                    e.preventDefault();
                    toggle(header.closest('.lsa-section'));
                }
            });

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
        }
    };
});
