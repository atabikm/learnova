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
        $card = self::from_recent_activity();
        if ($card === null) {
            $card = self::from_recent_course();
        }
        if ($card === null) {
            $card = self::empty_state();
        }
        return $card;
    }

    /**
     * Card built from the last activity the user opened.
     *
     * @return array|null  Null when nothing is tracked, or the activity is gone.
     */
    private static function from_recent_activity(): ?array {
        global $USER, $CFG;

        if (!class_exists('\block_recentlyaccesseditems\helper')) {
            return null;
        }

        // The helper already drops items the user can no longer see.
        $items = \block_recentlyaccesseditems\helper::get_recent_items(1);
        $item = reset($items);
        if (empty($item)) {
            return null;
        }

        try {
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $course = $modinfo->get_course();
        } catch (\Throwable $e) {
            return null;
        }

        require_once($CFG->dirroot . '/course/lib.php');

        // Meta line: section name · position within that section · activity type.
        $parts = [];
        $section = $cm->get_section_info();
        if ($section) {
            $sectionname = get_section_name($course, $section);
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

        return [
            'card_label'      => get_string('hero_card_label', 'theme_learnovatheme'),
            'card_course'     => format_string($course->fullname),
            'card_meta'       => implode(' · ', $parts),
            'card_progress'   => $percent === null ? 0 : (int) round($percent),
            'card_hasprogress' => $percent !== null,
            'card_url'        => $url,
            'card_continue'   => get_string('hero_card_continue', 'theme_learnovatheme'),
        ];
    }

    /**
     * Card built from the last course the user visited — used when no single
     * activity is tracked yet (e.g. the tracker was just enabled).
     *
     * @return array|null
     */
    private static function from_recent_course(): ?array {
        global $USER, $DB;

        $records = $DB->get_records('user_lastaccess', ['userid' => $USER->id], 'timeaccess DESC', 'courseid', 0, 5);
        foreach ($records as $record) {
            if ((int) $record->courseid === (int) SITEID) {
                continue;
            }
            try {
                $course = get_course($record->courseid);
            } catch (\Throwable $e) {
                continue;
            }
            if (!can_access_course($course, null, '', true)) {
                continue;
            }

            $percent = \core_completion\progress::get_course_progress_percentage($course, $USER->id);

            return [
                'card_label'      => get_string('hero_card_label', 'theme_learnovatheme'),
                'card_course'     => format_string($course->fullname),
                'card_meta'       => '',
                'card_progress'   => $percent === null ? 0 : (int) round($percent),
                'card_hasprogress' => $percent !== null,
                'card_url'        => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'card_continue'   => get_string('hero_card_continue', 'theme_learnovatheme'),
            ];
        }

        return null;
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
            if (!$other->uservisible || !$other->url) {
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
