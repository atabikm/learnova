<?php
// Learnova hero "Continue where you left off" card.
//
// Resolves the learner's last touched activity into the card shown on the right
// of the logged-in hero. Source of truth is the recently-accessed-items tracker
// (block_recentlyaccesseditems, whose observer records every module view), with
// a last-accessed-course fallback and a "start learning" empty state.

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * Resolver for the logged-in hero's continue card.
 */
class hero_continue {

    /**
     * Card context for the current user.
     *
     * @return array{
     *     card_label: string, card_course: string, card_meta: string,
     *     card_progress: int, card_hasprogress: bool,
     *     card_url: string, card_continue: string
     * }
     */
    public static function resolve(): array {
        $actcandidate = self::get_recent_activity_candidate();
        $coursecandidate = self::get_recent_course_candidate();

        // When a user accessed a course more recently than any single module view,
        // show that course's next resumable activity.
        if ($coursecandidate !== null) {
            if ($actcandidate === null || $coursecandidate['timeaccess'] > $actcandidate['timeaccess']) {
                $card = self::build_course_card($coursecandidate['course']);
                if ($card !== null) {
                    return $card;
                }
            }
        }

        if ($actcandidate !== null) {
            return self::build_activity_card($actcandidate['course'], $actcandidate['cm'], $actcandidate['modinfo']);
        }

        if ($coursecandidate !== null) {
            $card = self::build_course_card($coursecandidate['course']);
            if ($card !== null) {
                return $card;
            }
        }

        return self::empty_state();
    }

    /**
     * Finds the most recent activity candidate from block_recentlyaccesseditems.
     *
     * @return array{timeaccess: int, course: \stdClass, cm: \cm_info, modinfo: \course_modinfo}|null
     */
    private static function get_recent_activity_candidate(): ?array {
        global $USER;

        if (!class_exists('\block_recentlyaccesseditems\helper')) {
            return null;
        }

        $items = \block_recentlyaccesseditems\helper::get_recent_items(5);
        foreach ($items as $item) {
            try {
                $modinfo = get_fast_modinfo($item->courseid);
                $candidate = $modinfo->get_cm($item->cmid);
                if (in_array($candidate->modname, course_landing::EXCLUDED_MODS, true)) {
                    continue;
                }
                return [
                    'timeaccess' => (int) $item->timeaccess,
                    'course'     => $modinfo->get_course(),
                    'cm'         => $candidate,
                    'modinfo'    => $modinfo,
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Finds the most recent course candidate from user_lastaccess.
     *
     * @return array{timeaccess: int, course: \stdClass}|null
     */
    private static function get_recent_course_candidate(): ?array {
        global $USER, $DB;

        if (empty($USER->id) || isguestuser()) {
            return null;
        }

        $records = $DB->get_records_select(
            'user_lastaccess',
            'userid = ? AND courseid > 1',
            [$USER->id],
            'timeaccess DESC',
            'courseid, timeaccess',
            0,
            5
        );

        foreach ($records as $record) {
            try {
                $course = get_course($record->courseid);
            } catch (\Throwable $e) {
                continue;
            }
            if (!can_access_course($course, null, '', true)) {
                continue;
            }

            return [
                'timeaccess' => (int) $record->timeaccess,
                'course'     => $course,
            ];
        }

        return null;
    }

    /**
     * Card built from a specific activity.
     *
     * @param \stdClass $course
     * @param \cm_info $cm
     * @param \course_modinfo $modinfo
     * @return array
     */
    private static function build_activity_card($course, $cm, $modinfo): array {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        // Meta line: section name · position within that section · activity type.
        $parts = [];
        $section = $cm->get_section_info();
        if ($section) {
            $sectionname = html_entity_decode(get_section_name($course, $section), ENT_QUOTES, 'UTF-8');
            if ($sectionname !== '') {
                $parts[] = $sectionname;
            }
        }
        [$pos, $total] = self::position_in_section($modinfo, $cm);
        if ($pos > 0) {
            $parts[] = get_string('hero_card_activity_pos', 'theme_learnovatheme',
                (object) ['pos' => $pos, 'total' => $total]);
        }
        $parts[] = course_landing::activity_type_label($cm);

        // Continue lands on the course page, not the activity itself — the
        // learner picks up from the course landing.
        $url = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

        $percent = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        if ($percent === null) {
            $percent = self::calculate_course_progress($course, (int) $USER->id);
        }

        return [
            'card_label'      => get_string('hero_card_label', 'theme_learnovatheme'),
            'card_course'     => html_entity_decode(format_string($course->fullname), ENT_QUOTES, 'UTF-8'),
            'card_meta'       => implode(' · ', $parts),
            'card_progress'   => $percent === null ? 0 : (int) round($percent),
            'card_hasprogress' => $percent !== null,
            'card_url'        => $url,
            'card_continue'   => get_string('hero_card_continue', 'theme_learnovatheme'),
        ];
    }

    /**
     * Card built from a course: finds the first incomplete activity or last item.
     *
     * @param \stdClass $course
     * @return array|null
     */
    private static function build_course_card($course): ?array {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/lib/completionlib.php');

        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $excluded = course_landing::EXCLUDED_MODS;

        // 1. Try finding the last touched activity specifically in THIS course.
        $targetcm = null;
        if (class_exists('\block_recentlyaccesseditems\helper')) {
            $items = \block_recentlyaccesseditems\helper::get_recent_items(10);
            foreach ($items as $it) {
                if ((int) $it->courseid === (int) $course->id) {
                    try {
                        $candidate = $modinfo->get_cm($it->cmid);
                        if (!in_array($candidate->modname, $excluded, true) && $candidate->visible) {
                            $targetcm = $candidate;
                            break;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        // 2. If no recent item in this course, find the first incomplete activity.
        if ($targetcm === null) {
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress || in_array($cm->modname, $excluded, true) || !$cm->visible) {
                    continue;
                }
                $isdone = false;
                if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                    $data = $completion->get_data($cm, true, $USER->id);
                    $isdone = in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                }
                if (!$isdone) {
                    $targetcm = $cm;
                    break;
                }
            }
        }

        // 3. Fallback: take the first or last available activity in this course.
        if ($targetcm === null) {
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->deletioninprogress && !in_array($cm->modname, $excluded, true) && $cm->visible) {
                    $targetcm = $cm;
                    break;
                }
            }
        }

        if ($targetcm !== null) {
            return self::build_activity_card($course, $targetcm, $modinfo);
        }

        // Fallback when course has no learning activities.
        $percent = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        if ($percent === null) {
            $percent = self::calculate_course_progress($course, (int) $USER->id);
        }

        return [
            'card_label'      => get_string('hero_card_label', 'theme_learnovatheme'),
            'card_course'     => html_entity_decode(format_string($course->fullname), ENT_QUOTES, 'UTF-8'),
            'card_meta'       => '',
            'card_progress'   => $percent === null ? 0 : (int) round($percent),
            'card_hasprogress' => $percent !== null,
            'card_url'        => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'card_continue'   => get_string('hero_card_continue', 'theme_learnovatheme'),
        ];
    }

    /**
     * Fallback to calculate real progress of a course for unenrolled or admin users.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return int|null
     */
    private static function calculate_course_progress($course, int $userid): ?int {
        global $DB;
        if ($userid <= 0) {
            return null;
        }

        $quizzes = array_flip($DB->get_fieldset_sql("
            SELECT DISTINCT q.id FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = ? AND q.course = ? AND qa.state = 'finished'
        ", [$userid, $course->id]));

        $assigns = array_flip($DB->get_fieldset_sql("
            SELECT DISTINCT a.id FROM {assign_submission} s
            JOIN {assign} a ON a.id = s.assignment
            WHERE s.userid = ? AND a.course = ? AND s.status = 'submitted'
        ", [$userid, $course->id]));

        $views = array_flip($DB->get_fieldset_sql("
            SELECT DISTINCT contextinstanceid FROM {logstore_standard_log}
            WHERE userid = ? AND courseid = ? AND contextlevel = ? AND action = 'viewed'
        ", [$userid, $course->id, CONTEXT_MODULE]));

        $modinfo = get_fast_modinfo($course);
        require_once($GLOBALS['CFG']->dirroot . '/lib/completionlib.php');
        $completion = new \completion_info($course);

        $total = 0;
        $done = 0;
        $excluded = ['forum', 'chat', 'label', 'subsection'];

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress || in_array($cm->modname, $excluded, true) || !$cm->visible) {
                continue;
            }
            $total++;
            $isdone = false;
            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $data = $completion->get_data($cm, true, $userid);
                $isdone = in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
            }
            if (!$isdone) {
                if ($cm->modname === 'quiz' && isset($quizzes[$cm->instance])) {
                    $isdone = true;
                } else if ($cm->modname === 'assign' && isset($assigns[$cm->instance])) {
                    $isdone = true;
                } else if (in_array($cm->modname, ['page', 'resource', 'url', 'book', 'folder']) && isset($views[$cm->id])) {
                    $isdone = true;
                }
            }
            if ($isdone) {
                $done++;
            }
        }

        return $total > 0 ? (int) round(($done / $total) * 100) : 0;
    }

    /**
     * Card for a learner with no history at all — points at the catalogue.
     *
     * @return array
     */
    private static function empty_state(): array {
        return [
            'card_label'      => get_string('hero_card_empty_label', 'theme_learnovatheme'),
            'card_course'     => get_string('hero_card_empty_title', 'theme_learnovatheme'),
            'card_meta'       => get_string('hero_card_empty_meta', 'theme_learnovatheme'),
            'card_progress'   => 0,
            'card_hasprogress' => false,
            'card_url'        => (new moodle_url('/course/index.php'))->out(false),
            'card_continue'   => get_string('hero_card_browse', 'theme_learnovatheme'),
        ];
    }

    /**
     * Where the activity sits among the visible, openable activities of its
     * section ("Activity 2 of 5"). Labels and stealth items are skipped so the
     * count matches what the learner actually sees.
     *
     * @param \core_course\modinfo $modinfo
     * @param \core_course\cm_info $cm
     * @return array{0: int, 1: int}  Position (1-based, 0 when not found) and total.
     */
    private static function position_in_section($modinfo, $cm): array {
        $sections = $modinfo->get_sections();
        if (!isset($sections[$cm->sectionnum])) {
            return [0, 0];
        }

        $pos = 0;
        $total = 0;
        foreach ($sections[$cm->sectionnum] as $cmid) {
            $other = $modinfo->cms[$cmid];
            if (!$other->uservisible || !$other->url || in_array($other->modname, course_landing::EXCLUDED_MODS, true)) {
                continue;
            }
            $total++;
            if ((int) $cmid === (int) $cm->id) {
                $pos = $total;
            }
        }

        return [$pos, $total];
    }
}
