// AMD module: theme_learnovatheme/login_modal
// Intercepts clicks on [data-action="learnova-login"], fetches the Moodle
// logintoken (CSRF), then renders theme_learnovatheme/login_modal.mustache into
// a Moodle modal. Falls back to full-page redirect on any error.
define(['core/modal', 'core/modal_events', 'core/templates'],
function(Modal, ModalEvents, Templates) {
    'use strict';

    function fetchLoginToken(loginUrl) {
        return fetch(loginUrl, {credentials: 'same-origin'})
            .then(function(r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.text();
            })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var el  = doc.querySelector('input[name="logintoken"]');
                return el ? el.value : '';
            });
    }

    function init() {
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('[data-action="learnova-login"]');
            if (!trigger) { return; }

            e.preventDefault();
            var loginUrl = trigger.dataset.url || (M.cfg.wwwroot + '/login/index.php');

            fetchLoginToken(loginUrl)
                .then(function(token) {
                    var wantsurl = encodeURIComponent(window.location.href);
                    var context = {
                        loginurl:  loginUrl,
                        token:     token,
                        oauthurl:  M.cfg.wwwroot + '/auth/oauth2/login.php?wantsurl=' + wantsurl,
                        forgoturl: M.cfg.wwwroot + '/login/forgot_password.php',
                        wwwroot:   M.cfg.wwwroot,
                    };
                    return Modal.create({
                        title: '',
                        body:  Templates.render('theme_learnovatheme/login_modal', context),
                        large: false,
                        show:  false,
                        removeOnClose: true,
                        isVerticallyCentered: true,
                    });
                })
                .then(function(modal) {
                    var root = modal.getRoot();
                    root.addClass('learnova-login-modal');
                    root.find('.modal-header, .modal-footer').addClass('d-none');
                    root.find('.modal-body').addClass('p-0');

                    root.on(ModalEvents.shown, function() {
                        var input = root[0].querySelector('#lnv-username');
                        if (input) { input.focus(); }
                    });

                    modal.show();
                })
                .catch(function(err) {
                    window.console && window.console.error('Learnova login modal failed:', err);
                    window.location.href = loginUrl;
                });
        });
    }

    return {init: init};
});
