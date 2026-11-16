<?php
// Learnova hero stat-chip catalogue — logged-in variant.
//
// Mirrors hero_stats (the logged-out catalogue), but every value describes the
// *current learner* rather than the site. The admin chooses which 4 chips show
// (and their order) in Home Setting, and may set a manual override per chip for
// stats whose underlying feature does not exist yet (e.g. learning hours).

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Catalogue + resolver for the logged-in hero stat chips.
 */
class user_stats {

    /**
     * The available learner stats, keyed by stat key.
     *
     * Each entry: label (lang string), tone (teal|amber|'').
     *
     * @return array<string, array{label: string, tone: string}>
     */
    public static function definitions(): array {
        return [
            'courses_inprogress' => [
                'label' => get_string('userstat_courses_inprogress', 'theme_learnovatheme'),
                'tone'  => 'teal',
            ],
            'courses_completed' => [
                'label' => get_string('userstat_courses_completed', 'theme_learnovatheme'),
                'tone'  => '',
            ],
            'activities_done' => [
                'label' => get_string('userstat_activities_done', 'theme_learnovatheme'),
                'tone'  => 'teal',
            ],
            'badges' => [
                'label' => get_string('userstat_badges', 'theme_learnovatheme'),
                'tone'  => 'amber',
            ],
            'competency' => [
                'label' => get_string('userstat_competency', 'theme_learnovatheme'),
                'tone'  => 'amber',
            ],
            'competency_met' => [
                'label' => get_string('userstat_competency_met', 'theme_learnovatheme'),
                'tone'  => '',
            ],
            'due' => [
                'label' => get_string('userstat_due', 'theme_learnovatheme'),
                'tone'  => 'amber',
            ],
            'hours' => [
                'label' => get_string('userstat_hours', 'theme_learnovatheme'),
                'tone'  => 'teal',
            ],
        ];
    }

    /**
     * Stat keys in catalogue order.
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys(self::definitions());
    }

    /**
     * Options for an admin select (prepends a "None" choice).
     *
     * @return array<string, string>
     */
    public static function menu_options(): array {
        $options = ['' => get_string('settings_hero_stat_none', 'theme_learnovatheme')];
        foreach (self::definitions() as $key => $def) {
            $options[$key] = $def['label'];
        }
        return $options;
    }

    /**
     * Compute the live value for a stat, already formatted for display.
     *
     * Returns null when the value cannot be derived — either the underlying
     * feature does not exist yet (learning hours) or the learner has no data
     * for it (no competency plan). A manual override is expected in that case.
     *
     * @param string $key
     * @return string|null
     */
    public static function compute(string $key): ?string {
        global $USER, $DB;

        switch ($key) {
            case 'courses_inprogress':
                return (string) self::course_tally()['inprogress'];

            case 'courses_completed':
                return (string) self::course_tally()['completed'];

            case 'activities_done':
                // Completed activities across every course the user touched.
                $sql = "SELECT COUNT(cmc.id)
                          FROM {course_modules_completion} cmc
                          JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                         WHERE cmc.userid = :uid
                           AND cmc.completionstate >= 1
                           AND cm.deletioninprogress = 0";
                return (string) $DB->count_records_sql($sql, ['uid' => $USER->id]);

            case 'badges':
                return (string) $DB->count_records('badge_issued', ['userid' => $USER->id]);

            case 'competency':
                $tally = self::competency_tally();
                if ($tally === null || $tally['total'] === 0) {
                    return null;
                }
                return round(($tally['proficient'] / $tally['total']) * 100) . '%';

            case 'competency_met':
                $tally = self::competency_tally();
                if ($tally === null || $tally['total'] === 0) {
                    return null;
                }
                return $tally['proficient'] . '/' . $tally['total'];

            case 'due':
                return (string) self::due_this_week();

            case 'hours':
                // Needs the custom learning-hours feature — not built yet.
                return null;
        }

        return null;
    }

    /**
     * Enrolled courses split into "in progress" and "completed".
     *
     * A course counts as completed when core marks it complete, or — for
     * courses without a completion criteria set — when every completion-tracked
     * activity is done (progress 100%). Computed once per request.
     *
     * @return array{inprogress: int, completed: int}
     */
    private static function course_tally(): array {
        global $USER, $DB;
        // Keyed by user: several chips share this tally, and a CLI/test run may
        // resolve stats for more than one user in the same process.
        static $cache = [];

        if (isset($cache[$USER->id])) {
            return $cache[$USER->id];
        }

        $tally = ['inprogress' => 0, 'completed' => 0];
        $courses = enrol_get_my_courses('id');

        foreach ($courses as $course) {
            // get_course() reads through the course cache, so this stays cheap
            // even though progress needs the full record.
            $full = get_course($course->id);
            $percent = \core_completion\progress::get_course_progress_percentage($full, $USER->id);

            $iscomplete = $DB->record_exists_select(
                'course_completions',
                'userid = :uid AND course = :cid AND timecompleted IS NOT NULL',
                ['uid' => $USER->id, 'cid' => $course->id]
            );

            if ($iscomplete || ($percent !== null && $percent >= 100)) {
                $tally['completed']++;
            } else if ($percent !== null && $percent > 0) {
                $tally['inprogress']++;
            }
        }

        $cache[$USER->id] = $tally;
        return $tally;
    }

    /**
     * Competencies across the learner's plans: how many are proficient out of
     * how many are assigned. Null when competencies are off, the user has no
     * plan, or the API refuses (permissions).
     *
     * @return array{proficient: int, total: int}|null
     */
    private static function competency_tally(): ?array {
        global $USER;
        // Keyed by user for the same reason as course_tally().
        static $cache = [];

        if (array_key_exists($USER->id, $cache)) {
            return $cache[$USER->id];
        }
        $cache[$USER->id] = null;

        if (!class_exists('\core_competency\api') || !\core_competency\api::is_enabled()) {
            return null;
        }

        try {
            $plans = \core_competency\api::list_user_plans($USER->id);
            if (empty($plans)) {
                return null;
            }

            $total = 0;
            $proficient = 0;
            foreach ($plans as $plan) {
                foreach (\core_competency\api::list_plan_competencies($plan) as $item) {
                    $total++;
                    // Completed plans freeze their ratings in usercompetencyplan;
                    // active plans read the live usercompetency.
                    $uc = $item->usercompetencyplan ?? $item->usercompetency;
                    if ($uc && $uc->get('proficiency')) {
                        $proficient++;
                    }
                }
            }
            $cache[$USER->id] = ['proficient' => $proficient, 'total' => $total];
        } catch (\Throwable $e) {
            $cache[$USER->id] = null;
        }

        return $cache[$USER->id];
    }

    /**
     * Actionable items due in the next 7 days (assignment submissions, quiz
     * closes, …). Best-effort via the calendar action-events API.
     *
     * @return int
     */
    private static function due_this_week(): int {
        global $USER;

        try {
            $events = \core_calendar\local\api::get_action_events_by_timesort(
                time(),                     // From now…
                time() + (7 * DAYSECS),     // …to a week out.
                null,                       // aftereventid
                50,                         // limitnum — a display count, not a total.
                true,                       // non-suspended enrolments only
                $USER                       // user object
            );
            return count($events);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Resolve a stat to template data: manual override wins, else the live
     * value, else an em-dash placeholder.
     *
     * @param string $key
     * @return array{num: string, lbl: string, tone: string}|null  Null for an unknown/empty key.
     */
    public static function resolve(string $key): ?array {
        $defs = self::definitions();
        if ($key === '' || !array_key_exists($key, $defs)) {
            return null;
        }
        $def = $defs[$key];

        $manual = get_config('theme_learnovatheme', "hero_userstat_{$key}_manual");
        if ($manual !== false && trim((string) $manual) !== '') {
            $num = trim((string) $manual);
        } else {
            $num = (string) (self::compute($key) ?? '');
        }

        if ($num === '') {
            $num = '—';
        }

        return ['num' => $num, 'lbl' => $def['label'], 'tone' => $def['tone']];
    }

    /**
     * Default chip per slot — the four that work without any extra feature
     * being configured.
     *
     * @var string[]
     */
    public const SLOT_DEFAULTS = ['courses_inprogress', 'courses_completed', 'activities_done', 'badges'];

    /**
     * Resolve the admin's chosen chips (4 ordered slots) into template data.
     *
     * @return array<int, array{num: string, lbl: string, tone: string}>
     */
    public static function selected(): array {
        $stats = [];
        for ($i = 1; $i <= 4; $i++) {
            $key = get_config('theme_learnovatheme', "hero_userstat_$i");
            if ($key === false) {
                $key = self::SLOT_DEFAULTS[$i - 1];
            }
            $resolved = self::resolve($key);
            if ($resolved !== null) {
                $stats[] = $resolved;
            }
        }
        return $stats;
    }
}
