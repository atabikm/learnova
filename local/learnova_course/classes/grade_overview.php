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
use grade_grade;
use grade_item;
use moodle_url;
use stdClass;

/**
 * Data builder for the Course Management "Grade Overview" panel.
 *
 * The learner-facing grade report for a single user, rendered as a custom
 * Learnova layout (not the native user report): activity rows grouped under
 * their course-section header, each showing the module type, name, percentage,
 * grade range, feedback and contribution to the course total, closed by a
 * "Course Total" row. All values come from the native gradebook
 * ({@see grade_item} / {@see grade_grade}).
 *
 * Teachers (moodle/grade:viewall) get a "Viewing as" selector to inspect any
 * gradable learner; a learner (grade:view only) always sees their own grades —
 * the requested user id is ignored for them, so the panel can never leak
 * another user's results.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_overview {

    /**
     * Build the grade overview panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param array $params  Panel state: 'gouser' (learner to view, teachers only).
     * @return array
     */
    public static function context(stdClass $course, context_course $context, array $params): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');

        $courseid = (int) $course->id;
        $viewall  = has_capability('moodle/grade:viewall', $context);

        // Stale gradebook: every read below would throw until it is recalculated.
        if (!grades::ensure_regraded($course)) {
            return [
                'regrading' => true,
                'viewall'   => $viewall,
                'pane'      => grades::PANE_VIEW,
                'gtab'      => grades::TAB_USER,
            ];
        }

        // Gradable learners (teachers pick from these; a learner only ever sees self).
        $gradables = $viewall ? get_gradable_users($courseid) : [];

        if ($viewall) {
            $requested = (int) ($params['gouser'] ?? 0);
            $userid = isset($gradables[$requested]) ? $requested : (int) (array_key_first($gradables) ?? 0);
        } else {
            $userid = (int) $USER->id;
        }

        // No one to grade (empty teacher course).
        if ($viewall && !$userid) {
            return [
                'nolearners' => true,
                'viewall'    => true,
            ];
        }

        [$sections, $hasitems] = self::sections($course, $context, $userid);
        $total = self::course_total($courseid, $userid);

        // The panel is a tab of "Grades View", so its own reloads must land
        // back on that panel with this tab selected.
        $base = new moodle_url('/local/learnova_course/management.php',
            ['id' => $courseid, 'pane' => grades::PANE_VIEW, 'gtab' => grades::TAB_USER]);

        return [
            'pane'        => grades::PANE_VIEW,
            'gtab'        => grades::TAB_USER,
            'viewall'     => $viewall,
            'formaction'  => $base->out(false),
            'courseid'    => $courseid,
            'useroptions' => $viewall ? self::user_options($gradables, $userid) : [],
            'sections'    => $sections,
            'hasitems'    => $hasitems,
            'total'       => $total,
        ];
    }

    /**
     * Activity rows for one user, grouped by course section (in course order).
     *
     * Only graded activity items are shown (itemtype 'mod', a real grade type).
     * A section with no such items is skipped, matching the native report's
     * "graded activities only" view.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param int $userid
     * @return array{0: array, 1: bool}  [sections, hasitems]
     */
    private static function sections(stdClass $course, context_course $context, int $userid): array {
        $courseid = (int) $course->id;
        $modinfo  = get_fast_modinfo($course);

        // Map "modname:instance" → the activity grade items for that module.
        $itemsbymod = [];
        foreach (grade_item::fetch_all(['courseid' => $courseid]) as $item) {
            if ($item->itemtype !== 'mod' || $item->gradetype == GRADE_TYPE_NONE) {
                continue;
            }
            $itemsbymod[$item->itemmodule . ':' . $item->iteminstance][$item->itemnumber] = $item;
        }

        $sections = [];
        $hasitems = false;
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            $cmids = $modinfo->sections[$sectioninfo->section] ?? [];
            $rows  = [];
            foreach ($cmids as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible) {
                    continue;
                }
                $moditems = $itemsbymod[$cm->modname . ':' . $cm->instance] ?? [];
                ksort($moditems);
                foreach ($moditems as $item) {
                    $rows[] = self::row($item, $cm, $userid);
                }
            }
            if (!empty($rows)) {
                $hasitems = true;
                $sections[] = [
                    'name' => get_section_name($course, $sectioninfo),
                    'rows' => $rows,
                ];
            }
        }
        return [$sections, $hasitems];
    }

    /**
     * One activity row: type badge + name + percentage + range + feedback +
     * contribution to the course total.
     *
     * @param grade_item $item
     * @param \cm_info $cm
     * @param int $userid
     * @return array
     */
    private static function row(grade_item $item, \cm_info $cm, int $userid): array {
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $final = ($grade && $grade->finalgrade !== null) ? (float) $grade->finalgrade : null;
        $empty = $final === null;

        // Percentage (the headline figure); "(Empty)" when ungraded.
        $pct = $empty
            ? get_string('go_empty', 'local_learnova_course')
            : grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, 2);

        // min–max in the item's own scale, e.g. "0–100".
        $min = grade_format_gradevalue($item->grademin, $item, true, GRADE_DISPLAY_TYPE_REAL, 0);
        $max = grade_format_gradevalue($item->grademax, $item, true, GRADE_DISPLAY_TYPE_REAL, 0);

        return [
            'typeicon'   => self::type_icon($cm->modname),
            'typelabel'  => get_string('modulename', $cm->modname),
            'name'       => format_string($item->get_name()),
            'percent'    => $pct,
            'empty'      => $empty,
            'range'      => $min . '–' . $max,
            'feedback'   => self::feedback($grade),
            'weight'     => self::contribution($grade, $item, $final, $empty),
        ];
    }

    /**
     * The user's course-total figure: "value / max · percent" (or null if the
     * course has no total item / no grade).
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null
     */
    private static function course_total(int $courseid, int $userid): ?array {
        $item = grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course']);
        if (!$item) {
            return null;
        }
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $final = ($grade && $grade->finalgrade !== null) ? (float) $grade->finalgrade : null;
        $empty = $final === null;

        $value = $empty
            ? get_string('go_empty', 'local_learnova_course')
            : grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_REAL, 2);
        $max = grade_format_gradevalue($item->grademax, $item, true, GRADE_DISPLAY_TYPE_REAL, 0);
        $pct = $empty ? '' : grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, 2);

        return ['value' => $value, 'max' => $max, 'percent' => $pct, 'empty' => $empty];
    }

    /**
     * Contribution to the course total for one grade, as a percentage — matching
     * Moodle's native user report (grade/report/user, fill_contributions_column):
     * normalise the grade (by grademax for Natural/SUM, by the grademin–grademax
     * span otherwise), scale by the effective aggregation weight, ×100. '—' when
     * the grade is empty or the item carries no weight.
     *
     * This mirrors the single-level (item → course) case; deeply nested grade
     * categories would additionally fold in each parent category's weight.
     *
     * @param grade_grade|false $grade
     * @param grade_item $item
     * @param float|null $final
     * @param bool $empty
     * @return string
     */
    private static function contribution($grade, grade_item $item, ?float $final, bool $empty): string {
        $dash = '—';
        if ($empty || !$grade || $grade->aggregationweight === null) {
            return $dash;
        }

        $cat = $item->load_parent_category();
        if ($cat && $cat->aggregation == GRADE_AGGREGATE_SUM) {
            $range = (float) $item->grademax;
            $val = $range != 0 ? ($final / $range) : 0.0;
        } else {
            $val = \grade_grade::standardise_score($final, $item->grademin, $item->grademax, 0, 1);
        }
        $val *= (float) $grade->aggregationweight;

        return format_float($val * 100, 2) . '%';
    }

    /**
     * Plain, shortened feedback text for the narrow feedback column ('—' none).
     *
     * @param grade_grade|false $grade
     * @return string
     */
    private static function feedback($grade): string {
        if (!$grade || trim((string) $grade->feedback) === '') {
            return '—';
        }
        $text = content_to_text($grade->feedback, $grade->feedbackformat);
        return shorten_text(trim($text), 60);
    }

    /**
     * A small tone class (mock's quiz/assign/scorm colours) for the module type
     * icon square, so the type is glanceable without a per-module SVG.
     *
     * @param string $modname
     * @return string One of: quiz, assign, scorm, resource, default.
     */
    private static function type_icon(string $modname): string {
        static $map = [
            'quiz' => 'quiz', 'assign' => 'assign', 'scorm' => 'scorm',
            'workshop' => 'assign', 'lesson' => 'quiz', 'forum' => 'scorm',
            'resource' => 'resource', 'url' => 'resource', 'page' => 'resource',
        ];
        return $map[$modname] ?? 'default';
    }

    /**
     * "Viewing as" select options (gradable learners, name-sorted), current one
     * flagged. Each keeps the caller's full name so the label reads naturally.
     *
     * @param array<int, stdClass> $gradables  From get_gradable_users().
     * @param int $selected
     * @return array<int, array{value: int, label: string, selected: bool}>
     */
    private static function user_options(array $gradables, int $selected): array {
        $named = [];
        foreach ($gradables as $u) {
            $named[$u->id] = fullname($u);
        }
        \core_collator::asort($named);

        $options = [];
        foreach ($named as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name, 'selected' => $id === $selected];
        }
        return $options;
    }
}
