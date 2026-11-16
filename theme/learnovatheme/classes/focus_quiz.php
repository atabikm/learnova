<?php
// This file is part of the Learnova theme.

/**
 * Quiz-mode content data for focus mode.
 *
 * Quiz keeps mod_quiz's own attempt machinery end to end: the question engine
 * renders the questions, the quiz's own page-break settings decide how many
 * land on a page, and Previous/Next page are still the form's submit buttons
 * that post to processattempt.php. Flagging, "Clear my choice" and answer
 * saving are all core behaviour and are not reimplemented anywhere here.
 *
 * The only thing this class contributes is the progress indicator above the
 * questions — a read-only chart of where the learner is in the attempt, which
 * stands in for the navigation block that the focus layout has no region for.
 *
 * @package    theme_learnovatheme
 */

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\quiz_attempt;
use moodle_page;

/**
 * Template context for the quiz pages inside the focus chrome.
 */
class focus_quiz {

    /**
     * Quiz pages that travel inside the focus chrome. The attempt is the one
     * the layout is designed around; summary and review are the two pages the
     * attempt hands over to, so leaving them out would drop the learner out of
     * the chrome halfway through submitting.
     */
    public const PAGETYPES = ['mod-quiz-attempt', 'mod-quiz-summary', 'mod-quiz-review'];

    /**
     * Question state classes that mean nothing has been answered yet.
     *
     * Read as the complement: any other state (complete, needsgrading, and the
     * graded ones on review) means the learner has put an answer in.
     */
    private const UNANSWERED = ['notyetanswered', 'notanswered', 'invalidanswer'];

    /**
     * Whether this page gets the quiz treatment.
     *
     * @param moodle_page $page
     * @return bool
     */
    public static function applies(moodle_page $page): bool {
        return in_array((string) $page->pagetype, self::PAGETYPES, true);
    }

    /**
     * Quiz-mode context.
     *
     * @param moodle_page $page
     * @return array Template context. 'hasquizprogress' is false whenever the
     *               indicator cannot be built, in which case the questions
     *               simply render without it.
     */
    public static function context(moodle_page $page): array {
        $ctx = [
            'isquiz'          => true,
            'hasquizprogress' => false,
        ];

        // Only a live attempt has a page sequence to chart. Summary and review
        // show the attempt as a whole, so a "page x of y" there would be a lie.
        if ((string) $page->pagetype !== 'mod-quiz-attempt') {
            return $ctx;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return $ctx;
        }

        try {
            $attemptobj = quiz_attempt::create($attemptid);
        } catch (\moodle_exception $e) {
            // attempt.php has already loaded and vetted this attempt before the
            // layout runs, so this only fires for a page we cannot chart —
            // drop the indicator rather than take the whole page down with it.
            return $ctx;
        }

        $currentpage = $attemptobj->force_page_number_into_range(optional_param('page', 0, PARAM_INT));
        $currentslots = $attemptobj->get_slots($currentpage);

        $dots = [];
        foreach ($attemptobj->get_slots('all') as $slot) {
            // Description "questions" hold no answer, so they are not steps.
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }

            $oncurrentpage = in_array($slot, $currentslots, false);
            // Done means "has an answer in", not "is right" — correctness is
            // not shown during an attempt, so it must not colour the dots.
            $answered = !in_array($attemptobj->get_question_state_class($slot, false), self::UNANSWERED, true);

            $dots[] = [
                'iscurrent' => $oncurrentpage,
                'isdone'    => $answered && !$oncurrentpage,
                'number'    => $attemptobj->get_question_number($slot),
            ];
        }

        if (empty($dots)) {
            return $ctx;
        }

        return [
            'isquiz'          => true,
            'hasquizprogress' => true,
            'quizdots'        => $dots,
            'quizpagelabel'   => get_string('fm_quiz_pagelabel', 'theme_learnovatheme', (object) [
                'page'  => $currentpage + 1,
                'total' => $attemptobj->get_num_pages(),
            ]),
        ];
    }
}
