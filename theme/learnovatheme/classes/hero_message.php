<?php
// Learnova hero motivational message — picks a message for the logged-in hero
// based on the learner's current state (rule-based, not AI).
//
// Conditions are checked in priority order; the first match wins. Every
// message is customizable in Home Setting (admin overrides the default).

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule-based resolver for the logged-in hero's motivational message.
 */
class hero_message {

    /**
     * Condition keys in evaluation priority (first match wins).
     *
     * Note: away_long (7+ days) is checked before away_short (3+ days) so the
     * stronger "been away a long time" message is reachable — a strict 3+ then
     * 7+ order would make the 7+ branch dead code.
     *
     * @var string[]
     */
    private const PRIORITY = [
        'completed_today',
        'completed_course',
        'progress_week',
        'overdue',
        'away_long',
        'away_short',
        'default',
    ];

    /**
     * Resolve the message for the current user.
     *
     * @return string
     */
    public static function resolve(): string {
        foreach (self::PRIORITY as $key) {
            if (self::matches($key)) {
                return self::message($key);
            }
        }
        return self::message('default');
    }

    /**
     * All message keys (each is editable in Home Setting, including 'default').
     *
     * @return string[]
     */
    public static function keys(): array {
        return self::PRIORITY;
    }

    /**
     * The message text for a condition: admin override wins, else the default.
     *
     * @param string $key
     * @return string
     */
    private static function message(string $key): string {
        $custom = get_config('theme_learnovatheme', "heromsg_$key");
        if ($custom !== false && trim((string) $custom) !== '') {
            return $custom;
        }
        return get_string("heromsg_{$key}_default", 'theme_learnovatheme');
    }

    /**
     * Whether a given condition currently applies to the logged-in user.
     *
     * @param string $key
     * @return bool
     */
    private static function matches(string $key): bool {
        global $USER, $DB;
        $now = time();

        switch ($key) {
            case 'completed_today':
                // An activity completion recorded since midnight (user's tz).
                return $DB->record_exists_select(
                    'course_modules_completion',
                    'userid = :uid AND completionstate >= 1 AND timemodified >= :start',
                    ['uid' => $USER->id, 'start' => usergetmidnight($now)]
                );

            case 'completed_course':
                // A whole course completed in the last 24 hours.
                return $DB->record_exists_select(
                    'course_completions',
                    'userid = :uid AND timecompleted >= :since',
                    ['uid' => $USER->id, 'since' => $now - DAYSECS]
                );

            case 'progress_week':
                // Proxy for "journey progress up this week": any activity
                // completion in the last 7 days. (Replace with real journey
                // progress once the learning-journey feature exists.)
                return $DB->record_exists_select(
                    'course_modules_completion',
                    'userid = :uid AND completionstate >= 1 AND timemodified >= :since',
                    ['uid' => $USER->id, 'since' => $now - (7 * DAYSECS)]
                );

            case 'overdue':
                return self::has_overdue();

            case 'away_long':
                return self::absence_days() >= 7;

            case 'away_short':
                return self::absence_days() >= 3;

            case 'default':
                return true;
        }

        return false;
    }

    /**
     * Days since the user's previous login (their absence gap before the
     * current session). 0 for first-ever logins.
     *
     * @return float
     */
    private static function absence_days(): float {
        global $USER;
        if (empty($USER->lastlogin)) {
            return 0.0;
        }
        $reference = !empty($USER->currentlogin) ? $USER->currentlogin : time();
        return max(0, $reference - $USER->lastlogin) / DAYSECS;
    }

    /**
     * Whether the user has any past-due actionable items (e.g. an assignment
     * past its due date that still needs submitting). Best-effort via the
     * calendar action-events API; returns false if anything goes wrong.
     *
     * @return bool
     */
    private static function has_overdue(): bool {
        global $USER;

        try {
            // Note: the API takes a user *object* as its 6th argument.
            $events = \core_calendar\local\api::get_action_events_by_timesort(
                null,        // From the beginning of time…
                time(),      // …up to now = only past-due items.
                null,        // aftereventid
                1,           // limitnum — existence check only.
                true,        // non-suspended enrolments only
                $USER        // user object
            );
            return !empty($events);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
