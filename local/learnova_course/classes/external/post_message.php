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
use core_external\external_value;
use local_learnova_course\discussion_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint: post a message or a 1-level reply to a course discussion.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_message extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id the post belongs to.'),
            'message'  => new external_value(PARAM_RAW, 'Plain-text message body.'),
            'parentid' => new external_value(PARAM_INT, 'Parent post id, 0 for a top-level post.', VALUE_DEFAULT, 0),
            'cmid'     => new external_value(PARAM_INT,
                'Course module the post is scoped to, 0 for the course-level discussion.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int    $courseid
     * @param string $message
     * @param int    $parentid
     * @param int    $cmid
     * @return array The created post, exported for the current viewer.
     */
    public static function execute(int $courseid, string $message, int $parentid = 0, int $cmid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'message'  => $message,
            'parentid' => $parentid,
            'cmid'     => $cmid,
        ]);

        // An activity-scoped post is written in the module's context, so a
        // module the user cannot see is refused before anything is stored.
        if ($params['cmid'] > 0) {
            [, $cm] = get_course_and_cm_from_cmid($params['cmid'], '', $params['courseid']);
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
            if (!$cm->uservisible) {
                throw new \moodle_exception('disc_error_cannotpost', 'local_learnova_course');
            }
        } else {
            $context = \context_course::instance($params['courseid']);
            self::validate_context($context);
        }

        if (!discussion_manager::can_post($context)) {
            throw new \moodle_exception('disc_error_cannotpost', 'local_learnova_course');
        }

        return discussion_manager::create_post(
            $params['courseid'], $context, (int) $USER->id,
            $params['message'], $params['parentid'], $params['cmid']
        );
    }

    /**
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return discussion_manager::post_structure();
    }
}
