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

namespace local_learnova_course;

defined('MOODLE_INTERNAL') || die();

use context_course;
use moodle_url;
use stdClass;

/**
 * Data builders for the two "My Access" panels — the tools that act on the
 * viewer's OWN participation rather than on the course.
 *
 *  - Unenrol me: the self-unenrol links the enrolment plugins themselves offer
 *    ({@see \enrol_plugin::get_unenrolself_link()}), so a method that does not
 *    allow it simply has no button, exactly as on the course page.
 *  - Reset my completion: the activities the viewer has marked complete BY HAND.
 *    Only manual completion can be undone by a learner — automatic completion is
 *    derived from grades, submissions and views, so clearing it would just be
 *    recalculated. Unticking goes through the native
 *    core_completion_update_activity_completion_status_manually web service.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_access {

    /**
     * "Unenrol me" panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function unenrol_context(stdClass $course, context_course $context): array {
        $methods = [];
        foreach (enrol_get_instances($course->id, true) as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            if (!$plugin) {
                continue;
            }
            $link = $plugin->get_unenrolself_link($instance);
            if (!$link) {
                continue;
            }
            $methods[] = [
                'name' => $plugin->get_instance_name($instance),
                'url'  => $link->out(false),
            ];
        }

        return [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'methods'    => $methods,
            'hasmethods' => !empty($methods),
        ];
    }

    /**
     * "Reset my completion" panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function completion_context(stdClass $course, context_course $context): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return ['enabled' => false, 'hasrows' => false];
        }

        $rows = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            // Manual only: automatic completion is derived from the learner's
            // work, so "resetting" it would be undone on the next recalculation.
            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_MANUAL) {
                continue;
            }
            $data = $completion->get_data($cm, false, $USER->id);
            if ((int) $data->completionstate === COMPLETION_INCOMPLETE) {
                continue;
            }
            $rows[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'url'  => $cm->url ? $cm->url->out(false) : '',
                'hasurl' => (bool) $cm->url,
            ];
        }

        return [
            'enabled' => true,
            'rows'    => $rows,
            'hasrows' => !empty($rows),
        ];
    }
}
