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
use stdClass;

/**
 * Data builder for the "Grade summary" tab of the Grades View panel.
 *
 * One row per graded activity: its type, its name and the course-wide average
 * of that item — the class-level view, where the Grader report is the
 * per-learner one.
 *
 * The averages are the ones the Grader report already computes
 * ({@see grader_report::build()}) rather than a second calculation, so the
 * number in this tab and the "Overall average" row in the Grader report tab can
 * never disagree. Like there, an average is over graded values only; ungraded
 * learners are not counted as zero.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_summary {

    /**
     * Build the grade summary tab context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        // Stale gradebook: every read below would throw until it is recalculated.
        if (!grades::ensure_regraded($course)) {
            return ['regrading' => true, 'hasrows' => false];
        }

        $data  = grader_report::build($course, '');
        $types = self::item_types($course);

        $rows = [];
        foreach ($data['columns'] as $i => $column) {
            $cell = $data['averages']['cells'][$i] ?? ['display' => '', 'na' => true];
            $rows[] = [
                'type'    => $types[$column['itemid']] ?? get_string('gs_type_manual', 'local_learnova_course'),
                'name'    => $column['name'],
                'average' => $cell['display'],
                'empty'   => !empty($cell['na']),
            ];
        }

        return [
            'rows'     => $rows,
            'hasrows'  => !empty($rows),
            'total'    => $data['averages']['total'],
            'hastotal' => !empty($data['averages']['total']),
        ];
    }

    /**
     * Activity-type label per grade item id ("Assignment", "Quiz", …), for the
     * items that belong to a module. Manual items keep the generic label.
     *
     * @param stdClass $course
     * @return array<int, string>
     */
    private static function item_types(stdClass $course): array {
        $types = [];
        foreach (\grade_item::fetch_all(['courseid' => (int) $course->id]) as $item) {
            if ($item->itemtype === 'mod' && $item->itemmodule) {
                $types[(int) $item->id] = get_string('modulename', $item->itemmodule);
            }
        }
        return $types;
    }
}
