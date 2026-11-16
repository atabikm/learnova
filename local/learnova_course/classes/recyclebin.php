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
 * Data builder and action handler for the Course Management "Recycle Bin" panel.
 *
 * Lists the activities deleted from this course that Moodle's own recycle bin
 * (tool_recyclebin) is still holding, with Restore / Delete permanently per row.
 *
 * The bin itself is entirely native: \tool_recyclebin\course_bin decides whether
 * the feature is on, what it holds, and does the restoring and deleting. The
 * only reason the actions do not simply link to /admin/tool/recyclebin/index.php
 * is that it redirects to its own page — routing them through management.php
 * instead keeps the user in the panel they started from.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recyclebin {

    /**
     * Is the course recycle bin available to this user at all?
     *
     * @param context_course $context
     * @return bool
     */
    public static function is_available(context_course $context): bool {
        if (!class_exists('\tool_recyclebin\course_bin')) {
            return false;
        }
        if (!\tool_recyclebin\course_bin::is_enabled()) {
            return false;
        }
        return has_capability('tool/recyclebin:viewitems', $context);
    }

    /**
     * Build the recycle bin panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        if (!self::is_available($context)) {
            return ['available' => false, 'hasitems' => false, 'items' => []];
        }

        $bin = new \tool_recyclebin\course_bin($course->id);
        $canrestore = $bin->can_restore();
        $candelete  = $bin->can_delete();

        $base = new moodle_url('/local/learnova_course/management.php', [
            'id'      => $course->id,
            'pane'    => 'recycle-bin',
            'sesskey' => sesskey(),
        ]);

        $items = [];
        foreach ($bin->get_items() as $item) {
            $items[] = [
                'name'       => format_string($item->name, true, ['context' => $context]),
                'deletedon'  => userdate($item->timecreated),
                'canrestore' => $canrestore,
                'candelete'  => $candelete,
                'restoreurl' => (new moodle_url($base,
                    ['rbaction' => 'restore', 'rbitem' => $item->id]))->out(false),
                'deleteurl'  => (new moodle_url($base,
                    ['rbaction' => 'delete', 'rbitem' => $item->id]))->out(false),
            ];
        }

        return [
            'available' => true,
            'items'     => $items,
            'hasitems'  => !empty($items),
            'expiry'    => self::expiry_note(),
        ];
    }

    /**
     * Run a restore/delete requested from the panel, then return to it.
     *
     * Called before any page output. Every guard is the bin's own: sesskey, the
     * view capability, then can_restore() / the delete capability.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param string $action 'restore' or 'delete'
     * @param int $itemid
     * @return void  Always redirects.
     */
    public static function handle_action(stdClass $course, context_course $context,
            string $action, int $itemid): void {
        require_sesskey();

        if (!self::is_available($context)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'tool/recyclebin:viewitems');
        }

        $bin  = new \tool_recyclebin\course_bin($course->id);
        $item = $bin->get_item($itemid);

        if ($action === 'restore') {
            if (!$bin->can_restore()) {
                throw new \moodle_exception('nopermissions', 'error', '', 'tool/recyclebin:restoreitems');
            }
            raise_memory_limit(MEMORY_EXTRA);
            $bin->restore_item($item);
            $message = get_string('alertrestored', 'tool_recyclebin', $item);
        } else {
            if (!$bin->can_delete()) {
                throw new \moodle_exception('nopermissions', 'error', '', 'tool/recyclebin:deleteitems');
            }
            raise_memory_limit(MEMORY_EXTRA);
            $bin->delete_item($item);
            $message = get_string('alertdeleted', 'tool_recyclebin', $item);
        }

        redirect(new moodle_url('/local/learnova_course/management.php',
            ['id' => $course->id, 'pane' => 'recycle-bin']), $message);
    }

    /**
     * "Items are removed automatically after N days" note, or '' when the bin
     * keeps items forever.
     *
     * @return string
     */
    private static function expiry_note(): string {
        $expiry = get_config('tool_recyclebin', 'coursebinexpiry');
        if (empty($expiry)) {
            return '';
        }
        return get_string('rb_expiry', 'local_learnova_course', format_time($expiry));
    }
}
