// AMD module: local_learnova_course/grade
// Interactivity for the Course Management grade panels.
//   - Grade Overview "Viewing as" selector: submit its GET form on change so a
//     teacher can switch learner without a separate Apply button. The reload
//     re-opens the same panel (pane=grade-overview) with the chosen user.
// Everything else (structure, data, the grader report search/export/show-all)
// is plain server-rendered markup + GET links — no JS needed.
define([], function() {
    'use strict';

    return {
        init: function() {
            document.querySelectorAll('select[data-autosubmit]').forEach(function(select) {
                if (select.dataset.lvAutosubmit) {
                    return; // Already wired (init can run per panel render).
                }
                select.dataset.lvAutosubmit = '1';
                select.addEventListener('change', function() {
                    if (select.form) {
                        select.form.submit();
                    }
                });
            });
        }
    };
});
