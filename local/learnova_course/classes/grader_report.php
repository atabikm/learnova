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
use grade_item;
use moodle_url;
use stdClass;

/**
 * Data builder for the Course Management "Grader Report" panel.
 *
 * The teacher-facing gradebook overview: every gradable learner as one row,
 * one column per graded activity plus the course total, in a horizontally
 * scrollable Learnova table. An "Overall average" row summarises each column,
 * and the current view can be exported to CSV. All values come from the native
 * gradebook, read in bulk via {@see \graded_users_iterator}.
 *
 * Guarded by moodle/grade:viewall (set on the menu item and re-checked before
 * the CSV export), so only graders reach it.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grader_report {

    /** @var int Learners shown before the "Show all" link (unless already all). */
    const LIMIT = 25;

    /**
     * Build the grader report panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param array $params  Panel state: 'grsearch' (name/email filter),
     *                        'grall' (1 = show every learner, no cap).
     * @return array
     */
    public static function context(stdClass $course, context_course $context, array $params): array {
        // Stale gradebook: every read below would throw until it is recalculated.
        if (!grades::ensure_regraded($course)) {
            return [
                'regrading' => true,
                'hasrows'   => false,
                'pane'      => grades::PANE_VIEW,
                'gtab'      => grades::TAB_GRADER,
            ];
        }

        $search  = trim($params['grsearch'] ?? '');
        $showall = !empty($params['grall']);

        $data = self::build($course, $search);
        $total = count($data['rows']);

        $rows = $showall ? $data['rows'] : array_slice($data['rows'], 0, self::LIMIT);
        $capped = !$showall && $total > self::LIMIT;

        // The panel is a tab of "Grades View", so its own reloads (search,
        // show-all, export) must land back on that panel with this tab selected.
        $base = new moodle_url('/local/learnova_course/management.php',
            ['id' => (int) $course->id, 'pane' => grades::PANE_VIEW, 'gtab' => grades::TAB_GRADER]);
        $exporturl = new moodle_url($base, ['grsearch' => $search, 'graderexport' => 1]);
        $showallurl = new moodle_url($base, ['grsearch' => $search, 'grall' => 1]);

        return [
            'pane'        => grades::PANE_VIEW,
            'gtab'        => grades::TAB_GRADER,
            'courseid'    => (int) $course->id,
            'formaction'  => $base->out(false),
            'exporturl'   => $exporturl->out(false),
            'search'      => $search,
            'columns'     => $data['columns'],
            'rows'        => $rows,
            'averages'    => $data['averages'],
            'hasrows'     => !empty($rows),
            'colspan'     => count($data['columns']) + 3, // name + email + activities + total
            'showing'     => get_string('gr_showing', 'local_learnova_course',
                (object) ['shown' => count($rows), 'total' => $total]),
            'capped'      => $capped,
            'showallurl'  => $showallurl->out(false),
        ];
    }

    /**
     * Stream the current view (all matching learners, no display cap) as a CSV
     * download and exit. Re-checks the grade:viewall capability first.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param array $params  Panel state ('grsearch').
     * @return void  Sends the file and terminates.
     */
    public static function export_csv(stdClass $course, context_course $context, array $params): void {
        global $CFG;
        require_capability('moodle/grade:viewall', $context);
        require_once($CFG->libdir . '/csvlib.class.php');

        // Exporting a stale gradebook would ship wrong numbers (and throw on the
        // way): send the user back to the panel, which explains the wait.
        if (!grades::ensure_regraded($course)) {
            redirect(new moodle_url('/local/learnova_course/management.php',
                ['id' => (int) $course->id, 'pane' => grades::PANE_VIEW, 'gtab' => grades::TAB_GRADER]));
        }

        $data = self::build($course, trim($params['grsearch'] ?? ''));

        $csv = new \csv_export_writer();
        $csv->set_filename('grader_report_' . $course->shortname);

        $header = [
            get_string('firstname'),
            get_string('lastname'),
            get_string('gr_col_email', 'local_learnova_course'),
        ];
        foreach ($data['columns'] as $col) {
            $header[] = $col['name'];
        }
        $header[] = get_string('gr_col_total', 'local_learnova_course');
        $csv->add_data($header);

        foreach ($data['rows'] as $row) {
            $line = [$row['firstname'], $row['lastname'], $row['email']];
            foreach ($row['cells'] as $cell) {
                $line[] = $cell['na'] ? '' : $cell['raw'];
            }
            $line[] = $row['total']['na'] ? '' : $row['total']['raw'];
            $csv->add_data($line);
        }

        $avgline = [get_string('gr_overall_avg', 'local_learnova_course'), '', ''];
        foreach ($data['averages']['cells'] as $cell) {
            $avgline[] = $cell['na'] ? '' : $cell['display'];
        }
        $avgline[] = $data['averages']['total']['na'] ? '' : $data['averages']['total']['display'];
        $csv->add_data($avgline);

        $csv->download_file();
        exit;
    }

    /**
     * Read the whole gradebook for the course in one pass and shape it into
     * columns + per-user rows + column averages.
     *
     * Columns are the graded activity items in gradebook order; the course
     * total is handled separately. Averages are over graded (non-empty) values
     * only, matching the native grader report.
     *
     * Public because the Grade summary tab reads the same averages from here
     * ({@see grade_summary}) — the two tabs must never show different numbers
     * for the same item.
     *
     * @param stdClass $course
     * @param string $search  Name/email substring filter (empty = all).
     * @return array{columns: array, rows: array, averages: array}
     */
    public static function build(stdClass $course, string $search): array {
        $courseid = (int) $course->id;

        // Ordered activity items (columns) + the course-total item.
        $moditems  = [];
        $courseitem = null;
        foreach (grade_item::fetch_all(['courseid' => $courseid]) as $item) {
            if ($item->itemtype === 'course') {
                $courseitem = $item;
            } else if ($item->itemtype === 'mod' && $item->gradetype != GRADE_TYPE_NONE) {
                $moditems[] = $item;
            }
        }
        usort($moditems, fn($a, $b) => $a->sortorder <=> $b->sortorder);

        $columns = [];
        foreach ($moditems as $item) {
            $columns[] = ['itemid' => (int) $item->id, 'name' => format_string($item->get_name())];
        }

        // The set of items to load grades for (columns + total, keyed by id so
        // the iterator hands us grade_grade objects for exactly these).
        $loaditems = [];
        foreach ($moditems as $item) {
            $loaditems[$item->id] = $item;
        }
        if ($courseitem) {
            $loaditems[$courseitem->id] = $courseitem;
        }

        // Running sums for the averages row (graded values only).
        $sums = array_fill_keys(array_keys($loaditems), ['sum' => 0.0, 'n' => 0]);

        $needle = \core_text::strtolower($search);
        $rows = [];

        $gui = new \graded_users_iterator($course, $loaditems);
        $gui->require_active_enrolment(false);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $user = $userdata->user;
            if ($needle !== '') {
                $hay = \core_text::strtolower(fullname($user) . ' ' . $user->email);
                if (strpos($hay, $needle) === false) {
                    continue;
                }
            }

            $cells = [];
            foreach ($moditems as $item) {
                $cells[] = self::cell($userdata->grades[$item->id] ?? null, $item, $sums);
            }
            $total = $courseitem
                ? self::cell($userdata->grades[$courseitem->id] ?? null, $courseitem, $sums)
                : ['display' => '—', 'na' => true, 'raw' => ''];

            $rows[] = [
                'userid'    => (int) $user->id,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'name'      => fullname($user),
                'initials'  => self::initials($user),
                'email'     => $user->email,
                'cells'     => $cells,
                'total'     => $total,
            ];
        }
        $gui->close();

        return [
            'columns'  => $columns,
            'rows'     => $rows,
            'averages' => self::averages($moditems, $courseitem, $sums),
        ];
    }

    /**
     * One grade cell: real value + a raw copy (for CSV) + an "empty" flag,
     * accumulating the value into the column sums for the averages row.
     *
     * @param \grade_grade|null $grade
     * @param grade_item $item
     * @param array $sums  By-reference running totals keyed by item id.
     * @return array{display: string, na: bool, raw: string}
     */
    private static function cell($grade, grade_item $item, array &$sums): array {
        $final = ($grade && $grade->finalgrade !== null) ? (float) $grade->finalgrade : null;
        if ($final === null) {
            return ['display' => '—', 'na' => true, 'raw' => ''];
        }
        $sums[$item->id]['sum'] += $final;
        $sums[$item->id]['n']++;
        return [
            'display' => grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_REAL, 2),
            'na'      => false,
            'raw'     => format_float($final, 2, false), // dot-decimal for CSV.
        ];
    }

    /**
     * Column averages (graded values only) as a cells + total structure that
     * mirrors a data row, so the template renders it the same way.
     *
     * @param grade_item[] $moditems
     * @param grade_item|null $courseitem
     * @param array $sums
     * @return array{cells: array, total: array}
     */
    private static function averages(array $moditems, ?grade_item $courseitem, array $sums): array {
        $avg = function (?grade_item $item) use ($sums): array {
            if (!$item || ($sums[$item->id]['n'] ?? 0) === 0) {
                return ['display' => '—', 'na' => true];
            }
            $mean = $sums[$item->id]['sum'] / $sums[$item->id]['n'];
            return ['display' => grade_format_gradevalue($mean, $item, true, GRADE_DISPLAY_TYPE_REAL, 2), 'na' => false];
        };

        $cells = [];
        foreach ($moditems as $item) {
            $cells[] = $avg($item);
        }
        return ['cells' => $cells, 'total' => $avg($courseitem)];
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
