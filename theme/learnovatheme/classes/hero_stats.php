<?php
// Learnova hero stat-chip catalogue.
//
// Defines the 6 stat chips that can appear in the front-page hero. The admin
// chooses which 4 to show (and their order) via Home Setting, and may set a
// manual override per stat for new sites where the live data is still sparse.

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Catalogue + resolver for the hero stat chips.
 */
class hero_stats {

    /**
     * The 6 available stats, keyed by stat key.
     *
     * Each entry: label (lang string), tone (teal|amber|''), suffix (appended
     * to the auto value, e.g. '%' or 'h').
     *
     * @return array<string, array{label: string, tone: string, suffix: string}>
     */
    public static function definitions(): array {
        return [
            'courses' => [
                'label'  => get_string('statchip_courses', 'theme_learnovatheme'),
                'tone'   => 'teal',
                'suffix' => '',
            ],
            'learners' => [
                'label'  => get_string('statchip_learners', 'theme_learnovatheme'),
                'tone'   => '',
                'suffix' => '',
            ],
            'satisfaction' => [
                'label'  => get_string('statchip_satisfaction', 'theme_learnovatheme'),
                'tone'   => 'amber',
                'suffix' => '%',
            ],
            'instructors' => [
                'label'  => get_string('statchip_instructors', 'theme_learnovatheme'),
                'tone'   => '',
                'suffix' => '',
            ],
            'materials' => [
                'label'  => get_string('statchip_materials', 'theme_learnovatheme'),
                'tone'   => 'teal',
                'suffix' => '',
            ],
            'hours' => [
                'label'  => get_string('statchip_hours', 'theme_learnovatheme'),
                'tone'   => 'amber',
                'suffix' => 'h',
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
     * Compute the live value for a stat. Returns null when the underlying
     * feature isn't available yet (satisfaction rating, learning-hours field),
     * in which case a manual override is expected.
     *
     * @param string $key
     * @return int|null
     */
    public static function compute(string $key): ?int {
        global $DB, $CFG;

        switch ($key) {
            case 'courses':
                // Visible courses, excluding the front-page site course.
                return $DB->count_records_select('course', 'visible = 1 AND id <> :siteid', ['siteid' => SITEID]);

            case 'learners':
                // Real users who accessed the site in the last 30 days.
                $since = time() - (30 * DAYSECS);
                return $DB->count_records_select(
                    'user',
                    'deleted = 0 AND suspended = 0 AND id <> :guest AND lastaccess > :since',
                    ['guest' => $CFG->siteguest, 'since' => $since]
                );

            case 'satisfaction':
                // Needs the custom course-rating feature — not built yet.
                return null;

            case 'instructors':
                // Distinct users holding an editing-teacher role in any course.
                $sql = "SELECT COUNT(DISTINCT ra.userid)
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {role} r ON r.id = ra.roleid
                         WHERE ctx.contextlevel = :clevel
                           AND r.archetype = :arch";
                return $DB->count_records_sql($sql, ['clevel' => CONTEXT_COURSE, 'arch' => 'editingteacher']);

            case 'materials':
                // All activity/resource instances across courses.
                return $DB->count_records_select('course_modules', 'deletioninprogress = 0');

            case 'hours':
                // Needs the custom learning-hours course field — not built yet.
                return null;
        }

        return null;
    }

    /**
     * Format an auto value: append suffix, abbreviate large counts (K / M).
     *
     * @param int|null $value
     * @param string $suffix
     * @return string  Empty string when there's no value.
     */
    public static function format_value(?int $value, string $suffix): string {
        if ($value === null) {
            return '';
        }
        if ($suffix === '%' || $suffix === 'h') {
            return $value . $suffix;
        }
        if ($value >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M';
        }
        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K';
        }
        return (string) $value;
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

        $manual = get_config('theme_learnovatheme', "hero_stat_{$key}_manual");
        if ($manual !== false && trim((string) $manual) !== '') {
            $num = trim((string) $manual);
        } else {
            $num = self::format_value(self::compute($key), $def['suffix']);
        }

        if ($num === '') {
            $num = '—';
        }

        return ['num' => $num, 'lbl' => $def['label'], 'tone' => $def['tone']];
    }

    /**
     * Resolve the admin's chosen chips (4 ordered slots) into template data.
     * Falls back to sensible auto-working defaults when a slot is unset.
     *
     * @return array<int, array{num: string, lbl: string, tone: string}>
     */
    public static function selected(): array {
        $defaults = ['courses', 'learners', 'instructors', 'materials'];
        $stats = [];
        for ($i = 1; $i <= 4; $i++) {
            $key = get_config('theme_learnovatheme', "hero_stat_$i");
            if ($key === false) {
                $key = $defaults[$i - 1];
            }
            $resolved = self::resolve($key);
            if ($resolved !== null) {
                $stats[] = $resolved;
            }
        }
        return $stats;
    }
}
