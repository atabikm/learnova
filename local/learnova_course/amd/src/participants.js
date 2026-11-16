// AMD module: local_learnova_course/participants
// Interactivity for the Course Management "Participants" panel (a custom lm-*
// table on the management page — NOT an iframe):
//   - multiselect: header select-all + per-row checkboxes → bulk bar → native
//     bulk-message modal (core_message/message_send_bulk)
//   - per-row Edit: opens a styled modal (fragment body) to change the user's
//     roles + groups, saved via the native role/group web services
//
// Selection is per page (pagination reloads); bulk/edit act on the shown rows.
define(['core/str'], function(Str) {
    'use strict';

    // Open the "Edit participant" modal: role + group checkboxes from a fragment,
    // saved by diffing checked vs the initial (defaultChecked) state.
    function openEditModal(userid, name, contextid) {
        require([
            'core/modal_save_cancel', 'core/modal_events', 'core/fragment',
            'core/ajax', 'core/str', 'core/notification'
        ], function(ModalSaveCancel, ModalEvents, Fragment, Ajax, Str, Notification) {
            ModalSaveCancel.create({
                title: Str.get_string('pt_edit_title', 'local_learnova_course', name),
                body: Fragment.loadFragment('local_learnova_course', 'edit_participant', contextid, {userid: userid}),
                large: false,
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('savechanges', 'core'));

                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    var body = modal.getBody()[0];
                    var calls = [];
                    var assign = [], unassign = [], gadd = [], gdel = [];

                    body.querySelectorAll('[data-region="pt-role"]').forEach(function(cb) {
                        var rid = parseInt(cb.getAttribute('data-roleid'), 10);
                        if (cb.checked && !cb.defaultChecked) {
                            assign.push({roleid: rid, userid: userid, contextid: contextid});
                        } else if (!cb.checked && cb.defaultChecked) {
                            unassign.push({roleid: rid, userid: userid, contextid: contextid});
                        }
                    });
                    body.querySelectorAll('[data-region="pt-group"]').forEach(function(cb) {
                        var gid = parseInt(cb.getAttribute('data-groupid'), 10);
                        if (cb.checked && !cb.defaultChecked) {
                            gadd.push({groupid: gid, userid: userid});
                        } else if (!cb.checked && cb.defaultChecked) {
                            gdel.push({groupid: gid, userid: userid});
                        }
                    });

                    if (assign.length) {
                        calls.push({methodname: 'core_role_assign_roles', args: {assignments: assign}});
                    }
                    if (unassign.length) {
                        calls.push({methodname: 'core_role_unassign_roles', args: {unassignments: unassign}});
                    }
                    if (gadd.length) {
                        calls.push({methodname: 'core_group_add_group_members', args: {members: gadd}});
                    }
                    if (gdel.length) {
                        calls.push({methodname: 'core_group_delete_group_members', args: {members: gdel}});
                    }

                    // Native enrol form(s): serialise each and submit via the
                    // native web service (the form carries its own sesskey + qf
                    // marker, so the serialisation is exactly what it expects).
                    body.querySelectorAll('[data-region="pt-enrol"] form').forEach(function(form) {
                        var formdata = new URLSearchParams(new FormData(form)).toString();
                        calls.push({methodname: 'core_enrol_submit_user_enrolment_form', args: {formdata: formdata}});
                    });

                    if (!calls.length) {
                        modal.hide();
                        return;
                    }
                    Promise.all(Ajax.call(calls)).then(function() {
                        // Reload so the table reflects the new roles/groups.
                        window.location.reload();
                        return null;
                    }).catch(Notification.exception);
                });

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    }

    // Confirm + unenrol a user from the course. A user may hold several
    // enrolments (manual + self, etc.); we drop each user_enrolment id in turn.
    function confirmDelete(name, ueids) {
        require([
            'core/modal_save_cancel', 'core/modal_events', 'core/ajax',
            'core/str', 'core/notification'
        ], function(ModalSaveCancel, ModalEvents, Ajax, Str, Notification) {
            ModalSaveCancel.create({
                title: Str.get_string('pt_delete_title', 'local_learnova_course', name),
                body: Str.get_string('pt_delete_confirm', 'local_learnova_course', name),
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('pt_delete', 'local_learnova_course'));

                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    var calls = ueids.map(function(ueid) {
                        return {methodname: 'core_enrol_unenrol_user_enrolment', args: {ueid: ueid}};
                    });
                    Promise.all(Ajax.call(calls)).then(function() {
                        window.location.reload();
                        return null;
                    }).catch(Notification.exception);
                });

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    }

    // Open the native "Enrol users" form (enrol_manual fragment) in a styled
    // modal. Submit mirrors Moodle's own quickenrolment.js: strip the multiselect
    // force-submission marker, then GET enrol/manual/ajax.php with the serialised
    // form. On success (JSON {error?, count}) reload so the table reflects it.
    function openEnrolModal(contextid) {
        require([
            'core/modal_save_cancel', 'core/modal_events', 'core/fragment',
            'core/str', 'core/notification', 'core/config'
        ], function(ModalSaveCancel, ModalEvents, Fragment, Str, Notification, Config) {
            ModalSaveCancel.create({
                large: true,
                title: Str.get_string('enrolusers', 'enrol_manual'),
                body: Fragment.loadFragment('enrol_manual', 'enrol_users_form', contextid, {}),
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('enrolusers', 'enrol_manual'));

                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    var form = modal.getBody()[0].querySelector('form');
                    if (!form) {
                        modal.hide();
                        return;
                    }
                    // Drop Moodle's multiselect force-submission marker (native does
                    // the same); left in, ajax.php treats it as a no-op re-render.
                    form.querySelectorAll("input[value='_qf__force_multiselect_submission']")
                        .forEach(function(f) { f.remove(); });
                    var query = new URLSearchParams(new FormData(form)).toString();
                    modal.hide();
                    fetch(Config.wwwroot + '/enrol/manual/ajax.php?' + query, {
                        method: 'GET',
                        credentials: 'same-origin'
                    }).then(function(r) {
                        return r.json();
                    }).then(function(resp) {
                        if (resp.error) {
                            throw new Error(resp.error);
                        }
                        window.location.reload();
                        return null;
                    }).catch(Notification.exception);
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
            var table = document.querySelector('[data-region="pt-table"]');
            if (!table) {
                return;
            }
            var root      = table.closest('.lm-card') || document;
            var selectAll = root.querySelector('[data-action="pt-selectall"]');
            var bulkbar   = root.querySelector('[data-region="pt-bulkbar"]');
            var countEl   = root.querySelector('[data-region="pt-count"]');

            var rows = function() {
                return Array.prototype.slice.call(table.querySelectorAll('[data-region="pt-row"]'));
            };
            var selectedIds = function() {
                return rows()
                    .filter(function(c) { return c.checked; })
                    .map(function(c) { return parseInt(c.getAttribute('data-userid'), 10); });
            };

            function refresh() {
                var all = rows();
                var n = all.filter(function(c) { return c.checked; }).length;
                if (bulkbar) {
                    bulkbar.hidden = n === 0;
                }
                if (selectAll) {
                    selectAll.checked = n > 0 && n === all.length;
                    selectAll.indeterminate = n > 0 && n < all.length;
                }
                if (countEl) {
                    Str.get_string('pt_selected', 'local_learnova_course', n).then(function(s) {
                        countEl.textContent = s;
                        return s;
                    }).catch(function() { return null; });
                }
            }

            root.addEventListener('change', function(e) {
                if (e.target.matches('[data-action="pt-selectall"]')) {
                    var on = e.target.checked;
                    rows().forEach(function(c) { c.checked = on; });
                    refresh();
                } else if (e.target.matches('[data-region="pt-row"]')) {
                    refresh();
                }
            });

            root.addEventListener('click', function(e) {
                var editBtn = e.target.closest('[data-action="pt-edit"]');
                if (editBtn) {
                    openEditModal(
                        parseInt(editBtn.getAttribute('data-userid'), 10),
                        editBtn.getAttribute('data-name'),
                        parseInt(table.getAttribute('data-contextid'), 10)
                    );
                    return;
                }
                var delBtn = e.target.closest('[data-action="pt-delete"]');
                if (delBtn) {
                    var ueids = (delBtn.getAttribute('data-ueids') || '').split(',')
                        .map(function(s) { return parseInt(s, 10); })
                        .filter(function(n) { return !isNaN(n); });
                    if (ueids.length) {
                        confirmDelete(delBtn.getAttribute('data-name'), ueids);
                    }
                    return;
                }
                var enrolBtn = e.target.closest('[data-action="pt-enrol-users"]');
                if (enrolBtn) {
                    openEnrolModal(parseInt(enrolBtn.getAttribute('data-contextid'), 10));
                    return;
                }
                if (e.target.closest('[data-action="pt-message"]')) {
                    var ids = selectedIds();
                    if (ids.length) {
                        require(['core_message/message_send_bulk'], function(Bulk) {
                            Bulk.showModal(ids);
                        });
                    }
                    return;
                }
                if (e.target.closest('[data-action="pt-clear"]')) {
                    rows().forEach(function(c) { c.checked = false; });
                    refresh();
                }
            });

            refresh();
        }
    };
});
