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
use core_external\external_single_structure;
use core_external\external_value;
use local_learnova_course\discussion_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint: soft-delete a course discussion post (moderators only).
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_message extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Id of the post to delete.'),
        ]);
    }

    /**
     * @param int $postid
     * @return array
     */
    public static function execute(int $postid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid]);

        // Resolve the owning scope from the post so we can validate its context:
        // the module for an activity-scoped post, the course otherwise.
        $post = $DB->get_record(discussion_manager::TABLE,
            ['id' => $params['postid']], 'id, courseid, cmid', MUST_EXIST);
        if ((int) $post->cmid > 0) {
            $context = \context_module::instance($post->cmid);
        } else {
            $context = \context_course::instance($post->courseid);
        }
        self::validate_context($context);

        discussion_manager::delete_post($params['postid'], $context);

        return ['status' => true, 'postid' => $params['postid']];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the post was deleted.'),
            'postid' => new external_value(PARAM_INT, 'The deleted post id.'),
        ]);
    }
}
