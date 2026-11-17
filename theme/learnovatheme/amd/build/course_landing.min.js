// AMD module: theme_learnovatheme/course_landing
// Interactivity-only helpers for the Course Landing page (course/view.php):
//   - switch between the content tabs (Course Content / About / Discussion / Grades)
//   - collapse / expand a module's activity list
//   - sort the instructor Grades roster by a column (client-side reorder only)
//   - the custom Discussion tab: post/reply/delete over AJAX
// Static content is rendered server-side by course_landing.mustache. New
// discussion posts are rendered from the theme_learnovatheme/discussion_post
// Mustache template (never hand-built HTML) and inserted into the thread
// (top-level posts at the top, replies under their parent).
define([
    'core/ajax',
    'core/templates',
    'core/str',
    'core/notification',
    'core/fragment'
], function(Ajax, Templates, Str, Notification, Fragment) {
    'use strict';

    // Reorder the rows of the instructor grades roster by a column. The sort
    // keys live on each row as data-sort-* attributes (rendered server-side);
    // this only reorders existing DOM nodes — it never builds markup.
    function sortRoster(th) {
        var table = th.closest('[data-region="cl-grades-roster"]');
        if (!table) {
            return;
        }
        var tbody = table.querySelector('tbody');
        var key = th.getAttribute('data-cl-sort');
        var numeric = th.getAttribute('data-cl-sort-type') === 'num';
        // Toggle direction when re-clicking the active column, else default asc.
        var asc = !th.classList.contains('is-sorted-asc');

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
            var av = a.getAttribute('data-sort-' + key) || '';
            var bv = b.getAttribute('data-sort-' + key) || '';
            var cmp = numeric
                ? (parseFloat(av) - parseFloat(bv))
                : av.localeCompare(bv);
            return asc ? cmp : -cmp;
        });
        rows.forEach(function(r) {
            tbody.appendChild(r);
        });

        table.querySelectorAll('[data-cl-sort]').forEach(function(h) {
            h.classList.remove('is-sorted-asc', 'is-sorted-desc');
            h.removeAttribute('aria-sort');
        });
        th.classList.add(asc ? 'is-sorted-asc' : 'is-sorted-desc');
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
    }

    // ── Discussion tab ──────────────────────────────────────────────────────

    // Wire the custom Discussion tab: submit a post/reply and delete a post,
    // all without a full page reload. No-op when the tab is disabled.
    function initDiscussion(root) {
        var region = root.querySelector('[data-region="cl-discussion"]');
        if (!region) {
            return;
        }

        var courseid = parseInt(region.getAttribute('data-courseid'), 10);
        var thread = region.querySelector('[data-region="cl-thread"]');
        var form = region.querySelector('[data-region="cl-composer"]');

        // Reset the composer's reply context back to a top-level post.
        function clearReply() {
            if (!form) {
                return;
            }
            form.removeAttribute('data-parentid');
            var ctx = form.querySelector('[data-region="cl-reply-ctx"]');
            if (ctx) {
                ctx.hidden = true;
            }
        }

        // Show a transient error under the composer.
        function showError(message) {
            if (!form) {
                return;
            }
            var box = form.querySelector('[data-region="cl-composer-err"]');
            if (box) {
                box.textContent = message;
                box.hidden = false;
            }
        }

        function clearError() {
            if (!form) {
                return;
            }
            var box = form.querySelector('[data-region="cl-composer-err"]');
            if (box) {
                box.hidden = true;
                box.textContent = '';
            }
        }

        // Render a freshly-created post and insert it at the right spot, dropping
        // the empty state. Top-level posts go to the top of the thread (newest
        // first, matching the server-side ordering); replies go directly after
        // their parent's existing reply group so threads stay together.
        function renderPost(post) {
            var empty = thread.querySelector('[data-region="cl-thread-empty"]');
            if (empty) {
                empty.remove();
            }

            // A placeholder marks the insertion point; replaceNode swaps in the
            // rendered post (and runs its JS) exactly where we put the marker.
            var marker = document.createElement('div');
            if (post.isreply) {
                var parent = thread.querySelector(
                    '[data-region="cl-post"][data-postid="' + post.parentid + '"]');
                if (parent) {
                    var ref = parent.nextElementSibling;
                    while (ref && ref.classList.contains('learnova-cl__post--reply')) {
                        ref = ref.nextElementSibling;
                    }
                    thread.insertBefore(marker, ref); // ref null → appended at end.
                } else {
                    thread.appendChild(marker);
                }
            } else {
                thread.insertBefore(marker, thread.firstElementChild); // Prepend.
            }

            return Templates.renderForPromise('theme_learnovatheme/discussion_post', post)
                .then(function(result) {
                    Templates.replaceNode(marker, result.html, result.js);
                    // Bring the new top-level post into view at the top.
                    if (!post.isreply) {
                        thread.scrollTop = 0;
                    }
                    return post;
                });
        }

        // Submit (post or reply).
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                clearError();

                var input = form.querySelector('[data-region="cl-composer-input"]');
                var message = input ? input.value.trim() : '';
                if (message === '') {
                    return;
                }
                var parentid = parseInt(form.getAttribute('data-parentid'), 10) || 0;

                var btn = form.querySelector('[data-action="cl-post"]');
                if (btn) {
                    btn.disabled = true;
                }

                Ajax.call([{
                    methodname: 'local_learnova_course_post_message',
                    args: {courseid: courseid, message: message, parentid: parentid}
                }])[0]
                    .then(renderPost)
                    .then(function() {
                        input.value = '';
                        clearReply();
                        return;
                    })
                    .catch(function(error) {
                        showError(error.message || error.error || '');
                    })
                    .then(function() {
                        if (btn) {
                            btn.disabled = false;
                        }
                        return;
                    });
            });
        }

        // Reply / cancel-reply / delete (delegated clicks within the region).
        region.addEventListener('click', function(e) {
            var replyBtn = e.target.closest('[data-action="cl-reply"]');
            if (replyBtn && form) {
                form.setAttribute('data-parentid', replyBtn.getAttribute('data-postid'));
                var ctx = form.querySelector('[data-region="cl-reply-ctx"]');
                var name = form.querySelector('[data-region="cl-reply-name"]');
                if (name) {
                    name.textContent = replyBtn.getAttribute('data-name') || '';
                }
                if (ctx) {
                    ctx.hidden = false;
                }
                var input = form.querySelector('[data-region="cl-composer-input"]');
                if (input) {
                    input.focus();
                }
                return;
            }

            if (e.target.closest('[data-action="cl-reply-cancel"]')) {
                clearReply();
                return;
            }

            var delBtn = e.target.closest('[data-action="cl-delete"]');
            if (delBtn) {
                var postid = parseInt(delBtn.getAttribute('data-postid'), 10);
                Str.get_strings([
                    {key: 'cl_discussion_deleteconfirm', component: 'theme_learnovatheme'},
                    {key: 'delete', component: 'core'},
                    {key: 'cancel', component: 'core'}
                ]).then(function(strings) {
                    return Notification.saveCancelPromise(strings[1], strings[0], strings[1]);
                }).then(function() {
                    return Ajax.call([{
                        methodname: 'local_learnova_course_delete_message',
                        args: {postid: postid}
                    }])[0];
                }).then(function() {
                    var node = region.querySelector('[data-region="cl-post"][data-postid="' + postid + '"]');
                    if (node) {
                        node.remove();
                    }
                    return;
                }).catch(function(error) {
                    // The user dismissing the confirm modal rejects with no real
                    // error; only surface genuine failures.
                    if (error && (error.message || error.error)) {
                        Notification.exception(error);
                    }
                });
            }
        });
    }

    // ── Edit mode  ──────────────────────────────────────────

    // Wire every edit-mode control on the landing. All the markup this touches
    // is only rendered server-side for a teacher/admin in Moodle edit mode, so
    // this is a no-op (bails on the missing bulk bar) for everyone else.
    // Stateful actions go through Moodle's own reactive web services
    // (core_courseformat_update_course / _new_module); the page then reloads so
    // the server re-renders the landing from fresh modinfo — no DOM patching.
    function initEdit(root) {
        var bulkbar = root.querySelector('[data-region="cl-bulkbar"]');
        if (!bulkbar) {
            return; // Not in edit mode.
        }
        var courseid = parseInt(bulkbar.getAttribute('data-courseid'), 10);
        // Course context id — for the fragment loader that re-renders the content
        // tab in place after +Add section / +Subsection (no full page reload).
        var contextid = parseInt(bulkbar.getAttribute('data-contextid'), 10);
        // Component for the section inline-rename WS, e.g. 'format_topics'.
        var courseformat = bulkbar.getAttribute('data-courseformat') || 'format_topics';

        var overlay   = root.querySelector('[data-cl-overlay]');
        var editPanel = root.querySelector('[data-region="cl-edit-panel"]');
        var editFrame = root.querySelector('[data-region="cl-edit-frame"]');
        var editTitle = root.querySelector('[data-region="cl-edit-title"]');
        var editBadge = root.querySelector('[data-region="cl-edit-badge"]');
        var addPanel  = root.querySelector('[data-region="cl-add-panel"]');
        var addSearch = root.querySelector('[data-region="cl-add-search"]');
        var moveModal = root.querySelector('[data-region="cl-move-modal"]');
        var countEl   = root.querySelector('[data-region="cl-bulk-count"]');

        // Add-panel regions.
        var addEmpty  = root.querySelector('[data-region="cl-add-empty"]');
        var addInfo   = root.querySelector('[data-region="cl-add-info"]');
        var addInfoIco  = root.querySelector('[data-region="cl-add-info-ico"]');
        var addInfoName = root.querySelector('[data-region="cl-add-info-name"]');
        var addInfoDesc = root.querySelector('[data-region="cl-add-info-desc"]');
        var addHint   = root.querySelector('[data-region="cl-add-hint"]');
        var addStar   = root.querySelector('[data-region="cl-add-star"]');
        var addStarLbl = root.querySelector('[data-region="cl-add-star-lbl"]');
        var addConfirm = root.querySelector('[data-region="cl-add-confirm"]');

        var pendingMove = null; // {type: 'cm'|'section', ids: [..]}

        // ── Add-panel state ──
        var addSectionNum = 0;      // Target course section for the new activity.
        var addFilter   = 'all';    // Active category pill.
        var addSelected = null;     // Currently selected tile element, or null.
        var addHovered  = null;     // Currently hovered tile element, or null.
        // Favourites are the native activity-chooser "starred" items: each tile
        // carries its current state as the `is-fav` class (server-rendered) and
        // toggling goes through Moodle's own web services, so it stays in sync
        // with the core chooser. No client-side store.

        function closeMenus() {
            root.querySelectorAll('.learnova-cl__menu-wrap.is-open').forEach(function(w) {
                w.classList.remove('is-open');
            });
        }
        function closePanels() {
            if (overlay) {
                overlay.classList.remove('is-open');
            }
            if (editPanel) {
                editPanel.classList.remove('is-open');
            }
            if (addPanel) {
                addPanel.classList.remove('is-open');
            }
            // Drop the iframe so the heavy Moodle form isn't kept alive/scrolled.
            if (editFrame) {
                editFrame.src = 'about:blank';
            }
            if (editPanel) {
                editPanel.classList.remove('is-busy');
            }
        }
        function openEditPanel(url, title, badge) {
            closeMenus();
            // Opening the edit panel supersedes the add panel (used by the
            // "Add to course" flow, which hands off to the same modedit form).
            if (addPanel) {
                addPanel.classList.remove('is-open');
            }
            if (editTitle) {
                editTitle.textContent = title || '';
            }
            if (editBadge) {
                editBadge.textContent = badge || title || '';
            }
            if (editFrame) {
                // Veil the frame (spinner) until the form document arrives; the
                // load handler lifts it. Also hides any previous document.
                if (editPanel) {
                    editPanel.classList.add('is-busy');
                }
                // Cache-buster: the iframe reuses the same edit URL each open, so
                // the browser can serve a stale copy (with an old theme-CSS rev
                // link). A unique param forces a fresh document + current CSS.
                editFrame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'clcb=' + Date.now();
            }
            if (overlay) {
                overlay.classList.add('is-open');
            }
            if (editPanel) {
                editPanel.classList.add('is-open');
            }
        }

        // The embedded edit form (clembed=1) submits/cancels to the course page
        // inside the iframe. Detect that navigation and, instead of showing the
        // full course page in the panel, close the panel and reload the parent so
        // the saved change is reflected on the landing. The iframe is veiled the
        // moment the form submits (is-busy → frame hidden + spinner), so the
        // redirect's course page never flashes inside the panel.
        if (editFrame) {
            editFrame.addEventListener('load', function() {
                var href;
                var doc;
                try {
                    href = editFrame.contentWindow.location.href;
                    doc = editFrame.contentDocument;
                } catch (e) {
                    // Cross-origin (shouldn't happen) — ignore.
                    return;
                }
                if (!href || href === 'about:blank') {
                    return;
                }
                // Still on an edit-form page (initial load, or a validation-error
                // re-submit that dropped the clembed query param) → show it and
                // veil again on the next submit (save/cancel both submit).
                if (href.indexOf('/course/modedit.php') !== -1
                        || href.indexOf('/course/editsection.php') !== -1) {
                    if (editPanel) {
                        editPanel.classList.remove('is-busy');
                    }
                    if (doc) {
                        doc.addEventListener('submit', function() {
                            if (editPanel) {
                                editPanel.classList.add('is-busy');
                            }
                        }, true);
                    }
                    return;
                }
                // Navigated away (saved or cancelled back to the course) → the
                // change is committed; refresh the landing. Keep the veil up so
                // the course page never shows in the panel while reloading.
                window.location.reload();
            });
        }
        function openAddPanel(sectionnum) {
            closeMenus();
            addSectionNum = parseInt(sectionnum, 10) || 0;
            // Fresh state each open: clear selection, search and filter.
            clearAddSelection();
            if (addSearch) {
                addSearch.value = '';
            }
            setAddFilter('all');
            if (overlay) {
                overlay.classList.add('is-open');
            }
            if (addPanel) {
                addPanel.classList.add('is-open');
            }
        }

        // ── Add-panel: filter, selection, info pane, favourites ──

        // Show/hide tiles by active category + search query, recompute the
        // per-section counts, hide empty sections and toggle the empty state.
        function applyAddFilter() {
            if (!addPanel) {
                return;
            }
            var q = (addSearch ? addSearch.value : '').trim().toLowerCase();
            var total = 0;
            addPanel.querySelectorAll('[data-region="cl-add-section"]').forEach(function(section) {
                var vis = 0;
                section.querySelectorAll('[data-region="cl-add-tile"]').forEach(function(t) {
                    var cat = t.getAttribute('data-cl-cat');
                    var name = (t.getAttribute('data-cl-search') || '').toLowerCase();
                    var matchCat = addFilter === 'all'
                        || (addFilter === 'favorited' && t.classList.contains('is-fav'))
                        || cat === addFilter;
                    var matchSearch = q === '' || name.indexOf(q) !== -1;
                    var show = matchCat && matchSearch;
                    t.classList.toggle('is-hidden', !show);
                    if (show) {
                        vis++;
                    }
                });
                section.classList.toggle('is-hidden', vis === 0);
                var ct = section.querySelector('[data-region="cl-add-count"]');
                if (ct) {
                    ct.textContent = vis;
                }
                total += vis;
            });
            if (addEmpty) {
                addEmpty.classList.toggle('is-shown', total === 0);
            }
        }

        function setAddFilter(f) {
            addFilter = f;
            addPanel.querySelectorAll('[data-cl-filter]').forEach(function(p) {
                p.classList.toggle('is-active', p.getAttribute('data-cl-filter') === f);
            });
            applyAddFilter();
        }

        // Populate the footer info pane from a tile (icon, name, category pill, desc).
        function showAddInfo(tile) {
            if (!addInfo || !tile) {
                return;
            }
            var cat = tile.getAttribute('data-cl-cat');
            if (addInfoIco) {
                addInfoIco.className = 'learnova-cl__aa-info-ico learnova-cl__act-ico--' + tile.getAttribute('data-cl-slug');
                addInfoIco.innerHTML = '<img src="' + tile.getAttribute('data-cl-icon') + '" alt="">';
            }
            if (addInfoName) {
                addInfoName.textContent = tile.getAttribute('data-cl-search') + ' ';
                var pill = document.createElement('span');
                pill.className = 'learnova-cl__aa-info-cat learnova-cl__aa-catpill--' + cat;
                pill.textContent = tile.getAttribute('data-cl-catlabel') || '';
                addInfoName.appendChild(pill);
            }
            if (addInfoDesc) {
                addInfoDesc.textContent = tile.getAttribute('data-cl-desc') || '';
            }
            addInfo.classList.add('is-shown');
        }
        function hideAddInfo() {
            if (addInfo) {
                addInfo.classList.remove('is-shown');
            }
        }

        function clearAddSelection() {
            if (addSelected) {
                addSelected.classList.remove('is-selected');
            }
            addSelected = null;
            updateAddFooter();
            hideAddInfo();
        }

        function selectAddTile(tile) {
            if (addSelected === tile) {   // Toggle off.
                clearAddSelection();
                if (addHovered) {
                    showAddInfo(addHovered);
                }
                return;
            }
            if (addSelected) {
                addSelected.classList.remove('is-selected');
            }
            addSelected = tile;
            tile.classList.add('is-selected');
            updateAddFooter();
            showAddInfo(tile);
        }

        // Footer action row reflects the current selection.
        function updateAddFooter() {
            if (addConfirm) {
                addConfirm.disabled = !addSelected;
            }
            // Star is shown for a selected tile, unless it's a legacy item that
            // can't be favourited (no content-item id in the native chooser).
            if (addStar) {
                var canFav = addSelected && addSelected.getAttribute('data-cl-canfav') === '1';
                addStar.classList.toggle('is-shown', !!canFav);
            }
            if (!addSelected) {
                if (addHint) {
                    Str.get_string('cl_addpanel_hint', 'theme_learnovatheme').then(function(s) {
                        addHint.textContent = s;
                        return s;
                    }).catch(function() {
                        return null;
                    });
                }
                return;
            }
            if (addHint) {
                Str.get_string('cl_addpanel_selected', 'theme_learnovatheme',
                    addSelected.getAttribute('data-cl-search')).then(function(s) {
                    addHint.innerHTML = s;
                    return s;
                }).catch(function() {
                    return null;
                });
            }
            updateStarBtn(addSelected.classList.contains('is-fav'));
        }

        function updateStarBtn(isFav) {
            if (!addStar) {
                return;
            }
            addStar.classList.toggle('is-starred', isFav);
            if (addStarLbl) {
                Str.get_string(isFav ? 'cl_addpanel_favorited' : 'cl_addpanel_favorite',
                    'theme_learnovatheme').then(function(s) {
                    addStarLbl.textContent = s;
                    return s;
                }).catch(function() {
                    return null;
                });
            }
        }

        // Toggle the native activity-chooser favourite for the selected tile via
        // Moodle's own web service, then reflect the confirmed state locally.
        function toggleAddStar() {
            if (!addSelected || addSelected.getAttribute('data-cl-canfav') !== '1') {
                return;
            }
            var tile = addSelected;
            var willFav = !tile.classList.contains('is-fav');
            var method = willFav
                ? 'core_course_add_content_item_to_user_favourites'
                : 'core_course_remove_content_item_from_user_favourites';
            // Optimistic UI; revert on failure.
            tile.classList.toggle('is-fav', willFav);
            if (tile === addSelected) {
                updateStarBtn(willFav);
            }
            Ajax.call([{
                methodname: method,
                args: {
                    componentname: tile.getAttribute('data-cl-component'),
                    contentitemid: parseInt(tile.getAttribute('data-cl-itemid'), 10)
                }
            }])[0].then(function(item) {
                // Trust the server's returned state.
                var fav = !!(item && item.favourite);
                tile.classList.toggle('is-fav', fav);
                if (tile === addSelected) {
                    updateStarBtn(fav);
                }
                if (addFilter === 'favorited') {
                    applyAddFilter();
                }
                return item;
            }).catch(function(err) {
                tile.classList.toggle('is-fav', !willFav); // Revert.
                if (tile === addSelected) {
                    updateStarBtn(!willFav);
                }
                Notification.exception(err);
            });
        }

        // "Add to course" → open the real add form for the chosen section in the
        // edit panel (same modedit.php form as Edit settings), not a full-page
        // navigation. clembed=1 makes the drawers layout render it chrome-less.
        function confirmAddTile() {
            if (!addSelected) {
                return;
            }
            var url = addSelected.getAttribute('data-cl-addurl')
                + '&section=' + addSectionNum + '&clembed=1';
            var label = addSelected.getAttribute('data-cl-search') || '';
            Str.get_strings([
                {key: 'cl_add_panel_title', component: 'theme_learnovatheme', param: label},
                {key: 'cl_add_panel_badge', component: 'theme_learnovatheme'}
            ]).then(function(s) {
                openEditPanel(url, s[0], s[1]);
                return s;
            }).catch(function() {
                // Fall back to the raw activity name if the strings can't load.
                openEditPanel(url, label, label);
                return null;
            });
        }

        // Fire a reactive course-editor action, then reload on success.
        function dispatch(action, ids, targetsectionid, targetcmid) {
            var args = {action: action, courseid: courseid, ids: ids};
            if (targetsectionid) {
                args.targetsectionid = targetsectionid;
            }
            if (targetcmid) {
                args.targetcmid = targetcmid;
            }
            return Ajax.call([{methodname: 'core_courseformat_update_course', args: args}])[0];
        }
        function reload() {
            window.location.reload();
        }
        function afterAction(promise) {
            promise.then(reload).catch(Notification.exception);
        }

        // Re-render just the Course Content tab in place (no full page reload).
        // Used after actions that only change the section list — +Add section and
        // +Subsection — so the rest of the page (hero, sidebar, scroll) is kept.
        // Falls back to a full reload if the fragment context isn't available.
        function refreshContent() {
            var panel = root.querySelector('[data-cl-panel="content"]');
            if (!panel || !contextid) {
                reload();
                return;
            }
            // Preserve which sections the user had collapsed, keyed by section id.
            var collapsed = {};
            panel.querySelectorAll('.learnova-cl__module.is-collapsed').forEach(function(m) {
                var acts = m.querySelector('.learnova-cl__acts[data-cl-sectionid]');
                if (acts) {
                    collapsed[acts.getAttribute('data-cl-sectionid')] = true;
                }
            });
            Fragment.loadFragment('theme_learnovatheme', 'course_content', contextid, {})
                .then(function(html, js) {
                    Templates.replaceNodeContents(panel, html, js);
                    panel.querySelectorAll('.learnova-cl__module').forEach(function(m) {
                        var acts = m.querySelector('.learnova-cl__acts[data-cl-sectionid]');
                        if (acts && collapsed[acts.getAttribute('data-cl-sectionid')]) {
                            m.classList.add('is-collapsed');
                        }
                    });
                    return null;
                })
                .catch(Notification.exception);
        }

        // ── Bulk selection helpers ──
        function checks() {
            return root.querySelectorAll('[data-cl-bulk-item]');
        }
        function selectedBy(type) {
            var out = [];
            root.querySelectorAll('[data-cl-bulk-item][data-cl-type="' + type + '"]:checked')
                .forEach(function(c) {
                    out.push(parseInt(c.getAttribute('data-cl-id'), 10));
                });
            return out;
        }
        function updateCount() {
            var n = root.querySelectorAll('[data-cl-bulk-item]:checked').length;
            if (countEl) {
                Str.get_string('cl_bulk_selected', 'theme_learnovatheme', n).then(function(s) {
                    countEl.textContent = s;
                    return s;
                }).catch(function() {
                    return null;
                });
            }
        }
        function clearBulk() {
            checks().forEach(function(c) {
                c.checked = false;
            });
            var all = root.querySelector('[data-cl-bulk-all]');
            if (all) {
                all.checked = false;
            }
            updateCount();
        }
        function runBulk(kind) {
            var cms  = selectedBy('cm');
            var secs = selectedBy('section');
            if (!cms.length && !secs.length) {
                return;
            }
            if (kind === 'move') {
                // Move one type at a time; prefer activities when both are picked.
                pendingMove = cms.length ? {type: 'cm', ids: cms} : {type: 'section', ids: secs};
                if (moveModal) {
                    moveModal.classList.add('is-open');
                }
                return;
            }
            var verbmap = {hide: 'hide', duplicate: 'duplicate', delete: 'delete'};
            var verb = verbmap[kind];
            if (!verb) {
                return;
            }
            var run = function() {
                var calls = [];
                if (cms.length) {
                    calls.push(dispatch('cm_' + verb, cms));
                }
                if (secs.length) {
                    calls.push(dispatch('section_' + verb, secs));
                }
                Promise.all(calls).then(reload).catch(Notification.exception);
            };
            if (kind === 'delete') {
                Str.get_strings([
                    {key: 'delete', component: 'core'},
                    {key: 'cl_bulk_selected', component: 'theme_learnovatheme', param: (cms.length + secs.length)}
                ]).then(function(s) {
                    return Notification.saveCancelPromise(s[0], s[1], s[0]);
                }).then(run).catch(function() {
                    return null;
                });
            } else {
                run();
            }
        }

        // ── Inline rename (pencil) — Moodle-native quick edit of the name ──

        // Find the visible name element a pencil renames.
        function renameTarget(pencil, type) {
            if (type === 'cm') {
                var act = pencil.closest('.learnova-cl__act');
                return act ? act.querySelector('.learnova-cl__act-name') : null;
            }
            var sub = pencil.closest('.learnova-cl__subsec-hd');
            if (sub) {
                return sub.querySelector('.learnova-cl__subsec-title');
            }
            var hd = pencil.closest('.learnova-cl__mod-hd');
            return hd ? hd.querySelector('.learnova-cl__mod-title') : null;
        }
        // Write a new name back, preserving the "Continue" badge on activities.
        function writeName(nameEl, value) {
            var tag = nameEl.querySelector('.learnova-cl__act-tag');
            nameEl.textContent = value;
            if (tag) {
                nameEl.appendChild(document.createTextNode(' '));
                nameEl.appendChild(tag);
            }
        }
        // Turn the name into an <input>; save via core_update_inplace_editable.
        function beginRename(pencil) {
            closeMenus();
            var type = pencil.getAttribute('data-cl-rename-type'); // 'section' | 'cm'
            var id   = parseInt(pencil.getAttribute('data-cl-rename-id'), 10);
            var nameEl = renameTarget(pencil, type);
            if (!nameEl || nameEl.dataset.renaming === '1') {
                return;
            }
            var current = (pencil.getAttribute('data-cl-name') || nameEl.textContent).trim();

            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'learnova-cl__rename-input';
            input.value = current;
            input.setAttribute('data-cl-edstop', ''); // Don't collapse the section.
            input.setAttribute('aria-label', current);

            nameEl.dataset.renaming = '1';
            nameEl.style.display = 'none';
            nameEl.parentNode.insertBefore(input, nameEl);
            input.focus();
            input.select();

            var settled = false;
            var finish = function(save) {
                if (settled) {
                    return;
                }
                settled = true;
                var val = input.value.trim();
                input.remove();
                nameEl.style.display = '';
                delete nameEl.dataset.renaming;
                if (!save || val === '' || val === current) {
                    return;
                }
                var component = (type === 'section') ? courseformat : 'core_course';
                var itemtype  = (type === 'section') ? 'sectionname' : 'activityname';
                Ajax.call([{
                    methodname: 'core_update_inplace_editable',
                    args: {component: component, itemtype: itemtype, itemid: id, value: val}
                }])[0].then(function(res) {
                    var nv = (res && typeof res.value === 'string') ? res.value : val;
                    writeName(nameEl, nv);
                    pencil.setAttribute('data-cl-name', nv);
                    return nv;
                }).catch(Notification.exception);
            };
            input.addEventListener('keydown', function(ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    finish(true);
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    finish(false);
                }
            });
            input.addEventListener('blur', function() {
                finish(true);
            });
        }

        // ── Drag & drop activity reordering (like Moodle native) ──
        // Drag from the grip handle; drop persists via the reactive cm_move
        // action (targetsectionid + optional targetcmid = insert before it),
        // then the page reloads so the server re-renders the new order.
        var dragCmid = null;

        function clearDrop() {
            root.querySelectorAll('.cl-drop-before, .cl-drop-after').forEach(function(n) {
                n.classList.remove('cl-drop-before', 'cl-drop-after');
            });
        }

        root.addEventListener('dragstart', function(e) {
            var handle = e.target.closest('[data-cl-drag]');
            if (!handle) {
                return;
            }
            var row = handle.closest('.learnova-cl__act');
            if (!row) {
                return;
            }
            dragCmid = parseInt(row.getAttribute('data-cl-cmid'), 10);
            row.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(dragCmid));
                try {
                    e.dataTransfer.setDragImage(row, 12, 12);
                } catch (ignore) {
                    // Older browsers — fall back to the default drag image.
                }
            }
        });

        root.addEventListener('dragend', function() {
            root.querySelectorAll('.learnova-cl__act.is-dragging').forEach(function(n) {
                n.classList.remove('is-dragging');
            });
            clearDrop();
            dragCmid = null;
        });

        root.addEventListener('dragover', function(e) {
            if (dragCmid === null || !e.target.closest('[data-cl-sectionid]')) {
                return;
            }
            e.preventDefault(); // Allow drop.
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'move';
            }
            clearDrop();
            var overRow = e.target.closest('.learnova-cl__act');
            if (overRow && overRow.getAttribute('data-cl-cmid') !== String(dragCmid)) {
                var rect = overRow.getBoundingClientRect();
                overRow.classList.add(e.clientY < rect.top + rect.height / 2 ? 'cl-drop-before' : 'cl-drop-after');
            }
        });

        root.addEventListener('drop', function(e) {
            if (dragCmid === null) {
                return;
            }
            var container = e.target.closest('[data-cl-sectionid]');
            if (!container) {
                return;
            }
            e.preventDefault();
            var targetsectionid = parseInt(container.getAttribute('data-cl-sectionid'), 10);
            var overRow = e.target.closest('.learnova-cl__act');
            var targetcmid = null;
            if (overRow) {
                var rect = overRow.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    targetcmid = parseInt(overRow.getAttribute('data-cl-cmid'), 10);
                } else {
                    var next = overRow.nextElementSibling;
                    while (next && !next.classList.contains('learnova-cl__act')) {
                        next = next.nextElementSibling;
                    }
                    targetcmid = next ? parseInt(next.getAttribute('data-cl-cmid'), 10) : null;
                }
            }
            var moved = dragCmid;
            clearDrop();
            // Dropped exactly onto itself → nothing to do.
            if (targetcmid === moved) {
                return;
            }
            afterAction(dispatch('cm_move', [moved], targetsectionid, targetcmid));
        });

        // ── Delegated clicks ──
        root.addEventListener('click', function(e) {
            // Pencil → inline rename (like Moodle's native quick edit).
            var renameBtn = e.target.closest('[data-cl-action="rename"]');
            if (renameBtn) {
                e.preventDefault();
                beginRename(renameBtn);
                return;
            }

            // Three-dot menu toggle.
            var menuToggle = e.target.closest('[data-cl-menu-toggle]');
            if (menuToggle) {
                e.preventDefault();
                var wrap = menuToggle.closest('.learnova-cl__menu-wrap');
                var wasOpen = wrap.classList.contains('is-open');
                closeMenus();
                if (!wasOpen) {
                    wrap.classList.add('is-open');
                }
                return;
            }

            // Pencil / "Edit settings" → open the real form in the sliding panel.
            // In edit mode the activity name is also an edit trigger; a click on
            // the empty part of its row counts too (but not on the drag handle,
            // checkbox, or the pencil/menu controls).
            var editBtn = e.target.closest('[data-cl-action="edit"]');
            if (!editBtn) {
                var actRow = e.target.closest('.learnova-cl__act[data-cl-cmid]');
                if (actRow && !e.target.closest('[data-cl-edstop], input, button, a, .learnova-cl__menu')) {
                    editBtn = actRow.querySelector('.learnova-cl__act-name[data-cl-action="edit"]');
                }
            }
            if (editBtn) {
                // Modified clicks on the name link keep their native behaviour
                // (open the activity in a new tab/window).
                if (editBtn.tagName === 'A' && (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey)) {
                    return;
                }
                e.preventDefault();
                openEditPanel(editBtn.getAttribute('data-cl-edit-url'), editBtn.getAttribute('data-cl-title'), editBtn.getAttribute('data-cl-badge'));
                return;
            }

            // Add activity or resource → open the chooser grid for this section.
            var addBtn = e.target.closest('[data-cl-addact]');
            if (addBtn) {
                e.preventDefault();
                openAddPanel(addBtn.getAttribute('data-cl-section'));
                return;
            }

            // Add-panel: category filter pill.
            var filterPill = e.target.closest('[data-cl-filter]');
            if (filterPill) {
                e.preventDefault();
                setAddFilter(filterPill.getAttribute('data-cl-filter'));
                return;
            }
            // Add-panel: select a tile.
            var addTile = e.target.closest('[data-region="cl-add-tile"]');
            if (addTile) {
                e.preventDefault();
                selectAddTile(addTile);
                return;
            }
            // Add-panel: toggle favourite for the selected tile.
            if (e.target.closest('[data-cl-addstar]')) {
                e.preventDefault();
                toggleAddStar();
                return;
            }
            // Add-panel: "Add to course".
            if (e.target.closest('[data-cl-addconfirm]')) {
                e.preventDefault();
                confirmAddTile();
                return;
            }

            // + Add section → append a new course section via the reactive
            // state action (empty ids → added at the end), then re-render the
            // content tab in place (no full page reload).
            if (e.target.closest('[data-cl-addsection]')) {
                e.preventDefault();
                dispatch('section_add', []).then(refreshContent).catch(Notification.exception);
                return;
            }

            // + Subsection → create one via the reactive web service, then
            // re-render the content tab in place (no full page reload).
            var subBtn = e.target.closest('[data-cl-addsub]');
            if (subBtn) {
                e.preventDefault();
                var sid = parseInt(subBtn.getAttribute('data-cl-sectionid'), 10);
                Ajax.call([{
                    methodname: 'core_courseformat_new_module',
                    args: {courseid: courseid, modname: 'subsection', targetsectionid: sid}
                }])[0].then(refreshContent).catch(Notification.exception);
                return;
            }

            // Close a panel (X or overlay).
            if (e.target.closest('[data-cl-panel-close]') || e.target.closest('[data-cl-overlay]')) {
                e.preventDefault();
                closePanels();
                return;
            }

            // Stateful per-item action (duplicate / hide / show / delete / move).
            var actionBtn = e.target.closest('[data-cl-action]');
            if (actionBtn) {
                var action = actionBtn.getAttribute('data-cl-action');
                if (['duplicate', 'hide', 'show', 'delete', 'move'].indexOf(action) === -1) {
                    return;
                }
                e.preventDefault();
                closeMenus();
                var type = actionBtn.getAttribute('data-cl-type');
                var id   = parseInt(actionBtn.getAttribute('data-cl-id'), 10);
                var name = actionBtn.getAttribute('data-cl-name') || '';
                var prefix = (type === 'section') ? 'section_' : 'cm_';

                if (action === 'move') {
                    pendingMove = {type: type, ids: [id]};
                    if (moveModal) {
                        moveModal.classList.add('is-open');
                    }
                    return;
                }
                if (action === 'delete') {
                    Str.get_strings([
                        {key: 'cl_edit_deleteconfirm', component: 'theme_learnovatheme', param: name},
                        {key: 'delete', component: 'core'}
                    ]).then(function(s) {
                        return Notification.saveCancelPromise(s[1], s[0], s[1]);
                    }).then(function() {
                        afterAction(dispatch(prefix + 'delete', [id]));
                        return null;
                    }).catch(function() {
                        return null;
                    });
                    return;
                }
                afterAction(dispatch(prefix + action, [id]));
                return;
            }

            // Move-target chosen.
            var moveTarget = e.target.closest('[data-cl-move-target]');
            if (moveTarget && pendingMove) {
                e.preventDefault();
                var tsid = parseInt(moveTarget.getAttribute('data-cl-move-target'), 10);
                if (moveModal) {
                    moveModal.classList.remove('is-open');
                }
                var mv = (pendingMove.type === 'section') ? 'section_move_after' : 'cm_move';
                afterAction(dispatch(mv, pendingMove.ids, tsid));
                pendingMove = null;
                return;
            }
            if (e.target.closest('[data-cl-move-close]')) {
                if (moveModal) {
                    moveModal.classList.remove('is-open');
                }
                pendingMove = null;
                return;
            }

            // Bulk mode toggle (notice-bar button + bulk-bar close).
            if (e.target.closest('[data-cl-bulk-toggle]')) {
                e.preventDefault();
                root.classList.toggle('is-bulk');
                if (!root.classList.contains('is-bulk')) {
                    clearBulk();
                }
                return;
            }
            // Bulk action.
            var bulkAct = e.target.closest('[data-cl-bulk-action]');
            if (bulkAct) {
                e.preventDefault();
                runBulk(bulkAct.getAttribute('data-cl-bulk-action'));
                return;
            }
        });

        // Bulk checkbox changes + select-all.
        root.addEventListener('change', function(e) {
            if (e.target.matches('[data-cl-bulk-all]')) {
                var on = e.target.checked;
                checks().forEach(function(c) {
                    c.checked = on;
                });
                updateCount();
                return;
            }
            if (e.target.matches('[data-cl-bulk-item]')) {
                updateCount();
            }
        });

        // Live-filter the Add Activity grid on search.
        if (addSearch) {
            addSearch.addEventListener('input', applyAddFilter);
        }

        // Add-panel hover → preview the tile in the footer info pane (only while
        // nothing is selected; a selection pins the pane to the selected tile).
        if (addPanel) {
            addPanel.addEventListener('mouseover', function(e) {
                var tile = e.target.closest('[data-region="cl-add-tile"]');
                if (!tile || tile === addHovered) {
                    return;
                }
                addHovered = tile;
                if (!addSelected) {
                    showAddInfo(tile);
                }
            });
            addPanel.addEventListener('mouseout', function(e) {
                var tile = e.target.closest('[data-region="cl-add-tile"]');
                if (!tile) {
                    return;
                }
                // Ignore moves within the same tile.
                if (e.relatedTarget && tile.contains(e.relatedTarget)) {
                    return;
                }
                addHovered = null;
                if (!addSelected) {
                    hideAddInfo();
                }
            });
        }

        // Close menus on an outside click.
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.learnova-cl__menu-wrap')) {
                closeMenus();
            }
        });
    }

    return {
        init: function() {
            var root = document.querySelector('.learnova-cl');
            if (!root) {
                return;
            }

            // Tab switching.
            root.addEventListener('click', function(e) {
                var tab = e.target.closest('[data-cl-tab]');
                if (tab) {
                    var key = tab.getAttribute('data-cl-tab');
                    root.querySelectorAll('[data-cl-tab]').forEach(function(t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    root.querySelectorAll('[data-cl-panel]').forEach(function(p) {
                        p.classList.toggle('is-active', p.getAttribute('data-cl-panel') === key);
                    });
                    return;
                }

                // Grades roster column sort.
                var sortTh = e.target.closest('[data-cl-sort]');
                if (sortTh) {
                    sortRoster(sortTh);
                    return;
                }

                // Module collapse / expand — but never when interacting with the
                // edit controls or bulk checkbox inside the section header.
                var modHd = e.target.closest('[data-cl-mod-toggle]');
                if (modHd) {
                    if (e.target.closest('[data-cl-edstop], .learnova-cl__edctrls, .learnova-cl__menu, .learnova-cl__bchk')) {
                        return;
                    }
                    var module = modHd.closest('.learnova-cl__module');
                    if (module) {
                        module.classList.toggle('is-collapsed');
                    }
                }
            });

            initDiscussion(root);
            initEdit(root);
        }
    };
});
