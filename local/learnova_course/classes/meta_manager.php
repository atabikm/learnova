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

/**
 * Read/write helper and option catalogue for Learnova course meta.
 *
 * This is the single public entry point other Learnova features (dashboard,
 * reports, homepage popular courses) should use to read course meta. It always
 * returns a fully-populated record by falling back to spec defaults so callers
 * never have to special-case "no row yet".
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meta_manager {

    /** @var string Database table holding course meta. */
    const TABLE = 'learnova_course_meta';

    /**
     * Default values for every field, matching the Learnova spec.
     *
     * @return array
     */
    public static function get_defaults(): array {
        return [
            'learning_hours_minutes' => 60,
            'course_level' => 'beginner',
            'learning_objectives' => '',
            'cert_enabled' => 0,
            'cert_template_id' => 0,
            'cert_issuer' => '',
            'cert_validity_days' => 0,
            'learnova_annc_enabled' => 0,
            'learnova_annc_tier' => 'info',
            'learnova_annc_text' => '',
            'learnova_annc_start' => 0,
            'learnova_annc_end' => 0,
            'learnova_annc_scope' => 'all',
            'deadline_type' => 'enrollment',
            'deadline_days' => 30,
            'notif_reminder_days' => 7,
            'notif_repeat_days' => 3,
            'notif_channels' => 'email,push',
            'learnova_discussion_enabled' => 0,
        ];
    }

    /**
     * Course level options (key => lang string identifier).
     *
     * @return array
     */
    public static function level_options(): array {
        return [
            'beginner' => get_string('level_beginner', 'local_learnova_course'),
            'intermediate' => get_string('level_intermediate', 'local_learnova_course'),
            'advanced' => get_string('level_advanced', 'local_learnova_course'),
        ];
    }

    /**
     * Deadline type options.
     *
     * @return array
     */
    public static function deadline_options(): array {
        return [
            'enrollment' => get_string('deadline_enrollment', 'local_learnova_course'),
            'coursestart' => get_string('deadline_coursestart', 'local_learnova_course'),
            'courseend' => get_string('deadline_courseend', 'local_learnova_course'),
        ];
    }

    /**
     * Announcement tier options (key => label).
     *
     * The tier keys double as the band's CSS modifier and the icon selector, so
     * they are part of the stored data contract — keep them in sync with the
     * course landing template and post.scss.
     *
     * @return array
     */
    public static function annc_tier_options(): array {
        return [
            'urgent'  => get_string('annc_tier_urgent', 'local_learnova_course'),
            'warning' => get_string('annc_tier_warning', 'local_learnova_course'),
            'info'    => get_string('annc_tier_info', 'local_learnova_course'),
        ];
    }

    /**
     * Announcement visibility scope options (key => label).
     *
     * @return array
     */
    public static function annc_scope_options(): array {
        return [
            'all'      => get_string('annc_scope_all', 'local_learnova_course'),
            'enrolled' => get_string('annc_scope_enrolled', 'local_learnova_course'),
        ];
    }

    /**
     * Whether the course announcement band should render for this viewer.
     *
     * The single source of truth for the band's conditional logic, reused by the
     * course landing builder (and any future surface). The band shows only when:
     *  - it is enabled and has non-empty text;
     *  - the current time is within [start, end] — either bound is optional, a 0
     *    bound means "open ended" on that side, both 0 means "always";
     *  - the scope allows this viewer: "all" always passes, "enrolled" requires
     *    an enrolled/insider viewer.
     *
     * @param \stdClass $meta    Course meta (as returned by {@see self::get()}).
     * @param bool      $insider Whether the viewer is enrolled or can manage the
     *                           course (used to satisfy the "enrolled only" scope).
     * @param int|null  $now     Override the current time (testing); defaults to time().
     * @return bool
     */
    public static function announcement_is_visible(\stdClass $meta, bool $insider, ?int $now = null): bool {
        if (empty($meta->learnova_annc_enabled)) {
            return false;
        }
        // The text is rich HTML; treat markup-only content (e.g. an empty
        // "<p></p>" or a lone non-breaking space) as no announcement.
        $plain = preg_replace('/(\x{00a0}|\s)+/u', '', strip_tags((string) ($meta->learnova_annc_text ?? '')));
        if ($plain === '') {
            return false;
        }

        $now   = $now ?? time();
        $start = (int) ($meta->learnova_annc_start ?? 0);
        $end   = (int) ($meta->learnova_annc_end ?? 0);
        if ($start > 0 && $now < $start) {
            return false;
        }
        if ($end > 0 && $now > $end) {
            return false;
        }

        if (($meta->learnova_annc_scope ?? 'all') === 'enrolled' && !$insider) {
            return false;
        }

        return true;
    }

    /**
     * Normalise a stored tier to a known key, defaulting to "info".
     *
     * @param string|null $tier
     * @return string
     */
    public static function annc_tier_key(?string $tier): string {
        return array_key_exists((string) $tier, self::annc_tier_options()) ? (string) $tier : 'info';
    }

    /**
     * Notification channel options.
     *
     * @return array
     */
    public static function channel_options(): array {
        return [
            'email' => get_string('channel_email', 'local_learnova_course'),
            'push' => get_string('channel_push', 'local_learnova_course'),
        ];
    }

    /**
     * Certificate template options.
     *
     * Placeholder until the Certificate Builder is available; always exposes
     * at least the "Default" template (id 0).
     *
     * @return array
     */
    public static function cert_template_options(): array {
        return [
            0 => get_string('cert_template_default', 'local_learnova_course'),
        ];
    }

    /**
     * Get the meta record for a course, merged over defaults.
     *
     * Always returns an object with every field present. When no row exists yet
     * the spec defaults are returned (with id = 0).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get(int $courseid): \stdClass {
        global $DB;

        $defaults = self::get_defaults();
        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);

        if (!$record) {
            $record = (object) $defaults;
            $record->id = 0;
            $record->courseid = $courseid;
            return $record;
        }

        // Guard against fields added later but missing on an old row.
        foreach ($defaults as $key => $value) {
            if (!isset($record->{$key})) {
                $record->{$key} = $value;
            }
        }
        return $record;
    }

    /**
     * Create or update the meta record for a course.
     *
     * Only known fields are persisted; unknown keys in $data are ignored.
     *
     * @param int $courseid
     * @param array|\stdClass $data Field values keyed by field name.
     * @return int The id of the stored row.
     */
    public static function save(int $courseid, $data): int {
        global $DB;

        $data = (array) $data;
        $now = time();
        $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);

        $record = new \stdClass();
        $record->courseid = $courseid;
        foreach (array_keys(self::get_defaults()) as $field) {
            if (array_key_exists($field, $data)) {
                $record->{$field} = $data[$field];
            } else if ($existing && isset($existing->{$field})) {
                $record->{$field} = $existing->{$field};
            } else {
                $record->{$field} = self::get_defaults()[$field];
            }
        }

        if ($existing) {
            $record->id = $existing->id;
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
            return $existing->id;
        }

        $record->timecreated = $now;
        $record->timemodified = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete the meta row for a course (e.g. on course deletion).
     *
     * @param int $courseid
     */
    public static function delete(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Format a minutes value as "Xh Ym" for display.
     *
     * @param int $minutes
     * @return string
     */
    public static function format_learning_hours(int $minutes): string {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        }
        if ($hours > 0) {
            return "{$hours}h";
        }
        return "{$mins}m";
    }

    /**
     * Parse a notification channels CSV string into an array of channel keys.
     *
     * @param string|null $csv
     * @return string[]
     */
    public static function parse_channels(?string $csv): array {
        if (empty($csv)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /**
     * Parse a learning-objectives textarea into a list of non-empty lines.
     *
     * One objective per line; blank lines and surrounding whitespace are
     * dropped. Returns a plain list of strings ready to hand to a template.
     *
     * @param string|null $text Raw textarea value.
     * @return string[]
     */
    public static function parse_objectives(?string $text): array {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        return array_values(array_filter(array_map('trim', $lines), function($line) {
            return $line !== '';
        }));
    }
}
