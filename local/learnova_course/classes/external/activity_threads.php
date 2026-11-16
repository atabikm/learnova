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

namespace local_learnova_course\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_learnova_course\discussion_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint: the discussion threads of one activity.
 *
 * The focus-mode Activity Discussion panel re-reads the whole list after every
 * write, so what the reader sees is always what the server holds — including
 * posts other people made while the panel was open.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_threads extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id the discussion is scoped to.'),
        ]);
    }

    /**
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_module::instance($cm->id);
        // Covers login, enrolment and the activity's own availability: it runs
        // require_login() with this module, so a hidden or restricted activity
        // never reaches the query below.
        self::validate_context($context);

        return [
            'threads' => discussion_manager::get_threads((int) $course->id, (int) $cm->id, $context),
            'count'   => discussion_manager::count_posts((int) $course->id, (int) $cm->id),
            'canpost' => discussion_manager::can_post($context),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'threads' => new external_multiple_structure(discussion_manager::thread_structure(),
                'Threads, newest first.'),
            'count'   => new external_value(PARAM_INT, 'Visible posts and replies, for the trigger badge.'),
            'canpost' => new external_value(PARAM_BOOL, 'Whether the viewer may start a thread or reply.'),
        ]);
    }
}
