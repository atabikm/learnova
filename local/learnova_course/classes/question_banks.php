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
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_edit_contexts;
use moodle_url;
use stdClass;

/**
 * Data builder for the Course Management "Question Banks" panel.
 *
 * Since Moodle 5.0 a question bank is an ACTIVITY, not a property of the
 * course: shared banks are mod_qbank instances, and an activity that keeps its
 * own questions (a quiz) carries a private one. There is no longer a single
 * "the course question bank", and /question/edit.php?courseid=… redirects to
 * the bank list. So this panel is two layers:
 *
 *  1. our own card listing the banks this course has — name, whether it is
 *     shared or private, how many categories and questions it holds;
 *  2. the REAL native bank page for whichever bank is open, embedded
 *     chrome-less. That page is where categories are managed, where the
 *     multi-condition filter with its explicit Apply lives, and where questions
 *     are created — all of it Moodle's own, including the question-type chooser
 *     that a five-field form could never replace.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_banks {

    /**
     * Build the question banks panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param array $params  Panel state: 'qbank' (cmid of the open bank).
     * @return array
     */
    public static function context(stdClass $course, context_course $context, array $params): array {
        $courseid = (int) $course->id;
        $caps = array_merge(question_edit_contexts::$caps['editq'], question_edit_contexts::$caps['categories']);

        // Both flavours, in one list: shared banks (mod_qbank) first, then the
        // activities holding their own questions.
        $banks = [];
        foreach ([true, false] as $shared) {
            $found = $shared
                ? question_bank_helper::get_activity_instances_with_shareable_questions([$courseid], [], $caps)
                : question_bank_helper::get_activity_instances_with_private_questions([$courseid], [], $caps);
            foreach ($found as $bank) {
                $banks[] = [$bank->cminfo, $shared];
            }
        }

        $counts   = self::counts(array_map(fn($b) => (int) $b[0]->context->id, $banks));
        $selected = (int) ($params['qbank'] ?? 0);
        $rows     = [];
        $openbank = null;

        foreach ($banks as [$cm, $shared]) {
            $ctxid  = (int) $cm->context->id;
            $isopen = ($selected === (int) $cm->id);
            if ($isopen) {
                $openbank = $cm;
            }
            // Every row carries what opening it needs, so "Manage" swaps the
            // frame in place instead of reloading the whole management page.
            $new = self::newquestion($cm);
            $rows[] = [
                'name'       => $cm->get_formatted_name(),
                'shared'     => $shared,
                'typelabel'  => $shared
                    ? get_string('qb_type_shared', 'local_learnova_course')
                    : get_string('qb_type_private', 'local_learnova_course'),
                'categories' => $counts[$ctxid]['categories'] ?? 0,
                'questions'  => $counts[$ctxid]['questions'] ?? 0,
                'cmid'       => (int) $cm->id,
                'frameurl'   => (new moodle_url('/question/edit.php',
                    ['cmid' => $cm->id, 'clembed' => 1]))->out(false),
                'formurl'    => $new['formurl'] ?? '',
                'isopen'     => $isopen,
            ];
        }

        // Adding a bank is adding a mod_qbank activity — the native module form,
        // opened in the same slide-in as the question form.
        $addurl = new moodle_url('/course/modedit.php', [
            'add' => 'qbank', 'course' => $courseid, 'section' => 0, 'return' => 0, 'clembed' => 1,
        ]);
        $open = $openbank ? self::newquestion($openbank) : [];

        return [
            'rows'     => $rows,
            'hasrows'  => !empty($rows),
            'canadd'   => has_capability('moodle/course:manageactivities', $context),
            'addurl'   => $addurl->out(false),
            'hasopen'  => (bool) $openbank,
            'openname' => $openbank ? $openbank->get_formatted_name() : '',
            'frameurl' => $openbank
                ? (new moodle_url('/question/edit.php', ['cmid' => $openbank->id, 'clembed' => 1]))->out(false)
                : '',
            'formurl'  => $open['formurl'] ?? '',
            'qtypes'   => self::qtypes(),
        ];
    }

    /**
     * The creatable question types, as chooser tiles.
     *
     * @return array<int, array{name: string, label: string, icon: string}>
     */
    private static function qtypes(): array {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir . '/questionlib.php');

        $qtypes = [];
        foreach (\question_bank::get_creatable_qtypes() as $name => $qtype) {
            $qtypes[] = [
                'name'  => $name,
                'label' => $qtype->local_name(),
                'icon'  => $OUTPUT->image_url('icon', 'qtype_' . $name)->out(false),
            ];
        }
        return $qtypes;
    }

    /**
     * "Create a new question" slide-in data for the open bank: the creatable
     * question types, and the base URL of the NATIVE question form.
     *
     * The chooser is ours (a tile per type, like the course landing's add
     * activity panel); the form behind it is Moodle's own, so every question
     * type gets its real fields — a shared five-field form could only ever
     * produce a valid Essay.
     *
     * @param \cm_info $bank
     * @return array
     */
    private static function newquestion(\cm_info $bank): array {
        global $DB;

        $bankcontext = $bank->context;
        if (!has_capability('moodle/question:add', $bankcontext)) {
            return [];
        }

        // Default category of the bank: the first real one under "top". A bank
        // that has none yet cannot take a question — Moodle creates its default
        // category the first time the bank page is opened.
        $category = $DB->get_records_select('question_categories',
            'contextid = ? AND parent <> 0', [$bankcontext->id], 'sortorder, id', 'id', 0, 1);
        if (!$category) {
            return [];
        }

        // The form returns here when saved or cancelled; the panel JS watches
        // for that to close itself and refresh the bank behind it.
        $returnurl = new moodle_url('/question/edit.php', ['cmid' => $bank->id, 'clembed' => 1]);
        $formurl   = new moodle_url('/question/bank/editquestion/question.php', [
            'cmid'             => $bank->id,
            'category'         => (int) array_key_first($category),
            'appendqnumstring' => 'addquestion',
            'returnurl'        => $returnurl->out_as_local_url(false),
            'clembed'          => 1,
        ]);

        return ['formurl' => $formurl->out(false)];
    }

    /**
     * Category and question counts per bank context, in one pass.
     *
     * @param int[] $contextids
     * @return array<int, array{categories: int, questions: int}>
     */
    private static function counts(array $contextids): array {
        global $DB;
        if (empty($contextids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($contextids);

        $out = [];
        $cats = $DB->get_records_sql(
            "SELECT contextid, COUNT(1) AS n
               FROM {question_categories}
              WHERE contextid $insql
           GROUP BY contextid", $inparams);
        foreach ($cats as $row) {
            $out[(int) $row->contextid]['categories'] = (int) $row->n;
        }

        // Questions live under a category via their bank entry; drafts and
        // deleted entries are excluded by the category join itself.
        $questions = $DB->get_records_sql(
            "SELECT qc.contextid, COUNT(qbe.id) AS n
               FROM {question_categories} qc
               JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
              WHERE qc.contextid $insql
           GROUP BY qc.contextid", $inparams);
        foreach ($questions as $row) {
            $out[(int) $row->contextid]['questions'] = (int) $row->n;
        }

        return $out;
    }
}
