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
 * External (AJAX) function definitions for local_learnova_course.
 *
 * Powers the custom course-level Discussion tab on the course landing page:
 * post a message or reply, and (for moderators) delete a post — all without a
 * full page reload.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_learnova_course_post_message' => [
        'classname'   => 'local_learnova_course\external\post_message',
        'description' => 'Post a message or a 1-level reply to a course discussion.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_learnova_course_delete_message' => [
        'classname'   => 'local_learnova_course\external\delete_message',
        'description' => 'Soft-delete a course discussion post (moderators only).',
        'type'        => 'write',
        'ajax'        => true,
    ],
    // Focus mode's Activity Discussion panel: the thread list for one activity.
    // Writes reuse post_message / delete_message above, scoped by cmid.
    'local_learnova_course_activity_threads' => [
        'classname'   => 'local_learnova_course\external\activity_threads',
        'description' => 'List the discussion threads scoped to one course module.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    // Focus mode's forum thread list. Core's mod_forum_delete_post is registered
    // for the mobile service only, so the browser needs its own entry point.
    'local_learnova_course_delete_forum_discussion' => [
        'classname'   => 'local_learnova_course\external\delete_forum_discussion',
        'description' => 'Delete a whole forum discussion (mod/forum:deleteanypost).',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
