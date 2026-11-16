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

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Read/write helper for the custom discussion engine (Plan B).
 *
 * Single entry point for two surfaces, one data model:
 * * the Discussion tab on the course landing page (cmid = 0), and
 * * the Activity Discussion slide-over in focus mode (cmid = the module's).
 *
 * Posts are stored flat in {learnova_discussion_posts}; a non-zero parentid
 * marks a 1-level reply. Deletes are soft (the row stays, deleted = 1) so
 * threads never break, and deleting a thread takes its replies with it.
 *
 * The context passed in is the course context for the course tab and the module
 * context for the activity panel. Capabilities are inherited, so the same
 * capability check answers both; only the scope of the read/write differs.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_manager {

    /** @var string Database table holding discussion posts. */
    const TABLE = 'learnova_discussion_posts';

    /** @var int Maximum message length in characters. */
    const MAX_MESSAGE_LENGTH = 2000;

    /** @var int Characters after which a derived thread title is truncated. */
    const TITLE_LENGTH = 70;

    /** @var int Characters after which a thread snippet is truncated. */
    const SNIPPET_LENGTH = 120;

    /**
     * Whether the current user can moderate (delete any post) in this scope.
     *
     * @param \context $context Course or module context.
     * @return bool
     */
    public static function can_moderate(\context $context): bool {
        return has_capability('local/learnova_course:moderatediscussion', $context);
    }

    /**
     * Whether the current user can post/reply in this scope.
     *
     * Enrolled participants and moderators (teachers/managers) may post; guests
     * and non-enrolled visitors are read-only.
     *
     * @param \context $context Course or module context.
     * @return bool
     */
    public static function can_post(\context $context): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        return is_enrolled($context, null, '', true) || self::can_moderate($context);
    }

    /**
     * Fetch the visible discussion feed for one scope, newest thread first.
     *
     * @param int      $courseid
     * @param \context $context Course or module context.
     * @param int      $cmid    0 for the course-level feed, else the module's id.
     * @return array[] List of post export rows (see {@see self::export_post()}).
     */
    public static function get_posts(int $courseid, \context $context, int $cmid = 0): array {
        global $DB;

        $records = $DB->get_records(self::TABLE,
            ['courseid' => $courseid, 'cmid' => $cmid, 'deleted' => 0], 'timecreated ASC, id ASC');
        if (!$records) {
            return [];
        }

        // Order threads newest-first (latest top-level post on top) while keeping
        // each thread's replies grouped under their parent in chronological order.
        // $records is timecreated ASC, so replies collected here stay oldest-first.
        $tops = [];
        $repliesbyparent = [];
        foreach ($records as $record) {
            if ((int) $record->parentid === 0) {
                $tops[] = $record;
            } else {
                $repliesbyparent[(int) $record->parentid][] = $record;
            }
        }
        $ordered = [];
        foreach (array_reverse($tops) as $top) {
            $ordered[] = $top;
            foreach ($repliesbyparent[(int) $top->id] ?? [] as $reply) {
                $ordered[] = $reply;
            }
            unset($repliesbyparent[(int) $top->id]);
        }
        // Any replies whose parent is gone (e.g. soft-deleted) — keep them visible
        // at the end rather than silently dropping them.
        foreach ($repliesbyparent as $orphans) {
            foreach ($orphans as $reply) {
                $ordered[] = $reply;
            }
        }

        // Batch-load the authors to avoid a query per post.
        $userids = array_values(array_unique(array_map(fn($r) => (int) $r->userid, $ordered)));
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $users = $DB->get_records_select('user', "id $insql", $params);

        $canmoderate = self::can_moderate($context);
        $canpost = self::can_post($context);
        $posts = [];
        foreach ($ordered as $record) {
            $author = $users[$record->userid] ?? null;
            $posts[] = self::export_post($record, $context, $canmoderate, $canpost, $author);
        }
        return $posts;
    }

    /**
     * Create a post or a 1-level reply.
     *
     * @param int      $courseid
     * @param \context $context  Course or module context.
     * @param int      $userid   Author.
     * @param string   $message  Plain-text body.
     * @param int      $parentid 0 for a top-level post, else the top-level post id.
     * @param int      $cmid     0 for the course-level feed, else the module's id.
     * @return array The created post, exported for the current viewer.
     */
    public static function create_post(int $courseid, \context $context,
            int $userid, string $message, int $parentid = 0, int $cmid = 0): array {
        global $DB;

        $message = trim($message);
        if ($message === '') {
            throw new \moodle_exception('disc_error_empty', 'local_learnova_course');
        }
        if (\core_text::strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new \moodle_exception('disc_error_toolong', 'local_learnova_course', '', self::MAX_MESSAGE_LENGTH);
        }

        if ($parentid > 0) {
            $parent = $DB->get_record(self::TABLE,
                ['id' => $parentid, 'courseid' => $courseid, 'cmid' => $cmid, 'deleted' => 0]);
            if (!$parent) {
                throw new \moodle_exception('disc_error_parentmissing', 'local_learnova_course');
            }
            // Enforce a single level of nesting: you can only reply to a top-level post.
            if ((int) $parent->parentid !== 0) {
                throw new \moodle_exception('disc_error_nestedreply', 'local_learnova_course');
            }
        }

        $now = time();
        $record = (object) [
            'courseid'     => $courseid,
            'cmid'         => $cmid,
            'userid'       => $userid,
            'parentid'     => $parentid,
            'message'      => $message,
            'deleted'      => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $record->id = (int) $DB->insert_record(self::TABLE, $record);

        return self::export_post($record, $context, self::can_moderate($context), self::can_post($context));
    }

    /**
     * Soft-delete a post (moderators only).
     *
     * Deleting a top-level post takes its replies with it: a thread is deleted
     * as a whole, so no reply is left behind pointing at something the reader
     * can no longer see.
     *
     * @param int      $postid
     * @param \context $context Course or module context the post belongs to.
     * @return int The course id the post belonged to.
     */
    public static function delete_post(int $postid, \context $context): int {
        global $DB;

        require_capability('local/learnova_course:moderatediscussion', $context);

        $post = $DB->get_record(self::TABLE, ['id' => $postid], '*', MUST_EXIST);
        // The capability was checked against the caller's context, so the post
        // has to actually live in that course for the check to mean anything.
        if ((int) $post->courseid !== (int) $context->get_course_context()->instanceid) {
            throw new \moodle_exception('disc_error_wrongcourse', 'local_learnova_course');
        }

        $now = time();
        if (!$post->deleted) {
            $DB->update_record(self::TABLE, (object) [
                'id'           => $post->id,
                'deleted'      => 1,
                'timemodified' => $now,
            ]);
        }
        if ((int) $post->parentid === 0) {
            $DB->set_field_select(self::TABLE, 'deleted', 1,
                'parentid = :parentid AND deleted = 0', ['parentid' => $post->id]);
            $DB->set_field_select(self::TABLE, 'timemodified', $now,
                'parentid = :parentid', ['parentid' => $post->id]);
        }
        return (int) $post->courseid;
    }

    /**
     * The visible discussion of one activity, as threads rather than a flat feed.
     *
     * The focus-mode panel lists topics (title, reply count, snippet) and opens
     * one at a time, so the grouping the feed leaves implicit is made explicit
     * here. Same rows, same soft-delete rule — only the shape differs.
     *
     * @param int      $courseid
     * @param int      $cmid
     * @param \context $context Module context of the activity.
     * @return array[] Thread export rows (see {@see self::export_thread()}).
     */
    public static function get_threads(int $courseid, int $cmid, \context $context): array {
        global $DB;

        $records = $DB->get_records(self::TABLE,
            ['courseid' => $courseid, 'cmid' => $cmid, 'deleted' => 0], 'timecreated ASC, id ASC');
        if (!$records) {
            return [];
        }

        $tops = [];
        $repliesbyparent = [];
        foreach ($records as $record) {
            if ((int) $record->parentid === 0) {
                $tops[] = $record;
            } else {
                $repliesbyparent[(int) $record->parentid][] = $record;
            }
        }
        if (!$tops) {
            return [];
        }

        // One query for every author on the panel rather than one per post.
        $userids = array_values(array_unique(array_map(fn($r) => (int) $r->userid, $records)));
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $users = $DB->get_records_select('user', "id $insql", $params);

        $canmoderate = self::can_moderate($context);
        $canpost = self::can_post($context);

        $threads = [];
        // Newest topic on top, mirroring the course feed's ordering.
        foreach (array_reverse($tops) as $top) {
            $threads[] = self::export_thread($top, $repliesbyparent[(int) $top->id] ?? [],
                $context, $canmoderate, $canpost, $users);
        }
        return $threads;
    }

    /**
     * How many visible posts (topics plus replies) one activity's discussion holds.
     *
     * Drives the count badge on the Discussion trigger, so it is a count query
     * rather than a load of the panel's data.
     *
     * @param int $courseid
     * @param int $cmid
     * @return int
     */
    public static function count_posts(int $courseid, int $cmid): int {
        global $DB;
        return (int) $DB->count_records(self::TABLE,
            ['courseid' => $courseid, 'cmid' => $cmid, 'deleted' => 0]);
    }

    /**
     * Build a template/AJAX-ready row for one thread and its replies.
     *
     * The composer is a bare textarea (no subject field), so the title is taken
     * from the opening message: its first line, shortened. The snippet shows the
     * latest voice in the thread — the newest reply, or the opening post when
     * there is none.
     *
     * @param \stdClass   $top         The top-level post.
     * @param \stdClass[] $replies     Its replies, oldest first.
     * @param \context    $context
     * @param bool        $canmoderate Whether the viewer may delete threads.
     * @param bool        $canpost     Whether the viewer may post/reply.
     * @param \stdClass[] $users       Pre-loaded author records, keyed by id.
     * @return array
     */
    public static function export_thread(\stdClass $top, array $replies, \context $context,
            bool $canmoderate, bool $canpost, array $users = []): array {

        $starter = $users[$top->userid] ?? null;
        $startername = $starter ? fullname($starter)
            : get_string('disc_unknown_user', 'local_learnova_course');

        $exportedreplies = [];
        foreach ($replies as $reply) {
            $author = $users[$reply->userid] ?? null;
            $exportedreplies[] = [
                'id'      => (int) $reply->id,
                'name'    => $author ? fullname($author)
                    : get_string('disc_unknown_user', 'local_learnova_course'),
                'time'    => userdate($reply->timecreated, get_string('strftimedatetimeshort')),
                'message' => nl2br(s($reply->message)),
            ];
        }

        // Latest voice first: the newest reply if there is one, else the opener.
        $last = $replies ? end($replies) : $top;
        $lastauthor = $users[$last->userid] ?? null;
        $lastname = $lastauthor ? fullname($lastauthor)
            : get_string('disc_unknown_user', 'local_learnova_course');

        $replycount = count($replies);

        return [
            'id'         => (int) $top->id,
            'title'      => self::derive_title($top->message),
            'snippet'    => $lastname . ': ' . shorten_text(trim($last->message), self::SNIPPET_LENGTH),
            'message'    => nl2br(s($top->message)),
            'author'     => $startername,
            'time'       => userdate($top->timecreated, get_string('strftimedatetimeshort')),
            'meta'       => get_string('disc_thread_meta', 'local_learnova_course', (object) [
                'author' => $startername,
                'time'   => userdate($top->timecreated, get_string('strftimedatetimeshort')),
            ]),
            'replycount' => $replycount,
            'replylabel' => self::reply_label($replycount),
            'replies'    => $exportedreplies,
            'hasreplies' => $replycount > 0,
            'candelete'  => $canmoderate,
            'canreply'   => $canpost,
        ];
    }

    /**
     * The external_single_structure describing one exported thread, shared by the
     * AJAX endpoint so the return shape stays in lockstep with export_thread().
     *
     * @return external_single_structure
     */
    public static function thread_structure(): external_single_structure {
        return new external_single_structure([
            'id'         => new external_value(PARAM_INT, 'Top-level post id.'),
            'title'      => new external_value(PARAM_TEXT, 'Thread title, derived from the opening message.'),
            'snippet'    => new external_value(PARAM_TEXT, 'Author and excerpt of the latest post in the thread.'),
            'message'    => new external_value(PARAM_RAW, 'Escaped opening message HTML (line breaks only).'),
            'author'     => new external_value(PARAM_TEXT, 'Full name of the thread starter.'),
            'time'       => new external_value(PARAM_TEXT, 'Human-readable start time.'),
            'meta'       => new external_value(PARAM_TEXT, 'Started-by line.'),
            'replycount' => new external_value(PARAM_INT, 'Number of visible replies.'),
            'replylabel' => new external_value(PARAM_TEXT, 'Human-readable reply count.'),
            'replies'    => new external_multiple_structure(new external_single_structure([
                'id'      => new external_value(PARAM_INT, 'Reply post id.'),
                'name'    => new external_value(PARAM_TEXT, 'Author full name.'),
                'time'    => new external_value(PARAM_TEXT, 'Human-readable timestamp.'),
                'message' => new external_value(PARAM_RAW, 'Escaped message HTML (line breaks only).'),
            ]), 'Replies, oldest first.'),
            'hasreplies' => new external_value(PARAM_BOOL, 'Whether the thread has replies.'),
            'candelete'  => new external_value(PARAM_BOOL, 'Whether the viewer may delete this thread.'),
            'canreply'   => new external_value(PARAM_BOOL, 'Whether the viewer may reply to this thread.'),
        ], 'A single discussion thread.');
    }

    /**
     * A thread title taken from the body of its opening post.
     *
     * @param string $message
     * @return string
     */
    private static function derive_title(string $message): string {
        $firstline = trim((string) strtok(trim($message), "\n"));
        if ($firstline === '') {
            return get_string('disc_thread_untitled', 'local_learnova_course');
        }
        return shorten_text($firstline, self::TITLE_LENGTH);
    }

    /**
     * The "2 replies" / "1 reply" / "No replies" label for a thread.
     *
     * @param int $count
     * @return string
     */
    private static function reply_label(int $count): string {
        if ($count === 0) {
            return get_string('disc_thread_noreply', 'local_learnova_course');
        }
        if ($count === 1) {
            return get_string('disc_thread_reply', 'local_learnova_course');
        }
        return get_string('disc_thread_replies', 'local_learnova_course', $count);
    }

    /**
     * Delete all posts for a course (e.g. on course deletion).
     *
     * @param int $courseid
     */
    public static function delete_course_posts(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Delete the activity-scoped posts of one course module (on its deletion).
     *
     * @param int $cmid
     */
    public static function delete_module_posts(int $cmid): void {
        global $DB;
        if ($cmid <= 0) {
            return;
        }
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Build a template/AJAX-ready row for one post.
     *
     * @param \stdClass      $post        Raw DB record.
     * @param \context       $context     Course or module context.
     * @param bool           $canmoderate Whether the viewer may delete posts.
     * @param bool           $canpost     Whether the viewer may post/reply.
     * @param \stdClass|null $author      Pre-loaded user record, or null to load it.
     * @return array
     */
    public static function export_post(\stdClass $post, \context $context,
            bool $canmoderate, bool $canpost, ?\stdClass $author = null): array {
        global $PAGE, $DB;

        if ($author === null) {
            $author = $DB->get_record('user', ['id' => $post->userid]);
        }

        if ($author) {
            $fullname = fullname($author);
            $initials = self::initials($author);
            $picture = new \user_picture($author);
            $picture->size = 64;
            $avatarurl = $picture->get_url($PAGE)->out(false);
            // The author counts as an instructor when they can moderate the course.
            $isteacher = has_capability('local/learnova_course:moderatediscussion', $context, $author->id);
        } else {
            $fullname = get_string('disc_unknown_user', 'local_learnova_course');
            $initials = '?';
            $avatarurl = '';
            $isteacher = false;
        }

        $isreply = ((int) $post->parentid) > 0;

        return [
            'id'        => (int) $post->id,
            'parentid'  => (int) $post->parentid,
            'isreply'   => $isreply,
            'userid'    => (int) $post->userid,
            'name'      => $fullname,
            'initials'  => $initials,
            'avatarurl' => $avatarurl,
            'hasavatar' => $avatarurl !== '',
            'isteacher' => $isteacher,
            'time'      => userdate($post->timecreated, get_string('strftimedatetimeshort')),
            'message'   => nl2br(s($post->message)),
            'candelete' => $canmoderate,
            // You may reply only to top-level posts, and only when you can post.
            'canreply'  => $canpost && !$isreply,
        ];
    }

    /**
     * The external_single_structure describing one exported post, shared by the
     * AJAX endpoints so the return shape stays in lockstep with export_post().
     *
     * @return external_single_structure
     */
    public static function post_structure(): external_single_structure {
        return new external_single_structure([
            'id'        => new external_value(PARAM_INT, 'Post id.'),
            'parentid'  => new external_value(PARAM_INT, 'Parent post id, 0 for a top-level post.'),
            'isreply'   => new external_value(PARAM_BOOL, 'Whether this post is a reply.'),
            'userid'    => new external_value(PARAM_INT, 'Author user id.'),
            'name'      => new external_value(PARAM_TEXT, 'Author full name.'),
            'initials'  => new external_value(PARAM_TEXT, 'Author initials (avatar fallback).'),
            'avatarurl' => new external_value(PARAM_URL, 'Author avatar URL, empty if none.'),
            'hasavatar' => new external_value(PARAM_BOOL, 'Whether an avatar URL is present.'),
            'isteacher' => new external_value(PARAM_BOOL, 'Whether the author is an instructor.'),
            'time'      => new external_value(PARAM_TEXT, 'Human-readable timestamp.'),
            'message'   => new external_value(PARAM_RAW, 'Escaped message HTML (line breaks only).'),
            'candelete' => new external_value(PARAM_BOOL, 'Whether the viewer may delete this post.'),
            'canreply'  => new external_value(PARAM_BOOL, 'Whether the viewer may reply to this post.'),
        ], 'A single discussion post.');
    }

    /**
     * Two-letter initials for a user, used as the avatar fallback.
     *
     * @param \stdClass $user
     * @return string
     */
    private static function initials(\stdClass $user): string {
        $first = \core_text::strtoupper(\core_text::substr(trim((string) $user->firstname), 0, 1));
        $last = \core_text::strtoupper(\core_text::substr(trim((string) $user->lastname), 0, 1));
        $initials = $first . $last;
        return $initials !== '' ? $initials : '?';
    }
}
