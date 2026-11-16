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
use navigation_node;
use stdClass;

/**
 * Data builder for the Course Management "Reports" panel.
 *
 * The panel is a sub-tab bar (the shared .lm-subtabs control) over one embedded
 * frame: each tab is a course report — Competency breakdown, Logs, Live logs,
 * Activity report, Course participation, Activity completion — and clicking it
 * loads that report's real Moodle page inside the panel, chrome-less
 * (?clembed=1, see the theme's drawers layout) so only the report itself shows.
 *
 * The tab list is not hardcoded: it is built from the same
 * report_*_extend_navigation_course() callbacks that populate Moodle's own
 * course Reports menu, so the panel shows exactly the reports this viewer may
 * see — each plugin does its own capability and precondition checks (e.g.
 * Activity completion only appears when completion tracking is on), and any
 * report plugin added later shows up without a code change here.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports {

    /**
     * Build the reports panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        $tabs  = [];
        $first = true;

        foreach (self::nodes($course, $context) as $node) {
            $url = $node->action;
            if (!($url instanceof moodle_url)) {
                continue; // Not a plain link (nothing to embed).
            }
            // Chrome-less render of the report page inside the panel frame.
            $url = new moodle_url($url);
            $url->param('clembed', 1);

            $tabs[] = [
                'key'    => self::key($url),
                'label'  => (string) $node->text,
                'url'    => $url->out(false),
                'active' => $first,
            ];
            $first = false;
        }

        return [
            'frametitle' => get_string('management_menu_reports', 'local_learnova_course'),
            'tabs'       => $tabs,
            'hastabs'    => !empty($tabs),
            // Every tab here is an embed tab, so the shared frame is always on.
            'hasembed'   => !empty($tabs),
        ];
    }

    /**
     * The course report navigation nodes visible to the current user.
     *
     * Runs the report plugins' own course-navigation callbacks against a
     * throwaway parent node and collects what they added — the same list, in
     * the same order, as Moodle's course Reports menu.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return navigation_node[]
     */
    private static function nodes(stdClass $course, context_course $context): array {
        $parent = navigation_node::create('learnova-reports');

        foreach (get_plugin_list_with_function('report', 'extend_navigation_course', 'lib.php') as $function) {
            $function($parent, $course, $context);
        }

        $nodes = [];
        foreach ($parent->children as $child) {
            $nodes[] = $child;
        }
        return $nodes;
    }

    /**
     * Tab key for a report URL — the report plugin's directory name
     * (/report/loglive/index.php → "loglive"), used as the data-attribute that
     * ties a tab button to the frame it loads.
     *
     * @param moodle_url $url
     * @return string
     */
    private static function key(moodle_url $url): string {
        if (preg_match('#/report/([a-z0-9_]+)/#', $url->out_omit_querystring(), $m)) {
            return $m[1];
        }
        return 'report';
    }
}
