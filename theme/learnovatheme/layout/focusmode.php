<?php
// Learnova focus-mode layout.
//
// Distraction-free chrome for activity *view* pages (mod/*/view.php). When the
// page is not an activity view, or the user is editing, this delegates to the
// standard drawers layout so editing, blocks and settings keep working.
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

if (!\theme_learnovatheme\focus_mode::applies($PAGE)) {
    // Fall back to the normal Learnova drawers layout.
    require(__DIR__ . '/drawers.php');
    return;
}

// Chrome state is stamped onto <body> server-side so the sidebar and the dark
// palette are already correct on first paint. Below the tablet breakpoint the
// sidebar is an off-canvas drawer that always starts closed, which the SCSS
// handles on its own — the preference only drives the desktop column, so an
// explicit "collapsed" class is emitted rather than an "open" one.
$bodyclasses = ['learnova-focus-mode'];
if (isloggedin() && !isguestuser()) {
    if (!get_user_preferences('theme_learnovatheme_fm_sidebar', true)) {
        $bodyclasses[] = 'fm-sidebar-collapsed';
    }
    if (get_user_preferences('theme_learnovatheme_fm_dark', false)) {
        $bodyclasses[] = 'fm-dark';
    }
}

$bodyattributes = $OUTPUT->body_attributes($bodyclasses);

$templatecontext = [
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'sitename'       => format_string(
        $SITE->shortname,
        true,
        ['context' => context_course::instance(SITEID), 'escape' => false]
    ),
];
$templatecontext += \theme_learnovatheme\focus_mode::context($PAGE, $OUTPUT);

echo $OUTPUT->render_from_template('theme_learnovatheme/focusmode', $templatecontext);
