// AMD module: local_learnova_course/qbank
// Interactivity for the Course Management "Question Banks" panel. Everything
// here exists so the panel never leaves the page:
//   - "Manage" points the bank frame at another bank in place (no reload);
//   - "+ Add question bank" slides in the native mod_qbank activity form;
//   - "Create a new question" slides in our question-type chooser, then the
//     REAL native question form for the chosen type.
//
// Nothing re-implements question editing: the chooser only decides which native
// form URL to load, and the panel closes itself when that form leaves (saved or
// cancelled), refreshing the bank behind it.
//
// URLs always come from data attributes, never from JS literals: Mustache
// escapes & to &amp; in both, but only in an attribute does the HTML parser
// decode it back — in a string literal it would break every query parameter
// after the first.
define(['local_learnova_course/frame', 'core/str'], function(Frame, Str) {
    'use strict';

    var QUESTION_FORM = '/question/bank/editquestion/question.php';
    var ACTIVITY_FORM = '/course/modedit.php';

    function parts(root) {
        return {
            slide: root.querySelector('[data-region="qb-slide"]'),
            veil: root.querySelector('[data-region="qb-veil"]'),
            chooser: root.querySelector('[data-region="qb-chooser"]'),
            frame: root.querySelector('[data-region="qb-slide-frame"]'),
            title: root.querySelector('[data-region="qb-slide-title"]'),
            badge: root.querySelector('[data-region="qb-slide-badge"]'),
            card: root.querySelector('[data-region="qb-open"]'),
            cardname: root.querySelector('[data-region="qb-open-name"]'),
            bank: root.querySelector('[data-region="qb-frame"]'),
            newbtn: root.querySelector('[data-action="qb-new"]')
        };
    }

    // Point the bank frame at a URL and (re)load it.
    function loadBank(p, url) {
        if (!p.bank || !url) {
            return;
        }
        p.bank.setAttribute('data-src', url);
        p.bank.style.height = '';
        p.bank._cmHeight = 0;
        delete p.bank.dataset.cmLoaded;
        Frame.load(p.bank, url);
    }

    function closeSlide(root, reloadbank) {
        var p = parts(root);
        p.slide.classList.remove('is-open');
        p.veil.classList.remove('is-open');
        p.chooser.hidden = true;
        p.frame.hidden = true;
        p.frame.src = 'about:blank';

        if (reloadbank && p.bank) {
            loadBank(p, p.bank.getAttribute('data-src'));
        }
    }

    function openSlide(root, title, badge) {
        var p = parts(root);
        if (title) {
            p.title.textContent = title;
        }
        if (badge) {
            p.badge.textContent = badge;
        }
        p.veil.classList.add('is-open');
        p.slide.classList.add('is-open');
    }

    // Show a form in the slide-in (adding a bank goes straight here; creating a
    // question comes here after a type is chosen).
    function loadSlideForm(root, url) {
        var p = parts(root);
        p.chooser.hidden = true;
        p.frame.hidden = false;
        p.frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'cb=' + Date.now();
    }

    return {
        init: function() {
            var root = document.querySelector('[data-cm-panel="question-banks"]');
            if (!root || root.dataset.lvQbInit) {
                return;
            }
            root.dataset.lvQbInit = '1';
            var p = parts(root);
            // The form URL of the bank currently open, for the chooser tiles.
            var questionform = p.newbtn ? p.newbtn.getAttribute('data-formurl') : '';

            if (p.bank) {
                p.bank.addEventListener('load', function() {
                    var doc;
                    try {
                        doc = p.bank.contentDocument;
                    } catch (e) {
                        return;
                    }
                    if (!doc || !doc.body) {
                        return;
                    }
                    p.bank.classList.remove('is-loading');
                    Frame.fit(p.bank);
                    Frame.observe(p.bank);
                });
                // Load the bank the page opened with, once the panel is shown.
                var loadopen = function() {
                    if (!p.card.hidden && !p.bank.dataset.cmLoaded) {
                        Frame.load(p.bank, p.bank.getAttribute('data-src'));
                    }
                };
                root.addEventListener('learnova-cm:panelshown', loadopen);
                if (root.classList.contains('is-show')) {
                    loadopen();
                }
            }

            // The slide-in's frame: a native form that has left its own script
            // is a finished save or cancel, so close and refresh behind it.
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
                if (href.indexOf(QUESTION_FORM) === -1 && href.indexOf(ACTIVITY_FORM) === -1) {
                    // Adding a bank changes the list itself, so that one needs a
                    // reload of the page; a saved question only changes the bank.
                    if (p.frame.dataset.qbAdding) {
                        window.location.reload();
                        return;
                    }
                    closeSlide(root, true);
                }
            });

            root.addEventListener('click', function(e) {
                var manage = e.target.closest('[data-action="qb-manage"]');
                if (manage) {
                    p.card.hidden = false;
                    p.cardname.textContent = manage.getAttribute('data-name');
                    questionform = manage.getAttribute('data-formurl');
                    p.newbtn.hidden = !questionform;
                    loadBank(p, manage.getAttribute('data-url'));
                    p.card.scrollIntoView({behavior: 'smooth', block: 'start'});
                    return;
                }

                var add = e.target.closest('[data-action="qb-add"]');
                if (add) {
                    p.frame.dataset.qbAdding = '1';
                    Str.get_string('qb_add_bank_title', 'local_learnova_course')
                        .then(function(title) {
                            openSlide(root, title, null);
                            return null;
                        }).catch(function() {
                            openSlide(root, null, null);
                        });
                    loadSlideForm(root, add.getAttribute('data-url'));
                    return;
                }

                if (e.target.closest('[data-action="qb-new"]')) {
                    delete p.frame.dataset.qbAdding;
                    openSlide(root, null, null);
                    p.chooser.hidden = false;
                    p.frame.hidden = true;
                    return;
                }

                if (e.target.closest('[data-action="qb-close"]')
                        || e.target.closest('[data-region="qb-veil"]')) {
                    closeSlide(root, false);
                    return;
                }

                var tile = e.target.closest('[data-action="qb-qtype"]');
                if (tile && questionform) {
                    loadSlideForm(root, questionform + '&qtype='
                        + encodeURIComponent(tile.getAttribute('data-qtype')));
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && p.slide.classList.contains('is-open')) {
                    closeSlide(root, false);
                }
            });
        }
    };
});
