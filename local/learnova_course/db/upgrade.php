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

/**
 * Upgrade steps for local_learnova_course.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_learnova_course_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061700) {
        // The mandatory/elective course type is no longer used; drop the column.
        $table = new xmldb_table('learnova_course_meta');
        $field = new xmldb_field('course_type');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'learnova_course');
    }

    if ($oldversion < 2026062200) {
        // Add the learning objectives textarea (drives the course landing About tab).
        $table = new xmldb_table('learnova_course_meta');
        $field = new xmldb_field('learning_objectives', XMLDB_TYPE_TEXT, null, null, null, null, null, 'course_level');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026062200, 'local', 'learnova_course');
    }

    if ($oldversion < 2026062500) {
        // Replace the single free-text announcement with the conditional,
        // 3-tier announcement band fields. The 6 new columns are added first so
        // any existing announcement text can be migrated before the old column
        // is dropped.
        $table = new xmldb_table('learnova_course_meta');

        $newfields = [
            new xmldb_field('learnova_annc_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'cert_validity_days'),
            new xmldb_field('learnova_annc_tier', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info', 'learnova_annc_enabled'),
            new xmldb_field('learnova_annc_text', XMLDB_TYPE_TEXT, null, null, null, null, null, 'learnova_annc_tier'),
            new xmldb_field('learnova_annc_start', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'learnova_annc_text'),
            new xmldb_field('learnova_annc_end', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'learnova_annc_start'),
            new xmldb_field('learnova_annc_scope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all', 'learnova_annc_end'),
        ];
        foreach ($newfields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Migrate any existing announcement into the new band as an enabled
        // Info-tier notice, so nothing silently disappears on upgrade.
        $oldfield = new xmldb_field('course_announcement');
        if ($dbman->field_exists($table, $oldfield)) {
            $DB->execute("UPDATE {learnova_course_meta}
                             SET learnova_annc_text = course_announcement,
                                 learnova_annc_enabled = 1
                           WHERE course_announcement IS NOT NULL
                                 AND " . $DB->sql_isnotempty('learnova_course_meta', 'course_announcement', false, false));
            $dbman->drop_field($table, $oldfield);
        }

        upgrade_plugin_savepoint(true, 2026062500, 'local', 'learnova_course');
    }

    if ($oldversion < 2026070100) {
        // Add the Discussion tab toggle to course meta.
        $table = new xmldb_table('learnova_course_meta');
        $field = new xmldb_field('learnova_discussion_enabled', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'notif_channels');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create the custom discussion posts table (Plan B).
        $posts = new xmldb_table('learnova_discussion_posts');
        if (!$dbman->table_exists($posts)) {
            $posts->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $posts->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $posts->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $posts->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $posts->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $posts->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $posts->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $posts->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $posts->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $posts->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $posts->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $posts->add_index('courseid-deleted-timecreated', XMLDB_INDEX_NOTUNIQUE,
                ['courseid', 'deleted', 'timecreated']);
            $dbman->create_table($posts);
        }

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'learnova_course');
    }

    if ($oldversion < 2026081200) {
        // Scope discussion posts to a course module so the focus-mode Activity
        // Discussion panel can share this engine with the course-level tab.
        // cmid = 0 keeps every existing post where it is: on the course tab.
        $posts = new xmldb_table('learnova_discussion_posts');
        $cmid = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($posts, $cmid)) {
            $dbman->add_field($posts, $cmid);
        }

        // Every read filters on cmid now, so the covering index moves with it.
        $old = new xmldb_index('courseid-deleted-timecreated', XMLDB_INDEX_NOTUNIQUE,
            ['courseid', 'deleted', 'timecreated']);
        if ($dbman->index_exists($posts, $old)) {
            $dbman->drop_index($posts, $old);
        }
        $new = new xmldb_index('courseid-cmid-deleted-timecreated', XMLDB_INDEX_NOTUNIQUE,
            ['courseid', 'cmid', 'deleted', 'timecreated']);
        if (!$dbman->index_exists($posts, $new)) {
            $dbman->add_index($posts, $new);
        }

        upgrade_plugin_savepoint(true, 2026081200, 'local', 'learnova_course');
    }

    return true;
}
