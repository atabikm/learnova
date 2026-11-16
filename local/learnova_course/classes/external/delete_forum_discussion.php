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

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint: delete a whole forum discussion (Teacher/Admin only).
 *
 * Focus mode's forum thread list confirms deletion inline rather than sending
 * the user to Moodle's confirmation page. Core's own mod_forum_delete_post is
 * not exposed to web AJAX (it is registered for the mobile service only), so
 * this thin wrapper provides the same behaviour for the browser: it performs no
 * deletion of its own, it validates and then hands off to mod_forum's
 * forum_delete_discussion(), which owns the cascade, the completion update and
 * the events.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_forum_discussion extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'Id of the forum discussion to delete.'),
        ]);
    }

    /**
     * @param int $discussionid
     * @return array
     */
    public static function execute(int $discussionid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['discussionid' => $discussionid]);

        $discussion = $DB->get_record('forum_discussions', ['id' => $params['discussionid']], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Same capability mod_forum requires to remove someone else's thread.
        require_capability('mod/forum:deleteanypost', $context);

        forum_delete_discussion($discussion, false, $course, $cm, $forum);

        return ['status' => true, 'discussionid' => $params['discussionid']];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the discussion was deleted.'),
            'discussionid' => new external_value(PARAM_INT, 'The deleted discussion id.'),
        ]);
    }
}
