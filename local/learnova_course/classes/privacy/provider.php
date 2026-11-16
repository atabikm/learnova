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

namespace local_learnova_course\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_learnova_course\discussion_manager;

/**
 * Privacy provider for local_learnova_course.
 *
 * {learnova_course_meta} holds course-level configuration only (no personal
 * data). {learnova_discussion_posts} stores user-authored discussion posts, so
 * this provider exports and deletes those on request.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('learnova_discussion_posts', [
            'courseid'     => 'privacy:metadata:learnova_discussion_posts:courseid',
            'cmid'         => 'privacy:metadata:learnova_discussion_posts:cmid',
            'userid'       => 'privacy:metadata:learnova_discussion_posts:userid',
            'parentid'     => 'privacy:metadata:learnova_discussion_posts:parentid',
            'message'      => 'privacy:metadata:learnova_discussion_posts:message',
            'timecreated'  => 'privacy:metadata:learnova_discussion_posts:timecreated',
            'timemodified' => 'privacy:metadata:learnova_discussion_posts:timemodified',
        ], 'privacy:metadata:learnova_discussion_posts');
        return $collection;
    }

    /**
     * Course contexts where the given user has authored a discussion post.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {" . discussion_manager::TABLE . "} p
                  JOIN {context} ctx ON ctx.instanceid = p.courseid AND ctx.contextlevel = :courselevel
                 WHERE p.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid'      => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Users who have authored posts in the given (course) context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {" . discussion_manager::TABLE . "} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    /**
     * Export a user's posts, grouped per course context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $records = $DB->get_records(discussion_manager::TABLE,
                ['courseid' => $context->instanceid, 'userid' => $userid], 'timecreated ASC');
            if (!$records) {
                continue;
            }
            $data = array_map(function($r) {
                return (object) [
                    'message'      => $r->message,
                    'parentid'     => $r->parentid,
                    'deleted'      => $r->deleted ? get_string('yes') : get_string('no'),
                    'timecreated'  => $r->timecreated ? userdate($r->timecreated) : '',
                    'timemodified' => $r->timemodified ? userdate($r->timemodified) : '',
                ];
            }, array_values($records));

            writer::with_context($context)->export_data(
                [get_string('privacy:discussionpath', 'local_learnova_course')],
                (object) ['posts' => $data]
            );
        }
    }

    /**
     * Delete all discussion posts in a course context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_course) {
            return;
        }
        $DB->delete_records(discussion_manager::TABLE, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete a single user's posts across the approved course contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records(discussion_manager::TABLE,
                ['courseid' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete the posts of the approved users within a course context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select(discussion_manager::TABLE,
            "courseid = :courseid AND userid $insql", $params);
    }
}
