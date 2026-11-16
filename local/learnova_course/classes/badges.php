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
use moodle_url;
use stdClass;

/**
 * Data builder for the Course Management "Badges" panel.
 *
 * The course's badges as a Learnova table — image, name, status, recipients —
 * where a row is the entry point to that badge's overview, and "+ New Badge"
 * slides in the NATIVE badge form.
 *
 * That form is the one place the story's field list comes from: Moodle's own
 * badge form already declares the three sections the story asks for —
 * badgedetails (name, version, language, description, image, caption, tags),
 * issuerdetails (name, email) and issuancedetails (expiry) — so embedding it
 * gives the exact fields, their validation, and the image handling, instead of
 * a look-alike that cannot actually mint a badge.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges {

    /**
     * Build the badges panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');

        $courseid = (int) $course->id;
        $rows     = [];

        foreach (badges_get_badges(BADGE_TYPE_COURSE, $courseid) as $badge) {
            $awards = $badge->get_awards();
            $rows[] = [
                'id'         => (int) $badge->id,
                'name'       => format_string($badge->name, true, ['context' => $context]),
                'image'      => moodle_url::make_pluginfile_url(
                    $badge->get_context()->id, 'badges', 'badgeimage', $badge->id, '/', 'f2', false)->out(false),
                'status'     => get_string('badgestatus_' . $badge->status, 'core_badges'),
                'active'     => $badge->is_active(),
                'recipients' => count($awards),
                // Entry point to the badge overview. Focus Mode is a separate
                // story; until it lands this opens Moodle's own overview, so the
                // row is a real destination rather than a dead click.
                'url'        => (new moodle_url('/badges/overview.php', ['id' => $badge->id]))->out(false),
                'editurl'    => (new moodle_url('/badges/edit.php',
                    ['id' => $badge->id, 'action' => 'badge', 'clembed' => 1]))->out(false),
            ];
        }

        return [
            'rows'    => $rows,
            'hasrows' => !empty($rows),
            'canadd'  => has_capability('moodle/badges:createbadge', $context),
            // The native new-badge form, same params as Moodle's own button.
            'addurl'  => (new moodle_url('/badges/edit.php', [
                'action' => 'new', 'courseid' => $courseid, 'clembed' => 1,
            ]))->out(false),
        ];
    }
}
