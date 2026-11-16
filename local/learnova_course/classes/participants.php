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
 * Data builder for the Course Management "Participants" panel.
 *
 * Renders the enrolled users as a custom Learnova table (the lm-* component
 * library) driven by real data: name, email, role(s), groups, course last
 * access and enrolment status. Search / role filter / group filter /
 * pagination are all server-side (the panel reloads with URL params); the
 * heavier actions (enrol, edit roles/groups, unenrol) hand off to Moodle's
 * native participants page.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants {

    /** @var int Rows per page. */
    const PER_PAGE = 20;

    /**
     * Build the participants panel context.
     *
     * @param stdClass $course
     * @param array $params  Filter/paging state: psearch, prole (roleid),
     *                       pgroup (groupid), ppage (0-based).
     * @return array
     */
    public static function context(stdClass $course, array $params): array {
        global $DB;

        $courseid = (int) $course->id;
        $context  = context_course::instance($courseid);

        $search  = trim($params['psearch'] ?? '');
        $roleid  = (int) ($params['prole'] ?? 0);
        $groupid = (int) ($params['pgroup'] ?? 0);
        $page   = max(0, (int) ($params['ppage'] ?? 0));

        // ── Base: enrolled users (active + suspended) joined to {user}. ──────
        // get_enrolled_sql returns NAMED params, so the WHERE uses named params
        // too (mixing named + ? positional is illegal in Moodle's DB layer). The
        // group filter is native to get_enrolled_sql (3rd arg).
        [$esql, $eparams] = get_enrolled_sql($context, '', $groupid, false);
        $params = $eparams;
        $where  = ['u.deleted = 0'];

        if ($search !== '') {
            $like = '%' . $DB->sql_like_escape(\core_text::strtolower($search)) . '%';
            $namelike  = $DB->sql_like($DB->sql_concat('LOWER(u.firstname)', "' '", 'LOWER(u.lastname)'), ':lvsearchn', false);
            $emaillike = $DB->sql_like('LOWER(u.email)', ':lvsearche', false);
            $where[] = "($namelike OR $emaillike)";
            $params['lvsearchn'] = $like;
            $params['lvsearche'] = $like;
        }
        if ($roleid > 0) {
            $where[] = "EXISTS (SELECT 1 FROM {role_assignments} ra
                                 WHERE ra.userid = u.id AND ra.contextid = :lvctx AND ra.roleid = :lvrole)";
            $params['lvctx']  = $context->id;
            $params['lvrole'] = $roleid;
        }
        $wheresql = implode(' AND ', $where);

        $fromsql = "FROM {user} u JOIN ($esql) je ON je.id = u.id WHERE $wheresql";
        $total = $DB->count_records_sql("SELECT COUNT(1) $fromsql", $params);

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT u.id, u.email, u.lastaccess, $userfields $fromsql
              ORDER BY u.firstname, u.lastname",
            $params, $page * self::PER_PAGE, self::PER_PAGE
        );

        // ── Per-page lookups (roles / groups / status / course last access). ─
        $userids = array_keys($rows);
        $roles   = self::roles_for($context, $userids);
        $groups  = self::groups_for($courseid, $userids);
        $active  = self::active_ids($courseid, $userids);
        $lastacc = self::course_lastaccess($courseid, $userids);
        $ueids   = self::ueids_for($courseid, $userids);

        $items = [];
        foreach ($rows as $u) {
            $isactive = isset($active[$u->id]);
            $items[] = [
                'id'         => $u->id,
                'ueids'      => implode(',', $ueids[$u->id] ?? []),
                'initials'   => self::initials($u),
                'name'       => fullname($u),
                'email'      => $u->email,
                'roles'      => $roles[$u->id] ?? [],
                'hasroles'   => !empty($roles[$u->id]),
                'groups'     => $groups[$u->id] ?? '',
                'lastaccess' => self::relative_time($lastacc[$u->id] ?? 0),
                'status_label' => $isactive
                    ? get_string('pt_status_active', 'local_learnova_course')
                    : get_string('pt_status_suspended', 'local_learnova_course'),
                'status_active' => $isactive,
            ];
        }

        // ── Toolbar state: role + group options + pager (URL-driven). The pager
        // links are plain GET links to this page that preserve the current
        // search + role + group; the filter form is a GET form whose hidden
        // fields keep id/pane. Every reload re-opens this panel.
        $base = new moodle_url('/local/learnova_course/management.php',
            ['id' => $courseid, 'pane' => 'participants']);
        $caneditroles = has_capability('moodle/role:assign', $context);
        $caneditgroups = has_capability('moodle/course:managegroups', $context);

        return [
            'rows'        => $items,
            'hasrows'     => !empty($items),
            'total'       => $total,
            'count_label' => get_string('pt_found', 'local_learnova_course', $total),
            'search'      => $search,
            'roleoptions'  => self::role_options($context, $roleid),
            'groupoptions' => self::group_options($courseid, $groupid),
            'pager'       => self::pager($total, $page, $base, $search, $roleid, $groupid),
            'canenrol'    => has_capability('enrol/manual:enrol', $context),
            'candelete'   => has_capability('enrol/manual:unenrol', $context),
            'canmessage'  => has_capability('moodle/course:bulkmessaging', $context),
            'canedit'     => $caneditroles || $caneditgroups,
            'nativeurl'   => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
            'formaction'  => (new moodle_url('/local/learnova_course/management.php'))->out(false),
            'courseid'    => $courseid,
            'contextid'   => $context->id,
        ];
    }

    /**
     * Context for the "Edit participant" modal body (rendered as a fragment):
     * the user's header plus assignable-role and course-group checkboxes with
     * their current membership pre-checked. The JS diffs checked vs the initial
     * (defaultChecked) state on save and calls the role/group web services.
     *
     * @param int $courseid
     * @param int $userid
     * @param context_course $context
     * @return array
     */
    public static function edit_context(int $courseid, int $userid, context_course $context): array {
        global $CFG, $DB;

        $user = \core_user::get_user($userid, '*', MUST_EXIST);

        $userroles = [];
        foreach (get_user_roles($context, $userid, false) as $ra) {
            $userroles[$ra->roleid] = true;
        }
        $roles = [];
        foreach (get_assignable_roles($context, ROLENAME_ALIAS) as $rid => $name) {
            $roles[] = ['id' => $rid, 'name' => $name, 'checked' => isset($userroles[$rid])];
        }

        $usergroups = groups_get_user_groups($courseid, $userid)[0] ?? [];
        $usergroups = array_fill_keys($usergroups, true);
        $groups = [];
        foreach (groups_get_all_groups($courseid) as $g) {
            $groups[] = ['id' => $g->id, 'name' => format_string($g->name), 'checked' => isset($usergroups[$g->id])];
        }

        // ── Enrolment(s): the REAL native enrol_user_enrolment_form per method
        // the current user may manage. Rendered as-is so its field names + sesskey
        // + qf marker are correct; the JS serialises + submits it via the native
        // core_enrol_submit_user_enrolment_form web service (our SCSS restyles it).
        require_once($CFG->dirroot . '/enrol/editenrolment_form.php');
        $enrolments = [];
        $uerecs = $DB->get_records_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = ? AND ue.userid = ?
              ORDER BY ue.id",
            [$courseid, $userid]
        );
        foreach ($uerecs as $uerec) {
            $ue = $DB->get_record('user_enrolments', ['id' => $uerec->id], '*', MUST_EXIST);
            $instance = $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST);
            $plugin = enrol_get_plugin($instance->enrol);
            if (!$plugin || !$plugin->allow_manage($instance)) {
                continue;
            }
            $mform = new \enrol_user_enrolment_form(null, [
                'ue'                => $ue,
                'modal'             => true,
                'enrolinstancename' => $plugin->get_instance_name($instance),
            ]);
            $enrolments[] = [
                'ueid'     => $ue->id,
                'method'   => $plugin->get_instance_name($instance),
                'formhtml' => $mform->render(),
            ];
        }

        return [
            'userid'        => $userid,
            'contextid'     => $context->id,
            'name'          => fullname($user),
            'email'         => $user->email,
            'initials'      => self::initials($user),
            'roles'         => $roles,
            'hasroles'      => !empty($roles),
            'groups'        => $groups,
            'hasgroups'     => !empty($groups),
            'enrolments'    => $enrolments,
            'hasenrolments' => !empty($enrolments),
        ];
    }

    /**
     * Role badges per user (role display name + a badge tone from the archetype).
     *
     * @param context_course $context
     * @param int[] $userids
     * @return array<int, array<int, array{name: string, tone: string}>>
     */
    private static function roles_for(context_course $context, array $userids): array {
        if (empty($userids)) {
            return [];
        }
        $names = role_get_names($context, ROLENAME_ALIAS);
        $archetypes = [];
        foreach (get_all_roles() as $r) {
            $archetypes[$r->id] = $r->archetype;
        }
        $tones = [
            'editingteacher' => 'teal', 'teacher' => 'teal',
            'manager' => 'teal', 'coursecreator' => 'teal',
            'student' => 'muted',
        ];

        $out = [];
        $assignments = get_users_roles($context, $userids, false);
        foreach ($assignments as $userid => $ras) {
            $seen = [];
            foreach ($ras as $ra) {
                if (isset($seen[$ra->roleid]) || !isset($names[$ra->roleid])) {
                    continue;
                }
                $seen[$ra->roleid] = true;
                $arch = $archetypes[$ra->roleid] ?? '';
                $out[$userid][] = [
                    'name' => $names[$ra->roleid]->localname,
                    'tone' => $tones[$arch] ?? 'muted',
                ];
            }
        }
        return $out;
    }

    /**
     * Comma-separated group names per user (empty string when none).
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, string>
     */
    private static function groups_for(int $courseid, array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $recs = $DB->get_records_sql(
            "SELECT gm.id, gm.userid, g.name
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id
              WHERE g.courseid = ? AND gm.userid $insql
              ORDER BY g.name",
            array_merge([$courseid], $inparams)
        );
        $out = [];
        foreach ($recs as $r) {
            $out[$r->userid][] = format_string($r->name);
        }
        return array_map(fn($n) => implode(', ', $n), $out);
    }

    /**
     * User ids (among the page) with at least one active, in-window enrolment.
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, bool> keyed by userid
     */
    private static function active_ids(int $courseid, array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $now = time();
        $recs = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = ? AND e.status = 0 AND ue.status = 0
                AND (ue.timestart = 0 OR ue.timestart <= ?)
                AND (ue.timeend = 0 OR ue.timeend >= ?)
                AND ue.userid $insql",
            array_merge([$courseid, $now, $now], $inparams)
        );
        return array_fill_keys(array_keys($recs), true);
    }

    /**
     * All user_enrolment ids per user in the course (for row-level unenrol).
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, int[]>
     */
    private static function ueids_for(int $courseid, array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $recs = $DB->get_records_sql(
            "SELECT ue.id, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = ? AND ue.userid $insql",
            array_merge([$courseid], $inparams)
        );
        $out = [];
        foreach ($recs as $r) {
            $out[$r->userid][] = (int) $r->id;
        }
        return $out;
    }

    /**
     * Course-level last access timestamp per user.
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, int>
     */
    private static function course_lastaccess(int $courseid, array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $recs = $DB->get_records_sql(
            "SELECT userid, timeaccess FROM {user_lastaccess}
              WHERE courseid = ? AND userid $insql",
            array_merge([$courseid], $inparams)
        );
        $out = [];
        foreach ($recs as $r) {
            $out[$r->userid] = (int) $r->timeaccess;
        }
        return $out;
    }

    /**
     * Role <select> options (course roles + "All roles"), current one selected.
     *
     * @param context_course $context
     * @param int $selected
     * @return array<int, array{value: int, label: string, selected: bool}>
     */
    private static function role_options(context_course $context, int $selected): array {
        $options = [[
            'value' => 0,
            'label' => get_string('pt_all_roles', 'local_learnova_course'),
            'selected' => $selected === 0,
        ]];
        foreach (get_viewable_roles($context) as $rid => $name) {
            $options[] = ['value' => $rid, 'label' => $name, 'selected' => $selected === (int) $rid];
        }
        return $options;
    }

    /**
     * Group <select> options (course groups + "All groups"), current selected.
     *
     * @param int $courseid
     * @param int $selected
     * @return array<int, array{value: int, label: string, selected: bool}>
     */
    private static function group_options(int $courseid, int $selected): array {
        $options = [[
            'value' => 0,
            'label' => get_string('pt_all_groups', 'local_learnova_course'),
            'selected' => $selected === 0,
        ]];
        foreach (groups_get_all_groups($courseid) as $g) {
            $options[] = ['value' => $g->id, 'label' => format_string($g->name), 'selected' => $selected === (int) $g->id];
        }
        return $options;
    }

    /**
     * Pagination model: page buttons + prev/next links + "showing X–Y of N".
     * Every link keeps the current search + role + group.
     *
     * @param int $total
     * @param int $page
     * @param moodle_url $base
     * @param string $search
     * @param int $roleid
     * @param int $groupid
     * @return array
     */
    private static function pager(int $total, int $page, moodle_url $base, string $search, int $roleid, int $groupid): array {
        $pages = (int) ceil($total / self::PER_PAGE);
        $from  = $total ? ($page * self::PER_PAGE + 1) : 0;
        $to    = min(($page + 1) * self::PER_PAGE, $total);

        $link = function (int $p) use ($base, $search, $roleid, $groupid): string {
            return (new moodle_url($base, [
                'psearch' => $search, 'prole' => $roleid, 'pgroup' => $groupid, 'ppage' => $p,
            ]))->out(false);
        };

        // Windowed page numbers (max 5 around the current page).
        $start = max(0, $page - 2);
        $end   = min($pages - 1, $start + 4);
        $start = max(0, $end - 4);
        $buttons = [];
        for ($i = $start; $i <= $end; $i++) {
            $buttons[] = ['num' => $i + 1, 'url' => $link($i), 'active' => $i === $page];
        }

        return [
            'has'       => $pages > 1,
            'buttons'   => $buttons,
            'has_prev'  => $page > 0,
            'prev_url'  => $link($page - 1),
            'has_next'  => $page < $pages - 1,
            'next_url'  => $link($page + 1),
            'range'     => get_string('pt_showing', 'local_learnova_course',
                (object) ['from' => $from, 'to' => $to, 'total' => $total]),
        ];
    }

    /**
     * Two-letter uppercase initials from a user's name.
     *
     * @param stdClass $u
     * @return string
     */
    private static function initials(stdClass $u): string {
        $f = \core_text::strtoupper(\core_text::substr(trim($u->firstname), 0, 1));
        $l = \core_text::strtoupper(\core_text::substr(trim($u->lastname), 0, 1));
        return ($f . $l) ?: '?';
    }

    /**
     * Coarse "x ago" relative time, or "Never".
     *
     * @param int $timestamp
     * @return string
     */
    private static function relative_time(int $timestamp): string {
        if ($timestamp <= 0) {
            return get_string('pt_never', 'local_learnova_course');
        }
        $diff = max(1, time() - $timestamp);
        $units = [
            ['s' => 31536000, 'k' => 'pt_ago_years'],
            ['s' => 2592000,  'k' => 'pt_ago_months'],
            ['s' => 86400,    'k' => 'pt_ago_days'],
            ['s' => 3600,     'k' => 'pt_ago_hours'],
            ['s' => 60,       'k' => 'pt_ago_minutes'],
        ];
        foreach ($units as $u) {
            if ($diff >= $u['s']) {
                return get_string($u['k'], 'local_learnova_course', (int) floor($diff / $u['s']));
            }
        }
        return get_string('pt_ago_now', 'local_learnova_course');
    }
}
