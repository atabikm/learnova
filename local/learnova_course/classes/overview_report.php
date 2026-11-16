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
use context_system;
use context_user;
use grade_report_overview;
use moodle_url;
use stdClass;

/**
 * Data builder for the "Overview report" tab of the Grades View panel.
 *
 * One learner's course-total grade in every course they are enrolled in, as a
 * Learnova table (course name / grade / rank), with the same "Viewing as"
 * learner selector as the other grade tabs.
 *
 * The rows come from the NATIVE report's own data method,
 * {@see grade_report_overview::setup_courses_data()}. That is deliberate: the
 * decision of which of another user's courses may be listed is a stack of
 * rules — course visibility, showgrades, grade:view vs grade:viewall vs
 * user:viewuseractivitiesreport, plus hidden-grade adjustment and the rank
 * setting. Re-deriving those here would risk showing grades from a course the
 * viewer may not see, so only the presentation is ours.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_report {

    /**
     * Build the overview report tab context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param array $params  Panel state: 'gouser' (learner to view, teachers only).
     * @return array
     */
    public static function context(stdClass $course, context_course $context, array $params): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/overview/lib.php');

        $courseid = (int) $course->id;
        $viewall  = has_capability('moodle/grade:viewall', $context);
        $base     = [
            'pane'    => grades::PANE_VIEW,
            'gtab'    => grades::TAB_OVERVIEW,
            'viewall' => $viewall,
        ];

        // Stale gradebook: every read below would throw until it is recalculated.
        if (!grades::ensure_regraded($course)) {
            return $base + ['regrading' => true];
        }

        $gradables = $viewall ? get_gradable_users($courseid) : [];
        if ($viewall) {
            $requested = (int) ($params['gouser'] ?? 0);
            $userid = isset($gradables[$requested]) ? $requested : (int) (array_key_first($gradables) ?? 0);
        } else {
            $userid = (int) $USER->id;
        }

        if (!$userid) {
            return $base + ['nolearners' => true];
        }

        // The native report's own gate on viewing this user's grades.
        if (!grade_report_overview::check_access(context_system::instance(), $context,
                context_user::instance($userid), $course, $userid)) {
            return $base + ['noaccess' => true];
        }

        $gpr = new \grade_plugin_return([
            'type' => 'report', 'plugin' => 'overview', 'courseid' => $courseid, 'userid' => $userid,
        ]);
        $report = new grade_report_overview($userid, $gpr, $context);

        $rows     = [];
        $showrank = false;
        foreach ($report->setup_courses_data(false) as $coursedata) {
            $c          = $coursedata['course'];
            $finalgrade = $coursedata['finalgrade'];
            $courseitem = $coursedata['courseitem'];
            $hasrank    = isset($coursedata['rank']);
            $showrank   = $showrank || $hasrank;

            $rows[] = [
                'name'    => format_string(get_course_display_name_for_list($c), true,
                    ['context' => $coursedata['context']]),
                'url'     => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                'grade'   => $finalgrade === null
                    ? get_string('or_none', 'local_learnova_course')
                    : grade_format_gradevalue($finalgrade, $courseitem, true),
                'empty'   => $finalgrade === null,
                'rank'    => $hasrank
                    ? $coursedata['rank'] . '/' . $coursedata['numusers']
                    : get_string('or_none', 'local_learnova_course'),
                'current' => ((int) $c->id === $courseid),
            ];
        }

        return $base + [
            'formaction'  => (new moodle_url('/local/learnova_course/management.php', [
                'id' => $courseid, 'pane' => grades::PANE_VIEW, 'gtab' => grades::TAB_OVERVIEW,
            ]))->out(false),
            'courseid'    => $courseid,
            'useroptions' => $viewall ? self::user_options($gradables, $userid) : [],
            'learner'     => fullname(\core_user::get_user($userid)),
            'initials'    => self::initials(\core_user::get_user($userid)),
            'rows'        => $rows,
            'hasrows'     => !empty($rows),
            'showrank'    => $showrank,
        ];
    }

    /**
     * "Viewing as" options: every gradable learner, current one selected.
     *
     * @param array $users
     * @param int $selected
     * @return array<int, array{value: int, label: string, selected: bool}>
     */
    private static function user_options(array $users, int $selected): array {
        $options = [];
        foreach ($users as $user) {
            $options[] = [
                'value'    => (int) $user->id,
                'label'    => fullname($user),
                'selected' => ((int) $user->id === $selected),
            ];
        }
        return $options;
    }

    /**
     * Two-letter uppercase initials from a user's name.
     *
     * @param stdClass $u
     * @return string
     */
    private static function initials(stdClass $u): string {
        $f = \core_text::strtoupper(\core_text::substr(trim($u->firstname), 0, 1));
        $l = \core_text::strtoupper(\core_text::substr(trim($u->lastname), 0, 1));
        return ($f . $l) ?: '?';
    }
}
