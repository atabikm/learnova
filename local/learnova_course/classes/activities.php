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

use cm_info;
use core_collator;
use core_courseformat\output\local\overview\overviewtable;
use stdClass;

/**
 * Data builder for the Course Management "Activities" panel.
 *
 * One collapsible Learnova card per activity type actually present in the
 * course (Assignments, Forums, Quizzes, Resources, …), each holding a table
 * whose columns are specific to that type — due date + submissions for
 * assignments, forum type + subscribed + digest + discussions for forums, and
 * so on.
 *
 * The rows come from Moodle's own activity-overview API
 * ({@see overviewtable}, the data behind /course/overview.php): every mod
 * plugin declares its own overview columns there, so the panel always shows
 * the fields that really exist for a type — core, contrib or custom — instead
 * of a hardcoded column set. Interactive cells (the forum subscribe toggle,
 * the digest dropdown) are core renderables that register their own JS, so
 * they stay fully functional inside our card.
 *
 * Only the first (name) column is ours: core renders it with the stock module
 * icon, and the panel uses the Learnova course-landing icon set instead.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activities {

    /**
     * Build the activities panel context.
     *
     * @param stdClass $course
     * @return array
     */
    public static function context(stdClass $course): array {
        global $PAGE;

        // The core renderer, not $OUTPUT: the overview cells are renderables and
        // must be rendered by a real renderer_base (outside a web request
        // $OUTPUT is still the bootstrap renderer).
        $output  = $PAGE->get_renderer('core');
        $modinfo = get_fast_modinfo($course);

        $groups = [];
        $first  = true;
        foreach (self::group_labels($modinfo) as $modname => $label) {
            $table = new overviewtable($course, $modname);
            $data  = $table->export_for_template($output);

            $rows = self::rows($data->activities, $modinfo, $course);
            if (empty($rows)) {
                continue;
            }

            $groups[] = [
                'modname'   => $modname,
                'label'     => $label,
                'count'     => count($rows) === 1
                    ? get_string('act_count_one', 'local_learnova_course')
                    : get_string('act_count', 'local_learnova_course', count($rows)),
                'icon'      => self::icon_url($modname),
                'icontype'  => self::icon_slug($modname),
                'namelabel' => self::name_label($data->headers),
                'headers'   => self::headers($data->headers),
                'rows'      => $rows,
                // The first card opens expanded, the rest start collapsed —
                // the panel stays scannable on activity-heavy courses.
                'collapsed' => !$first,
            ];
            $first = false;
        }

        return [
            'groups'    => $groups,
            'hasgroups' => !empty($groups),
        ];
    }

    /**
     * The activity types present in this course, as modname => plural label.
     *
     * Mirrors the native overview page: every resource-archetype module
     * (File, Page, URL, Book, Folder …) folds into one "Resources" group,
     * everything else groups per module type. Types with nothing displayable
     * never appear, so the card count follows the course content.
     *
     * @param \course_modinfo $modinfo
     * @return array<string, string>
     */
    private static function group_labels(\course_modinfo $modinfo): array {
        $labels     = [];
        $archetypes = [];

        foreach ($modinfo->cms as $cm) {
            if (!overviewtable::is_cm_displayable($cm)) {
                continue;
            }
            if (!array_key_exists($cm->modname, $archetypes)) {
                $archetypes[$cm->modname] = plugin_supports(
                    'mod', $cm->modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            }
            if ($archetypes[$cm->modname] == MOD_ARCHETYPE_RESOURCE) {
                $labels['resource'] = get_string('resources');
            } else if (!array_key_exists($cm->modname, $labels)) {
                $labels[$cm->modname] = $cm->modplural;
            }
        }

        core_collator::asort($labels);
        return $labels;
    }

    /**
     * Label for the name column. The cell itself is ours (Learnova icon), but
     * the header text stays core's — so it reads and translates exactly like
     * the other columns, which are core strings too.
     *
     * @param array $headers Exported core headers (objects: key/name/textalign).
     * @return string
     */
    private static function name_label(array $headers): string {
        foreach ($headers as $header) {
            if ($header->key === 'name') {
                return $header->name;
            }
        }
        return get_string('name');
    }

    /**
     * Column headers for one type's table, minus the name column
     * ({@see self::name_label()} carries that one).
     *
     * @param array $headers Exported core headers (objects: key/name/textalign).
     * @return array<int, array{label: string, align: string}>
     */
    private static function headers(array $headers): array {
        $out = [];
        foreach ($headers as $header) {
            if ($header->key === 'name') {
                continue;
            }
            $out[] = ['label' => $header->name, 'align' => $header->textalign];
        }
        return $out;
    }

    /**
     * Table rows: our own name cell (Learnova icon + name + section) plus every
     * other column exactly as core rendered it.
     *
     * @param array $activities Exported core rows.
     * @param \course_modinfo $modinfo
     * @param stdClass $course
     * @return array
     */
    private static function rows(array $activities, \course_modinfo $modinfo, stdClass $course): array {
        $rows     = [];
        $sections = [];

        foreach ($activities as $activity) {
            $cm = $modinfo->get_cm($activity['cmid']);

            // Section name doubles as the row subtitle ("Topic 3", "Module 1").
            if (!array_key_exists($cm->sectionnum, $sections)) {
                $sections[$cm->sectionnum] = get_section_name($course, $cm->sectionnum);
            }

            $cells = [];
            foreach ($activity['overviews'] as $cell) {
                if ($cell->overview === 'name') {
                    continue;
                }
                $cells[] = [
                    'content' => $cell->content,
                    'align'   => $cell->textalign ?? '',
                ];
            }

            $rows[] = [
                'name'     => $cm->get_formatted_name(),
                'subtitle' => $sections[$cm->sectionnum],
                'url'      => $cm->url ? $cm->url->out(false) : '',
                'hasurl'   => (bool) $cm->url,
                'icon'     => self::icon_url($cm->modname, $cm),
                'icontype' => self::icon_slug($cm->modname, $cm),
                'cells'    => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Learnova icon-type slug for a module (the SVG filename and the CSS
     * modifier that tints the icon chip), shared with the course landing page.
     *
     * Falls back to the neutral "file" slug when the theme isn't available —
     * the panel must not depend on a theme being active to render.
     *
     * @param string $modname
     * @param cm_info|null $cm  When known, lets unmapped modules resolve by purpose.
     * @return string
     */
    private static function icon_slug(string $modname, ?cm_info $cm = null): string {
        if (!class_exists('\theme_learnovatheme\course_landing')) {
            return 'file';
        }
        if ($cm) {
            return \theme_learnovatheme\course_landing::activity_icon_type($cm);
        }
        return \theme_learnovatheme\course_landing::icon_type_for_modname($modname) ?? 'file';
    }

    /**
     * URL of the Learnova icon for a module type, or the module's own Moodle
     * icon when the theme isn't available.
     *
     * @param string $modname
     * @param cm_info|null $cm
     * @return string
     */
    private static function icon_url(string $modname, ?cm_info $cm = null): string {
        if (class_exists('\theme_learnovatheme\course_landing')) {
            return \theme_learnovatheme\course_landing::act_icon_url(self::icon_slug($modname, $cm));
        }
        return $cm ? $cm->get_icon_url()->out(false) : '';
    }
}
