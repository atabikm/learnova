// AMD module: local_learnova_course/recyclebin
// Interactivity for the Course Management "Recycle Bin" panel: confirm before
// a permanent delete. Deleting a bin item is the one action here that cannot be
// undone — Restore is not confirmed, it simply puts the activity back.
//
// The links themselves are plain sesskey-guarded GETs back to management.php
// (see \local_learnova_course\recyclebin), so without JS the panel still works;
// this only inserts the confirmation step.
define(['core/str'], function(Str) {
    'use strict';

    function confirmDelete(name, url) {
        require(['core/modal_save_cancel', 'core/modal_events', 'core/notification'],
            function(ModalSaveCancel, ModalEvents, Notification) {
                ModalSaveCancel.create({
                    title: Str.get_string('rb_delete_title', 'local_learnova_course'),
                    body: Str.get_string('rb_delete_confirm', 'local_learnova_course', name),
                }).then(function(modal) {
                    modal.setSaveButtonText(Str.get_string('rb_delete', 'local_learnova_course'));

                    modal.getRoot().on(ModalEvents.save, function() {
                        window.location.assign(url);
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
            var root = document.querySelector('[data-cm-panel="recycle-bin"]');
            if (!root || root.dataset.lvRbInit) {
                return;
            }
            root.dataset.lvRbInit = '1';

            root.addEventListener('click', function(e) {
                var link = e.target.closest('[data-action="rb-delete"]');
                if (!link) {
                    return;
                }
                e.preventDefault();
                confirmDelete(link.getAttribute('data-name'), link.getAttribute('href'));
            });
        }
    };
});
