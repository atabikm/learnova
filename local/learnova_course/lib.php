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
 * Library callbacks for local_learnova_course.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Fragment: the "Edit participant" modal body for the Course Management
 * Participants panel — role + group checkboxes for one user, rendered with the
 * lm-* components so the popup matches Learnova's design. The save itself is
 * done client-side via the native role/group web services.
 *
 * @param array $args Fragment arguments: 'context' (course context, from the
 *                    contextid passed to loadFragment) and 'userid'.
 * @return string Rendered HTML.
 */
function local_learnova_course_output_fragment_edit_participant(array $args): string {
    global $OUTPUT;

    $context = $args['context'];
    if ($context->contextlevel !== CONTEXT_COURSE) {
        throw new coding_exception('edit_participant fragment requires a course context');
    }
    require_capability('moodle/course:viewparticipants', $context);

    $courseid = $context->instanceid;
    $userid   = (int) ($args['userid'] ?? 0);
    // The user must actually be enrolled in this course.
    if (!$userid || !is_enrolled($context, $userid)) {
        throw new moodle_exception('invaliduser');
    }

    $data = \local_learnova_course\participants::edit_context($courseid, $userid, $context);
    return $OUTPUT->render_from_template('local_learnova_course/edit_participant', $data);
}
