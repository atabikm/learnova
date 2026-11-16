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
 * Data builder for the Course Management "Course Reuse" panel.
 *
 * Sub-tabs — Import, Backup, Restore, Reset, Copy course — over the shared
 * tabbed-embed component: each tab loads the REAL native Moodle page for that
 * operation (chrome-less, ?clembed=1), so every flow is Moodle's own, with its
 * own steps, validation and permissions. Nothing here re-implements a backup or
 * a reset; the panel only decides which tabs a viewer gets and introduces each.
 *
 * Which tabs appear comes from course_get_user_administration_options(), the
 * same source as Moodle's own "Course reuse" menu — so the tab set matches what
 * the user could reach natively, exactly.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reuse {

    /**
     * Build the course reuse panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $options = course_get_user_administration_options($course, $context);

        // Tab order follows the story: Import · Backup · Restore · Reset · Copy.
        // Reset carries a danger note — it is the one destructive operation here.
        $defs = [
            'import' => [
                'show'  => !empty($options->import),
                'label' => get_string('import'),
                'url'   => new moodle_url('/backup/import.php', ['id' => $course->id]),
                'tone'  => 'info',
            ],
            'backup' => [
                'show'  => !empty($options->backup),
                'label' => get_string('backup'),
                'url'   => new moodle_url('/backup/backup.php', ['id' => $course->id]),
                'tone'  => 'info',
            ],
            'restore' => [
                'show'  => !empty($options->restore),
                'label' => get_string('restore'),
                'url'   => new moodle_url('/backup/restorefile.php', ['contextid' => $context->id]),
                'tone'  => 'info',
            ],
            'reset' => [
                'show'  => !empty($options->reset),
                'label' => get_string('reset'),
                'url'   => new moodle_url('/course/reset.php', ['id' => $course->id]),
                // White card, pink border — the mock's warning card.
                'tone'  => 'danger-outline',
            ],
            'copy' => [
                'show'  => !empty($options->copy),
                'label' => get_string('copycourse'),
                'url'   => new moodle_url('/backup/copy.php', ['id' => $course->id]),
                'tone'  => 'info',
            ],
        ];

        $tabs  = [];
        $first = true;
        foreach ($defs as $key => $def) {
            if (!$def['show']) {
                continue;
            }
            $url = new moodle_url($def['url']);
            $url->param('clembed', 1);

            $tabs[] = [
                'key'    => $key,
                'label'  => $def['label'],
                'url'    => $url->out(false),
                'note'   => get_string('cr_note_' . $key, 'local_learnova_course'),
                'tone'   => $def['tone'],
                'active' => $first,
            ];
            $first = false;
        }

        return [
            'frametitle' => get_string('management_menu_coursereuse', 'local_learnova_course'),
            'tabs'       => $tabs,
            'hastabs'    => !empty($tabs),
            // Every tab here is an embed tab, so the shared frame is always on.
            'hasembed'   => !empty($tabs),
        ];
    }
}
