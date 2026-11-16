// AMD module: theme_learnovatheme/focus_mode
// Interactivity-only helpers for the distraction-free activity view:
//   - show/hide the Course Content sidebar (desktop column + mobile drawer)
//   - collapse/expand a module group in the table of contents
//   - dark mode and browser fullscreen from the topbar
//   - the Activity Discussion slide-over (open/close, post, reply, delete)
// Structural HTML lives in templates/theme_learnovatheme/focusmode.mustache;
// this module never injects structural markup. It only flips state classes on
// <body> — which state maps to which visual result is decided entirely in SCSS,
// so the two Show/Hide anchors stay in sync without being touched here.
// Manual completion is handled by core_course/manual_completion_toggle, loaded
// by the completion button itself.
define(['core_user/repository', 'core/ajax', 'core/notification', 'core/str', 'core/templates'],
        function(UserRepository, Ajax, Notification, Str, Templates) {
    'use strict';

    // Kept in step with the $fm-drawer-max breakpoint in post.scss.
    var DRAWER_MAX = 1023;

    var COLLAPSED = 'fm-sidebar-collapsed';
    var OPEN = 'fm-sidebar-open';
    var DARK = 'fm-dark';

    var PREF_SIDEBAR = 'theme_learnovatheme_fm_sidebar';
    var PREF_DARK = 'theme_learnovatheme_fm_dark';

    // Which side of the drawer breakpoint the last resize left us on, so a
    // crossing can be told apart from a resize within one breakpoint.
    var wasDrawer = false;

    /**
     * Persist a chrome preference so the layout can stamp it before first paint
     * on the next activity. Failures are non-fatal: the current page still works,
     * only the state is not carried over.
     *
     * @param {String} name Preference name.
     * @param {Boolean} value
     */
    function savePreference(name, value) {
        UserRepository.setUserPreference(name, value ? 1 : 0).catch(function() {
            return null;
        });
    }

    function isDrawer() {
        return window.innerWidth <= DRAWER_MAX;
    }

    /**
     * Close the drawer whenever the viewport crosses down into drawer territory.
     *
     * The two state classes are complements, so a sidebar left open on the
     * desktop column carries an "open" class into the drawer breakpoint — and
     * the drawer would then be showing over the content, with its backdrop, on
     * a resize the user never asked anything of. Below the breakpoint the
     * sidebar always starts closed; this keeps that true after load too.
     * Going the other way needs nothing: the desktop column reads the
     * "collapsed" class, which the drawer never sets on its own.
     */
    function syncSidebarToBreakpoint() {
        var drawer = isDrawer();
        if (drawer && !wasDrawer) {
            document.body.classList.remove(OPEN);
        }
        wasDrawer = drawer;
    }

    /**
     * Open or close the sidebar. The two classes are complements and are always
     * written together: the drawer breakpoint reads the "open" one (closed by
     * default) while the desktop column reads the "collapsed" one (open by
     * default), so one call covers both without measuring anything.
     *
     * @param {Boolean} open
     */
    function setSidebar(open) {
        var body = document.body;
        body.classList.toggle(OPEN, open);
        body.classList.toggle(COLLAPSED, !open);
        // The drawer is a temporary overlay, not a layout preference — only the
        // desktop column state is worth remembering.
        if (!isDrawer()) {
            savePreference(PREF_SIDEBAR, open);
        }
    }

    function toggleDarkMode() {
        var dark = document.body.classList.toggle(DARK);
        savePreference(PREF_DARK, dark);
    }

    function toggleModule(header) {
        var acts = header.nextElementSibling;
        if (!acts || !acts.classList.contains('fm-activities')) {
            return;
        }
        var open = acts.style.display !== 'none';
        acts.style.display = open ? 'none' : '';
        header.setAttribute('aria-expanded', open ? 'false' : 'true');
        var chev = header.querySelector('.fm-module-chev');
        if (chev) {
            chev.classList.toggle('open', !open);
        }
    }

    /**
     * Expand/collapse the activity description. The strip is a flex-shrink:0 row
     * in a column layout, so growing it pushes the content body down rather than
     * floating over it — and collapsing restores the fixed row height that keeps
     * the strip aligned with the sidebar header.
     *
     * @param {Element} btn The Description toggle.
     */
    function toggleDescription(btn) {
        var desc = document.getElementById('fmIntroDesc');
        if (!desc) {
            return;
        }
        var open = desc.classList.toggle('is-open');
        btn.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    /**
     * Open/close the activity actions menu, closing any other open one first.
     *
     * @param {Element} btn The kebab button.
     * @param {Boolean} [force] Explicit target state.
     */
    function toggleKebab(btn, force) {
        var wrap = btn.closest('.fm-kebab-wrap');
        if (!wrap) {
            return;
        }
        var open = typeof force === 'boolean' ? force : !wrap.classList.contains('is-open');
        wrap.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    /**
     * Open/close the book's chapter list, which hangs off the chapter label.
     *
     * @param {Element} btn The chapter label button.
     * @param {Boolean} [force] Explicit target state.
     */
    function toggleBookToc(btn, force) {
        var wrap = btn.closest('[data-region="fm-book-toc"]');
        if (!wrap) {
            return;
        }
        var open = typeof force === 'boolean' ? force : !wrap.classList.contains('is-open');
        wrap.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        // Long books scroll: bring the chapter being read into view rather than
        // leaving the reader to hunt for it.
        if (open) {
            var current = wrap.querySelector('.fm-book-toc-item.is-current');
            if (current && current.scrollIntoView) {
                current.scrollIntoView({block: 'nearest'});
            }
        }
    }

    function closeBookToc() {
        document.querySelectorAll('[data-region="fm-book-toc"].is-open').forEach(function(wrap) {
            wrap.classList.remove('is-open');
            var btn = wrap.querySelector('[data-fm-action="toggle-booktoc"]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function closeAllKebabs() {
        document.querySelectorAll('.fm-kebab-wrap.is-open').forEach(function(wrap) {
            wrap.classList.remove('is-open');
            var btn = wrap.querySelector('.fm-kebab-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /**
     * Show or hide a thread's inline delete confirmation. Only one is ever open,
     * so asking on a second thread closes the first.
     *
     * @param {Element} el Any element inside the thread card.
     * @param {Boolean} open
     */
    function setThreadConfirm(el, open) {
        var thread = el.closest('[data-region="fm-thread"]');
        if (!thread) {
            return;
        }
        if (open) {
            document.querySelectorAll('[data-region="fm-thread"].is-confirming')
                .forEach(function(other) {
                    other.classList.remove('is-confirming');
                });
        }
        thread.classList.toggle('is-confirming', open);
    }

    /**
     * Delete a forum discussion outright.
     *
     * mod_forum owns the deletion; this only calls the endpoint and drops the
     * card once the server confirms, so a failed request leaves the list exactly
     * as the server still has it.
     *
     * @param {Element} btn The confirm button.
     */
    function deleteThread(btn) {
        var thread = btn.closest('[data-region="fm-thread"]');
        if (!thread || thread.classList.contains('is-deleting')) {
            return;
        }
        var id = parseInt(thread.getAttribute('data-discussionid'), 10);
        if (!id) {
            return;
        }
        thread.classList.add('is-deleting');

        Ajax.call([{
            methodname: 'local_learnova_course_delete_forum_discussion',
            args: {discussionid: id},
        }])[0]
        .then(function() {
            thread.remove();
            return null;
        })
        .catch(function(error) {
            thread.classList.remove('is-deleting');
            thread.classList.remove('is-confirming');
            return Str.get_string('fm_thread_deletefail', 'theme_learnovatheme')
                .then(function(message) {
                    Notification.addNotification({message: message, type: 'error'});
                    return null;
                })
                .catch(function() {
                    Notification.exception(error);
                    return null;
                });
        });
    }

    // ── Activity Discussion panel ───────────────────────────────────────────
    // The panel is server-rendered with the page; this only opens it, sends the
    // writes, and swaps in the list the server returns afterwards. The markup of
    // a thread is the focusmode_threads template in both directions, so a posted
    // reply looks exactly like a reloaded one.

    var DISC_OPEN = 'fm-discussion-open';

    function discPanel() {
        return document.querySelector('[data-region="fm-disc"]');
    }

    /**
     * Open or close the discussion slide-over.
     *
     * @param {Boolean} open
     */
    function setDiscussion(open) {
        var panel = discPanel();
        if (!panel) {
            return;
        }
        document.body.classList.toggle(DISC_OPEN, open);
        var trigger = document.querySelector('[data-fm-action="discussion"]');
        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open) {
            var input = panel.querySelector('[data-region="fm-disc-input"]');
            if (input) {
                input.focus();
            }
        }
    }

    /**
     * Point the composer at a thread, or back at "new topic" when no thread is
     * given. One composer serves both, so the panel never has two open inputs.
     *
     * @param {Element} panel
     * @param {Element} [btn] The Reply button of the thread being answered.
     */
    function setReplyTarget(panel, btn) {
        var form = panel.querySelector('[data-region="fm-disc-form"]');
        if (!form) {
            return;
        }
        var ctx = form.querySelector('[data-region="fm-disc-replyctx"]');
        var name = form.querySelector('[data-region="fm-disc-replyname"]');
        var input = form.querySelector('[data-region="fm-disc-input"]');

        form.setAttribute('data-parentid', btn ? (btn.getAttribute('data-postid') || '0') : '0');
        if (name) {
            name.textContent = btn ? (btn.getAttribute('data-title') || '') : '';
        }
        if (ctx) {
            ctx.hidden = !btn;
        }
        if (btn && input) {
            input.focus();
        }
    }

    /**
     * Show a composer error, or clear it when passed nothing.
     *
     * @param {Element} panel
     * @param {String} [message]
     */
    function setDiscError(panel, message) {
        var box = panel.querySelector('[data-region="fm-disc-error"]');
        if (!box) {
            return;
        }
        box.textContent = message || '';
        box.hidden = !message;
    }

    /**
     * Re-read the activity's threads and rebuild the list.
     *
     * Every write refreshes rather than patching the DOM: reply counts,
     * snippets and ordering are all server-derived, so re-rendering is the only
     * way the panel keeps saying what the server actually holds — including
     * posts made by other people while it was open.
     *
     * @param {Element} panel
     * @return {Promise}
     */
    function refreshThreads(panel) {
        var body = panel.querySelector('[data-region="fm-disc-threads"]');
        var cmid = parseInt(panel.getAttribute('data-cmid'), 10);
        if (!body || !cmid) {
            return Promise.resolve();
        }
        body.classList.add('is-busy');

        // Which topics were open is the reader's place in the panel, not the
        // server's business — so it is remembered here and restored after the
        // swap, otherwise posting a reply would close the thread you replied to.
        var wasopen = Array.prototype.map.call(
            body.querySelectorAll('[data-region="fm-thread"].is-open'),
            function(el) {
                return el.getAttribute('data-postid');
            });

        return Ajax.call([{
            methodname: 'local_learnova_course_activity_threads',
            args: {cmid: cmid},
        }])[0]
        .then(function(response) {
            return Templates.renderForPromise('theme_learnovatheme/focusmode_threads', {
                threads: response.threads,
                hasthreads: response.threads.length > 0,
            }).then(function(result) {
                Templates.replaceNodeContents(body, result.html, result.js);
                updateBadge(response.count);
                wasopen.forEach(function(postid) {
                    var thread = body.querySelector(
                        '[data-region="fm-thread"][data-postid="' + postid + '"]');
                    if (!thread) {
                        return;
                    }
                    thread.classList.add('is-open');
                    var title = thread.querySelector('[data-fm-action="disc-toggle"]');
                    if (title) {
                        title.setAttribute('aria-expanded', 'true');
                    }
                });
                return null;
            });
        })
        .catch(function(error) {
            return Str.get_string('fm_disc_loadfail', 'theme_learnovatheme')
                .then(function(message) {
                    setDiscError(panel, message);
                    return null;
                })
                .catch(function() {
                    Notification.exception(error);
                    return null;
                });
        })
        .then(function() {
            body.classList.remove('is-busy');
            return null;
        });
    }

    /**
     * Write the live post count onto the trigger badge, hiding it at zero.
     *
     * @param {Number} count
     */
    function updateBadge(count) {
        var badge = document.querySelector('[data-region="fm-disc-badge"]');
        if (!badge) {
            return;
        }
        badge.textContent = count;
        badge.classList.toggle('fm-badge--empty', !count);
    }

    /**
     * Post a new topic, or a reply when the composer is pointed at a thread.
     *
     * @param {Element} panel
     */
    function postMessage(panel) {
        var form = panel.querySelector('[data-region="fm-disc-form"]');
        if (!form) {
            return;
        }
        var input = form.querySelector('[data-region="fm-disc-input"]');
        var message = input ? input.value.trim() : '';
        if (message === '') {
            return;
        }
        var btn = form.querySelector('[data-action="disc-post"]');
        if (btn) {
            btn.disabled = true;
        }
        setDiscError(panel);

        Ajax.call([{
            methodname: 'local_learnova_course_post_message',
            args: {
                courseid: parseInt(panel.getAttribute('data-courseid'), 10),
                cmid: parseInt(panel.getAttribute('data-cmid'), 10),
                message: message,
                parentid: parseInt(form.getAttribute('data-parentid'), 10) || 0,
            },
        }])[0]
        .then(function() {
            input.value = '';
            setReplyTarget(panel);
            return refreshThreads(panel);
        })
        .catch(function(error) {
            // The server's own message (empty, too long, no permission) is the
            // useful one; fall back to ours only when there is none.
            if (error && (error.message || error.error)) {
                setDiscError(panel, error.message || error.error);
                return null;
            }
            return Str.get_string('fm_disc_postfail', 'theme_learnovatheme')
                .then(function(text) {
                    setDiscError(panel, text);
                    return null;
                });
        })
        .then(function() {
            if (btn) {
                btn.disabled = false;
            }
            return null;
        });
    }

    /**
     * Delete a thread from the panel, once its inline confirmation is accepted.
     *
     * @param {Element} confirmbtn The Delete button inside the confirmation.
     */
    function deleteDiscussionThread(confirmbtn) {
        var panel = confirmbtn.closest('[data-region="fm-disc"]');
        var thread = confirmbtn.closest('[data-region="fm-thread"]');
        if (!panel || !thread || thread.classList.contains('is-deleting')) {
            return;
        }
        var postid = parseInt(thread.getAttribute('data-postid'), 10);
        if (!postid) {
            return;
        }
        thread.classList.add('is-deleting');

        Ajax.call([{
            methodname: 'local_learnova_course_delete_message',
            args: {postid: postid},
        }])[0]
        .then(function() {
            return refreshThreads(panel);
        })
        .catch(function(error) {
            thread.classList.remove('is-deleting', 'is-confirming');
            return Str.get_string('fm_thread_deletefail', 'theme_learnovatheme')
                .then(function(message) {
                    setDiscError(panel, message);
                    return null;
                })
                .catch(function() {
                    Notification.exception(error);
                    return null;
                });
        });
    }

    /**
     * Size the media player to the largest 16:9 box that fits the space without
     * exceeding it in either direction.
     *
     * Done in JS rather than with aspect-ratio + max-width/max-height because
     * that pairing collapses to a near-zero box here: the player sits inside a
     * scrolling column whose height is content-driven, so the height cap has
     * nothing to resolve against and the width backs off to nothing.
     */
    function fitMedia() {
        document.querySelectorAll('[data-region="fm-media-fit"]').forEach(function(wrap) {
            var frame = wrap.querySelector('.fm-scorm-frame, .mediaplugin, .resourcecontent object, .resourcecontent iframe');
            if (!frame) {
                return;
            }
            var styles = window.getComputedStyle(wrap);
            var availW = wrap.clientWidth
                - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight);

            // Height is measured geometrically, from where this box starts on
            // screen down to the bottom nav — never from an ancestor's height.
            // Every ancestor here can end up sized by its content, and the
            // content is what we are about to resize, so reading one back
            // created a stable-but-wrong fixed point: 151px of player, +80px of
            // padding, = 231px of "available" height, which yields 151 again.
            var top = wrap.getBoundingClientRect().top;
            var navbar = document.querySelector('.fm-nav-bar');
            var floor = navbar ? navbar.getBoundingClientRect().top : window.innerHeight;
            var availH = floor - top
                - parseFloat(styles.paddingTop) - parseFloat(styles.paddingBottom);
            if (availW <= 0 || availH <= 0) {
                return;
            }
            // Width is the only thing set. VideoJS runs in fluid mode and keeps
            // 16:9 from the width via a padding-top ratio, so writing a height
            // as well fights it — and a percentage height inside this column
            // has no definite parent to resolve against, which is what left the
            // player collapsed at the top of an over-tall black box.
            // Content with a fixed stage (a SCORM package authored at, say,
            // 1000x540 with no scaling of its own) is rendered at exactly that
            // size and then scaled to fit. Growing the frame alone would not
            // help: the package would still draw its stage at native size and
            // pad the remainder with its own background colour.
            var natW = parseFloat(frame.getAttribute('data-fm-w'));
            var natH = parseFloat(frame.getAttribute('data-fm-h'));
            if (natW > 0 && natH > 0) {
                var k = Math.min(availW / natW, availH / natH);
                frame.style.width = natW + 'px';
                frame.style.height = natH + 'px';
                frame.style.aspectRatio = 'auto';
                frame.style.transform = 'scale(' + k + ')';
                frame.style.transformOrigin = 'top left';
                // A transform does not affect layout, so the wrapper has to
                // reserve the scaled box itself.
                var holder = frame.parentElement;
                if (holder) {
                    holder.style.width = Math.floor(natW * k) + 'px';
                    holder.style.height = Math.floor(natH * k) + 'px';
                }
                return;
            }

            // Otherwise the player keeps its own ratio and only the width is
            // capped — VideoJS derives the height from it.
            var ratio = parseFloat(frame.getAttribute('data-fm-ratio')) || (16 / 9);
            frame.style.width = Math.floor(Math.min(availW, availH * ratio)) + 'px';
            frame.style.maxWidth = 'none';
        });
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function() {
                    return null;
                });
            }
        } else if (document.exitFullscreen) {
            document.exitFullscreen().catch(function() {
                return null;
            });
        }
    }

    return {
        init: function() {
            // Below the drawer breakpoint the sidebar always starts closed (the
            // SCSS default). Mirror that into the state classes so the first
            // toggle agrees with what the user is actually looking at.
            if (isDrawer()) {
                document.body.classList.remove(OPEN);
            }
            wasDrawer = isDrawer();
            window.addEventListener('resize', syncSidebarToBreakpoint);

            document.addEventListener('click', function(e) {
                var trigger = e.target.closest('[data-fm-action]');

                // Any click that is not on an open menu closes it.
                if (!e.target.closest('.fm-kebab-wrap.is-open')) {
                    closeAllKebabs();
                }
                if (!e.target.closest('[data-region="fm-book-toc"].is-open')) {
                    closeBookToc();
                }

                if (!trigger) {
                    return;
                }
                var action = trigger.getAttribute('data-fm-action');
                if (action === 'toggle-submission') {
                    e.preventDefault();
                    var block = trigger.closest('[data-region="fm-sub-block"]');
                    if (block) {
                        var open = block.classList.toggle('is-open');
                        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                    }
                } else if (action === 'thread-delete-ask') {
                    e.preventDefault();
                    setThreadConfirm(trigger, true);
                } else if (action === 'thread-delete-cancel') {
                    e.preventDefault();
                    setThreadConfirm(trigger, false);
                } else if (action === 'thread-delete-do') {
                    e.preventDefault();
                    deleteThread(trigger);
                } else if (action === 'toggle-desc') {
                    e.preventDefault();
                    toggleDescription(trigger);
                } else if (action === 'toggle-kebab') {
                    e.preventDefault();
                    toggleKebab(trigger);
                } else if (action === 'toggle-booktoc') {
                    e.preventDefault();
                    toggleBookToc(trigger);
                } else if (action === 'discussion') {
                    e.preventDefault();
                    setDiscussion(!document.body.classList.contains(DISC_OPEN));
                } else if (action === 'discussion-close') {
                    e.preventDefault();
                    setDiscussion(false);
                } else if (action === 'disc-toggle') {
                    e.preventDefault();
                    var topic = trigger.closest('[data-region="fm-thread"]');
                    if (topic) {
                        var opened = topic.classList.toggle('is-open');
                        trigger.setAttribute('aria-expanded', opened ? 'true' : 'false');
                    }
                } else if (action === 'disc-reply') {
                    e.preventDefault();
                    var replypanel = trigger.closest('[data-region="fm-disc"]');
                    if (replypanel) {
                        setReplyTarget(replypanel, trigger);
                    }
                } else if (action === 'disc-reply-cancel') {
                    e.preventDefault();
                    var cancelpanel = trigger.closest('[data-region="fm-disc"]');
                    if (cancelpanel) {
                        setReplyTarget(cancelpanel);
                    }
                } else if (action === 'disc-delete-do') {
                    e.preventDefault();
                    deleteDiscussionThread(trigger);
                } else if (action === 'show-sidebar') {
                    e.preventDefault();
                    setSidebar(true);
                } else if (action === 'hide-sidebar') {
                    e.preventDefault();
                    setSidebar(false);
                } else if (action === 'dark-mode') {
                    e.preventDefault();
                    toggleDarkMode();
                } else if (action === 'fullscreen') {
                    e.preventDefault();
                    toggleFullscreen();
                } else if (action === 'toggle-module') {
                    toggleModule(trigger);
                }
            });

            // The composer is a plain form so Enter-to-submit and required-field
            // behaviour stay the browser's; only where it posts to is ours.
            var panel = discPanel();
            if (panel) {
                var form = panel.querySelector('[data-region="fm-disc-form"]');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        postMessage(panel);
                    });
                }
            }

            // Escape closes whatever transient surface is open — the actions
            // menu first, then the discussion panel, then the drawer (a backdrop
            // tap is not always reachable for keyboard users).
            document.addEventListener('keydown', function(e) {
                if (e.key !== 'Escape') {
                    return;
                }
                var confirming = document.querySelector('[data-region="fm-thread"].is-confirming');
                var booktoc = document.querySelector('[data-region="fm-book-toc"].is-open');
                if (document.querySelector('.fm-kebab-wrap.is-open')) {
                    closeAllKebabs();
                } else if (booktoc) {
                    closeBookToc();
                    var tocbtn = booktoc.querySelector('[data-fm-action="toggle-booktoc"]');
                    if (tocbtn) {
                        tocbtn.focus();
                    }
                } else if (confirming) {
                    confirming.classList.remove('is-confirming');
                } else if (document.body.classList.contains(DISC_OPEN)) {
                    setDiscussion(false);
                } else if (isDrawer() && document.body.classList.contains(OPEN)) {
                    setSidebar(false);
                }
            });

            // Media sizing depends on the space the chrome leaves, so refit
            // whenever that space changes — including the sidebar transition,
            // which finishes after the click that started it.
            fitMedia();
            window.addEventListener('resize', fitMedia);
            window.addEventListener('load', fitMedia);
            // The first pass can run before the shell has its final geometry;
            // watching the scrolling column means a stale result is corrected
            // rather than left standing.
            var slot = document.getElementById('fmContentBody');
            if (slot && window.ResizeObserver) {
                new ResizeObserver(fitMedia).observe(slot);
            }
            document.addEventListener('transitionend', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('fm-sidebar')) {
                    fitMedia();
                }
            });

            // Keep the fullscreen button state in sync if the user exits via Esc.
            document.addEventListener('fullscreenchange', function() {
                var btn = document.querySelector('[data-fm-action="fullscreen"]');
                if (btn) {
                    btn.classList.toggle('active', !!document.fullscreenElement);
                }
            });
        }
    };
});
