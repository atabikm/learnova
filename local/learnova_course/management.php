<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course Management page — the destination of the "More →" button on the
 * course landing page.
 *
 * Open to every user who can access the course; the sidebar menu adapts to
 * the viewer's role via per-item capability checks (see
 * \local_learnova_course\management::menu()), like the old "More" dropdown.
 *
 * Layout: global theme topbar + 220px sidebar (Back to Course pill, 5 menu
 * groups) + full-width main area. Each menu item switches to its own panel
 * client-side (no reload); panels are placeholders until each tool's own
 * story lands.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course   = get_course($courseid);
$context  = context_course::instance($courseid);

require_login($course);

// Which panel to open on load + panel-specific filter/paging state. The panels
// switch client-side, but participants filtering/pagination reloads the page
// with these, so we re-open the right panel with the right data.
$params = [
    'pane'    => optional_param('pane', '', PARAM_ALPHANUMEXT),
    'psearch' => optional_param('psearch', '', PARAM_NOTAGS),
    'prole'   => optional_param('prole', 0, PARAM_INT),
    'pgroup'  => optional_param('pgroup', 0, PARAM_INT),
    'ppage'   => optional_param('ppage', 0, PARAM_INT),
    // Grade Overview: which learner a teacher is viewing.
    'gouser'  => optional_param('gouser', 0, PARAM_INT),
    // Grader Report: user filter + "show all" toggle.
    'grsearch' => optional_param('grsearch', '', PARAM_NOTAGS),
    'grall'    => optional_param('grall', 0, PARAM_BOOL),
    // Grades View: which tab of the panel is open.
    'gtab'     => optional_param('gtab', '', PARAM_ALPHANUMEXT),
    // Question Banks: which bank (course module id) is open.
    'qbank'    => optional_param('qbank', 0, PARAM_INT),
];

// Grader Report CSV export: stream the file and exit before any page output.
// The builder re-checks moodle/grade:viewall itself.
if (optional_param('graderexport', 0, PARAM_BOOL)) {
    \local_learnova_course\grader_report::export_csv($course, $context, $params);
}

// Recycle Bin row actions (restore / delete permanently). Handled before any
// output because they redirect back to this page; sesskey and the recycle-bin
// capabilities are checked by the handler.
$rbaction = optional_param('rbaction', '', PARAM_ALPHA);
if ($rbaction === 'restore' || $rbaction === 'delete') {
    \local_learnova_course\recyclebin::handle_action(
        $course, $context, $rbaction, required_param('rbitem', PARAM_INT));
}

$title = get_string('management_title', 'local_learnova_course');

$PAGE->set_url(new moodle_url('/local/learnova_course/management.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($course->fullname) . ': ' . $title);
$PAGE->set_heading(format_string($course->fullname));
// Scopes the theme CSS that hides Moodle's default page chrome (page header,
// breadcrumb) and lets the section go full-bleed under the navbar.
$PAGE->add_body_class('learnova-cm-page');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_learnova_course/management',
    \local_learnova_course\management::context($course, $params));
echo $OUTPUT->footer();
