// AMD module: theme_learnovatheme/embed_form
// Runs inside a Learnova embedded-form iframe (clembed shell) to normalise the
// native form's action buttons to a single "Save changes" + Cancel — the wording
// the section-edit form already uses — regardless of which form landed there.
//
// Three flavours reach the shell:
//   • Activity (modedit): "Save and return to course" (submitbutton2),
//     "Save and display" (submitbutton), Cancel.
//   • Course settings (course/edit.php, Course Management panel): "Save changes
//     and return" (saveandreturn, only when returnto is set), "Save changes and
//     display" (saveanddisplay), Cancel.
//   • Section (editsection): a single "Save changes" (submitbutton) + Cancel —
//     already correct, so it's left untouched.
// In each multi-button case we keep the button that returns to where we came
// from, relabel it "Save changes", and hide the "…and display" one.
define(['core/str'], function(Str) {
    'use strict';

    // Relabel a submit control (INPUT or BUTTON) to the core "Save changes".
    function relabel(btn) {
        Str.get_string('savechanges', 'core').then(function(label) {
            if (btn.tagName === 'INPUT') {
                btn.value = label;
            } else {
                btn.textContent = label;
            }
            return label;
        }).catch(function() {
            return null;
        });
    }

    return {
        init: function() {
            // Activity (modedit) form.
            var modeditReturn = document.querySelector('[name="submitbutton2"]');
            if (modeditReturn) {
                var modeditDisplay = document.querySelector('[name="submitbutton"]');
                if (modeditDisplay) {
                    modeditDisplay.style.display = 'none';
                }
                relabel(modeditReturn);
                return;
            }

            // Course settings (course/edit.php) form. "Save and return" only
            // exists while returnto is set; after a save-and-return round-trip
            // the reloaded form has just "Save and display" — relabel whichever
            // is present so the button always reads "Save changes".
            var courseReturn = document.querySelector('[name="saveandreturn"]');
            var courseDisplay = document.querySelector('[name="saveanddisplay"]');
            if (courseReturn) {
                if (courseDisplay) {
                    courseDisplay.style.display = 'none';
                }
                relabel(courseReturn);
            } else if (courseDisplay) {
                relabel(courseDisplay);
            }
        }
    };
});
