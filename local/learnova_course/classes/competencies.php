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
use core_competency\api;
use core_competency\course_competency;
use moodle_url;
use stdClass;

/**
 * Data builder for the Course Management "Competencies" panel.
 *
 * The course's competencies as a two-column card grid — name, framework path,
 * status, description — under a proficiency summary for the viewer, with a
 * slide-in picker for adding more.
 *
 * Every value comes from the native competency API ({@see api}), the same one
 * behind /admin/tool/lp/coursecompetencies.php: the course competency list, the
 * viewer's proficiency in the course, the framework path, and which activities
 * each competency is attached to. Adding goes through the native
 * core_competency_add_competency_to_course web service, so permissions and
 * events are Moodle's.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencies {

    /**
     * Build the competencies panel context.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function context(stdClass $course, context_course $context): array {
        global $USER;

        if (!api::is_enabled()) {
            return ['disabled' => true];
        }

        $courseid = (int) $course->id;

        // The viewer's own proficiency, exactly as the native page reports it —
        // only for someone who can actually be graded in this course.
        $gradable = is_enrolled($context, $USER, 'moodle/competency:coursecompetencygradable');
        $proficient = $gradable
            ? api::count_proficient_competencies_in_course_for_user($courseid, $USER->id) : 0;
        $total = api::count_competencies_in_course($courseid);

        $ruleoutcomes = course_competency::get_ruleoutcome_list();
        $usercomps    = $gradable ? api::list_user_competencies_in_course($courseid, $USER->id) : [];
        $modinfo      = get_fast_modinfo($course);

        $cards      = [];
        $activities = [];
        foreach (api::list_course_competencies($courseid) as $entry) {
            $competency = $entry['competency'];
            $cc         = $entry['coursecompetency'];
            $id         = (int) $competency->get('id');

            // Which activities in this course use it — the filter's options and
            // the per-card filter key both come from here.
            $cmids = [];
            foreach (api::list_course_modules_using_competency($id, $courseid) as $cmid) {
                if (!isset($modinfo->cms[$cmid])) {
                    continue;
                }
                $cmids[] = (int) $cmid;
                $activities[$cmid] = $modinfo->cms[$cmid]->get_formatted_name();
            }

            $cards[] = [
                'id'          => $id,
                'name'        => format_string($competency->get('shortname')),
                'path'        => self::path($competency),
                'description' => self::description($competency, $cc, $ruleoutcomes),
                'cmids'       => implode(' ', $cmids),
            ] + self::status($id, $cc, $usercomps, $gradable);
        }

        \core_collator::asort($activities);
        $activityoptions = [];
        foreach ($activities as $cmid => $name) {
            $activityoptions[] = ['value' => $cmid, 'label' => $name];
        }

        return [
            'disabled'   => false,
            'cards'      => $cards,
            'hascards'   => !empty($cards),
            'gradable'   => $gradable,
            'proficient' => $proficient,
            'total'      => $total,
            'percent'    => $total ? round($proficient / $total * 100) : 0,
            'summary'    => get_string('cp_summary', 'local_learnova_course',
                (object) ['proficient' => $proficient, 'total' => $total]),
            'activities'    => $activityoptions,
            'hasactivities' => !empty($activityoptions),
            'canadd'     => has_capability('moodle/competency:coursecompetencymanage', $context),
            'courseid'   => $courseid,
            'manageurl'  => (new moodle_url('/admin/tool/lp/competencyframeworks.php',
                ['pagecontextid' => $context->id]))->out(false),
            'frameworks' => self::frameworks($context, $cards),
        ];
    }

    /**
     * "Framework / idnumber" path line of a card.
     *
     * @param \core_competency\competency $competency
     * @return string
     */
    private static function path($competency): string {
        $framework = $competency->get_framework();
        $parts = [format_string($framework->get('shortname'))];
        if ($competency->get('idnumber')) {
            $parts[] = $competency->get('idnumber');
        }
        return implode(' / ', $parts);
    }

    /**
     * Card description: the competency's own description if it has one, else
     * what the course rule will do when it is met.
     *
     * @param \core_competency\competency $competency
     * @param \core_competency\course_competency $cc
     * @param array $ruleoutcomes
     * @return string
     */
    private static function description($competency, $cc, array $ruleoutcomes): string {
        $description = trim(html_to_text($competency->get('description'), 0, false));
        if ($description !== '') {
            return shorten_text($description, 180);
        }
        $outcome = (int) $cc->get('ruleoutcome');
        return (string) ($ruleoutcomes[$outcome] ?? '');
    }

    /**
     * Status badge for one card.
     *
     * "Met" is the viewer's own proficiency in this course; "Not configured" is
     * a course competency whose rule outcome is "Do nothing", i.e. nothing will
     * ever mark it automatically. Everything else is simply not met yet.
     *
     * @param int $competencyid
     * @param \core_competency\course_competency $cc
     * @param array $usercomps  user_competency_course records for the viewer.
     * @param bool $gradable
     * @return array{status: string, tone: string}
     */
    private static function status(int $competencyid, $cc, array $usercomps, bool $gradable): array {
        if ($gradable) {
            foreach ($usercomps as $usercomp) {
                if ((int) $usercomp->get('competencyid') === $competencyid && $usercomp->get('proficiency')) {
                    return [
                        'status' => get_string('cp_status_met', 'local_learnova_course'),
                        'tone'   => 'success',
                    ];
                }
            }
        }
        if ((int) $cc->get('ruleoutcome') === course_competency::OUTCOME_NONE) {
            return [
                'status' => get_string('cp_status_none', 'local_learnova_course'),
                'tone'   => 'muted',
            ];
        }
        return [
            'status' => get_string('cp_status_pending', 'local_learnova_course'),
            'tone'   => 'warn',
        ];
    }

    /**
     * Picker data: every framework the user may pick from, with its
     * competencies, flagged when the course already has them.
     *
     * Loaded up front rather than per keystroke: the slide-in then filters by
     * framework and search text with no round trip, which is the behaviour the
     * story asks for. Frameworks are capped so a very large one cannot blow up
     * the page.
     *
     * @param context_course $context
     * @param array $cards  Competencies already on the course.
     * @return array
     */
    private static function frameworks(context_course $context, array $cards): array {
        $already = array_column($cards, 'id');
        $out     = [];
        $first   = true;

        // 'parents': frameworks almost always live in the SYSTEM context, which
        // is a parent of the course — the default ('children') would find none
        // and leave the picker empty.
        foreach (api::list_frameworks('shortname', 'ASC', null, 100, $context, 'parents') as $framework) {
            $competencies = [];
            foreach (api::list_competencies(['competencyframeworkid' => $framework->get('id')]) as $competency) {
                $id = (int) $competency->get('id');
                $competencies[] = [
                    'id'      => $id,
                    'name'    => format_string($competency->get('shortname')),
                    'idnumber' => $competency->get('idnumber'),
                    'inuse'   => in_array($id, $already, true),
                ];
            }
            if (empty($competencies)) {
                continue;
            }
            $out[] = [
                'id'           => (int) $framework->get('id'),
                'name'         => format_string($framework->get('shortname')),
                'selected'     => $first,
                'competencies' => $competencies,
            ];
            $first = false;
        }
        return $out;
    }
}
