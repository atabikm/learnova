// AMD module: local_learnova_course/my_access
// "Reset My Completion": un-tick the activities the learner marked complete by
// hand. Each one goes through the NATIVE web service
// core_completion_update_activity_completion_status_manually — the same call the
// checkbox on the course page makes — so the completion state, its events and
// any criteria that depend on it stay consistent.
//
// Resetting cannot be undone by the panel (the learner has to do the activity
// again), so it asks first.
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    'use strict';

    function reset(root) {
        var calls = [];
        root.querySelectorAll('[data-region="rc-pick"]').forEach(function(box) {
            if (box.checked) {
                calls.push({
                    methodname: 'core_completion_update_activity_completion_status_manually',
                    args: {cmid: parseInt(box.value, 10), completed: false}
                });
            }
        });
        if (!calls.length) {
            return;
        }
        Promise.all(Ajax.call(calls)).then(function() {
            // The list is "what you have completed", so it changes wholesale.
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    }

    function confirmReset(root) {
        require(['core/modal_save_cancel', 'core/modal_events'], function(ModalSaveCancel, ModalEvents) {
            ModalSaveCancel.create({
                title: Str.get_string('rc_confirm_title', 'local_learnova_course'),
                body: Str.get_string('rc_confirm_body', 'local_learnova_course'),
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('rc_reset', 'local_learnova_course'));
                modal.getRoot().on(ModalEvents.save, function() {
                    reset(root);
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    }

    return {
        init: function() {
            var root = document.querySelector('[data-cm-panel="reset-completion"]');
            if (!root || root.dataset.lvMaInit) {
                return;
            }
            root.dataset.lvMaInit = '1';

            root.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="rc-reset"]')) {
                    confirmReset(root);
                }
            });
        }
    };
});
