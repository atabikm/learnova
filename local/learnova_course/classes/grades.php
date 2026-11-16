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

use moodle_url;
use stdClass;

/**
 * Gradebook support for the Course Management grade panels.
 *
 * Two jobs:
 *  - the regrade precondition every grade read needs ({@see self::ensure_regraded()});
 *  - the tab lists for the "Grades View" and "Grades Setup" panels.
 *
 * The tabs are not a hand-written list: they come from grade_get_plugin_info(),
 * the same call that builds Moodle's own gradebook dropdown — its 'report' group
 * is the dropdown's "View" section (Grader report, Grade history, Overview
 * report, Single view, Grade summary, User report) and its 'settings' group is
 * the "Setup" section (Gradebook setup, Course grade settings, Preferences).
 * So the panels show exactly what this viewer would get natively, including any
 * gradebook plugin added later, and never a tab they may not open.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades {

    /** @var string Panel key both grade tabs live in. */
    const PANE_VIEW = 'grades-view';

    /** @var string Tab key of our own grader report (replaces the native one). */
    const TAB_GRADER = 'report-grader';

    /** @var string Tab key of our own per-learner report (replaces "User report"). */
    const TAB_USER = 'report-user';

    /** @var string Tab key of our own cross-course report (replaces "Overview report"). */
    const TAB_OVERVIEW = 'report-overview';

    /** @var string Tab key of our own class-level averages (replaces "Grade summary"). */
    const TAB_SUMMARY = 'report-summary';

    /**
     * Tabs for the "Grades View" panel — the dropdown's View section.
     *
     * Four of the six are ours rather than embedded — Grader report, Overview
     * report, Grade summary and User report — built straight from the gradebook
     * and rendered in place with the lm-* tables instead of an iframe. The other
     * two stay native on purpose: Single view is a grade EDITOR (inline editing,
     * bulk insert, override/exclude semantics) and Grade history carries filter
     * machinery we would only be duplicating.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param array $params  Panel state, forwarded to the local tabs.
     * @return array
     */
    public static function view_context(stdClass $course, \context_course $context, array $params): array {
        global $PAGE;
        $output = $PAGE->get_renderer('core');

        $data = self::tabs($course, 'report',
            get_string('management_menu_gradesview', 'local_learnova_course'));

        // Swap the two native tabs for our own panels, in place — the tab order
        // stays the one Moodle's own dropdown uses.
        $local = [
            self::TAB_GRADER => fn() => $output->render_from_template(
                'local_learnova_course/grader_report',
                grader_report::context($course, $context, $params)),
            self::TAB_USER => fn() => $output->render_from_template(
                'local_learnova_course/grade_overview',
                grade_overview::context($course, $context, $params)),
            self::TAB_OVERVIEW => fn() => $output->render_from_template(
                'local_learnova_course/overview_report',
                overview_report::context($course, $context, $params)),
            self::TAB_SUMMARY => fn() => $output->render_from_template(
                'local_learnova_course/grade_summary',
                grade_summary::context($course, $context)),
        ];

        $requested = (string) ($params['gtab'] ?? '');
        $hasembed  = false;
        foreach ($data['tabs'] as &$tab) {
            if (isset($local[$tab['key']])) {
                $tab['islocal'] = true;
                $tab['html']    = $local[$tab['key']]();
                unset($tab['url']);
            } else {
                $hasembed = true;
            }
            $tab['active'] = false;
        }
        unset($tab);

        self::select_tab($data['tabs'], $requested);
        $data['hasembed'] = $hasembed;
        $data['activeislocal'] = false;
        foreach ($data['tabs'] as $tab) {
            if (!empty($tab['active'])) {
                $data['activeislocal'] = !empty($tab['islocal']);
            }
        }
        return $data;
    }

    /**
     * Mark the open tab: the requested one when it exists, else the first.
     *
     * @param array $tabs  Passed by reference.
     * @param string $requested
     * @return void
     */
    private static function select_tab(array &$tabs, string $requested): void {
        foreach ($tabs as &$tab) {
            if ($tab['key'] === $requested) {
                $tab['active'] = true;
                return;
            }
        }
        unset($tab);
        if (!empty($tabs)) {
            $tabs[0]['active'] = true;
        }
    }

    /**
     * Tabs for the "Grades Setup" panel — the dropdown's Setup section AND its
     * "More" section, which is where Moodle files the rest of the gradebook
     * configuration: scales, outcomes, grade letters, import and export.
     *
     * @param stdClass $course
     * @return array
     */
    public static function setup_context(stdClass $course): array {
        $data = self::tabs($course, 'settings',
            get_string('management_menu_gradessetup', 'local_learnova_course'));

        $data['tabs'] = array_merge($data['tabs'], self::more_tabs($course));
        $data['hastabs'] = !empty($data['tabs']);
        $data['hasembed'] = $data['hastabs'];
        self::select_tab($data['tabs'], '');
        return $data;
    }

    /**
     * The dropdown's "More" section as tabs.
     *
     * Mirrors \core_grades\output\general_action_bar exactly, including its
     * quirks: of the scale / outcome / letter plugin groups only one entry each
     * is a menu item (the rest are edit screens reached from it), and import and
     * export collapse to their own chooser page rather than one tab per format.
     *
     * @param stdClass $course
     * @return array
     */
    private static function more_tabs(stdClass $course): array {
        global $CFG;
        require_once($CFG->dirroot . '/grade/lib.php');

        $courseid = (int) $course->id;
        $info     = grade_get_plugin_info($courseid, 'settings', '');
        $tabs     = [];
        $seen     = [];

        foreach ($info as $type => $plugins) {
            if ($type === 'strings' || !empty($plugins->id)) {
                continue;
            }
            foreach ((array) $plugins as $key => $plugin) {
                switch ($type) {
                    case 'scale':
                        if ($key !== 'view') {
                            continue 2;
                        }
                        $label = get_string('scales');
                        $url   = $plugin->link;
                        break;
                    case 'outcome':
                        if ($key !== 'course') {
                            continue 2;
                        }
                        $label = get_string('outcomes', 'grades');
                        $url   = $plugin->link;
                        break;
                    case 'letter':
                        if ($key !== 'view') {
                            continue 2;
                        }
                        $label = get_string('gradeletters', 'grades');
                        $url   = $plugin->link;
                        break;
                    case 'import':
                        $label = get_string('import', 'grades');
                        $url   = new moodle_url('/grade/import/index.php', ['id' => $courseid]);
                        break;
                    case 'export':
                        $label = get_string('export', 'grades');
                        $url   = new moodle_url('/grade/export/index.php', ['id' => $courseid]);
                        break;
                    default:
                        continue 2;
                }
                if (isset($seen[$type]) || empty($url)) {
                    continue 2; // Import/export contribute one tab, not one per format.
                }
                $seen[$type] = true;

                $url = new moodle_url($url);
                $url->param('clembed', 1);
                $tabs[] = [
                    'key'    => 'more-' . $type,
                    'label'  => $label,
                    'url'    => $url->out(false),
                    'active' => false,
                ];
            }
        }
        return $tabs;
    }

    /**
     * Build one group of the native gradebook dropdown as tabframe tabs.
     *
     * @param stdClass $course
     * @param string $group  Key in grade_get_plugin_info(): 'report' or 'settings'.
     * @param string $frametitle
     * @return array
     */
    private static function tabs(stdClass $course, string $group, string $frametitle): array {
        global $CFG;
        require_once($CFG->dirroot . '/grade/lib.php');

        $info = grade_get_plugin_info($course->id, $group, '');
        $tabs = [];
        $first = true;

        foreach ((array) ($info[$group] ?? []) as $plugin) {
            if (!($plugin instanceof \grade_plugin_info) || empty($plugin->link)) {
                continue;
            }
            // Chrome-less render of the gradebook page inside the panel frame.
            $url = new moodle_url($plugin->link);
            $url->param('clembed', 1);

            $tabs[] = [
                'key'    => $group . '-' . $plugin->id,
                'label'  => (string) $plugin->string,
                'url'    => $url->out(false),
                'active' => $first,
            ];
            $first = false;
        }

        return [
            'frametitle' => $frametitle,
            'tabs'       => $tabs,
            'hastabs'    => !empty($tabs),
            'hasembed'   => !empty($tabs),
        ];
    }

    /**
     * Make sure the course's final grades are up to date before reading them.
     *
     * A course whose gradebook is flagged "needs regrade" (an activity added,
     * deleted or regraded since the last calculation) makes EVERY grade read
     * fail: graded_users_iterator::init() and get_gradable_users() both call
     * export_verify_grades(), which throws grades/gradesneedregrading. Moodle's
     * own grade report pages avoid that by regrading first
     * (grade_regrade_final_grades_if_required); the panels must do the same.
     *
     * On a course big enough to need a progress bar, core only QUEUES the
     * regrade and returns — the grades stay stale until the ad-hoc task runs, so
     * this reports false and the panel shows a "being recalculated" state rather
     * than taking the whole management page down with an exception.
     *
     * @param stdClass $course
     * @return bool True when the grades are ready to read.
     */
    public static function ensure_regraded(stdClass $course): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        if (!grade_needs_regrade_final_grades($course->id)) {
            return true;
        }
        grade_regrade_final_grades_if_required($course);
        return !grade_needs_regrade_final_grades($course->id);
    }
}
