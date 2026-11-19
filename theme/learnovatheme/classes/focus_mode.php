<?php
// This file is part of the Learnova theme.

/**
 * Focus mode renderable helper.
 *
 * Gathers real course data (table of contents, completion, progress and
 * previous/next navigation) for the distraction-free activity view rendered
 * by layout/focusmode.php. Mirrors the learnova_course_focus_mode mockup.
 *
 * @package    theme_learnovatheme
 */

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use completion_info;
use context_module;
use core_availability\info;
use core_completion\cm_completion_details;
use moodle_page;
use moodle_url;
use renderer_base;
use section_info;

/**
 * Builds the template context for the focus-mode layout.
 */
class focus_mode {

    /**
     * Activity types excluded from focus mode (communication/social channels
     * like announcements and discussion forums that do not belong to the sequential
     * course learning curriculum).
     */
    public const EXCLUDED_MODS = ['forum', 'chat', 'label', 'subsection'];

    /**
     * Whether the focus-mode chrome should replace the standard layout for the
     * current page. Only real activity *view* pages qualify, and only when the
     * teacher is not editing (so edit mode falls back to the normal layout).
     *
     * @param moodle_page $page
     * @return bool
     */
    public static function applies(moodle_page $page): bool {
        // Read $page->cm into a local first: direct access invokes the magic
        // getter, whereas empty($page->cm) would always be true because
        // moodle_page defines __get but no __isset.
        $cm = $page->cm;
        if (!$cm) {
            return false;
        }
        if ($page->user_is_editing()) {
            return false;
        }
        // Exclude communication modules like announcements and discussion forums.
        if (in_array($cm->modname, self::EXCLUDED_MODS, true)) {
            return false;
        }
        $pagetype = (string) $page->pagetype;
        if (in_array($pagetype, self::EXTRA_PAGETYPES, true)
                || in_array($pagetype, focus_quiz::PAGETYPES, true)) {
            return true;
        }
        return strpos($pagetype, 'mod-') === 0 && substr($pagetype, -5) === '-view';
    }

    /**
     * Non-"view" activity pages that still belong inside the focus chrome.
     */
    private const EXTRA_PAGETYPES = [];

    /**
     * Builds the full template context.
     *
     * @param moodle_page $page
     * @param renderer_base $output
     * @return array
     */
    public static function context(moodle_page $page, renderer_base $output): array {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot . '/lib/completionlib.php');

        $course = $page->course;
        $currentcm = $page->cm;
        $userid = $USER->id;

        $modinfo = get_fast_modinfo($course);
        $completion = new completion_info($course);
        $completionon = $completion->is_enabled();

        // Pre-fetch activity progress data across the course for this user.
        $finishedquizzes = [];
        $submittedassigns = [];
        $finishedh5p = [];
        $finishedscorm = [];
        $viewedcontextids = [];

        try {
            // Finished quiz attempts.
            $quizsql = "SELECT DISTINCT q.id FROM {quiz} q
                          JOIN {quiz_attempts} qa ON qa.quiz = q.id
                         WHERE q.course = ? AND qa.userid = ? AND qa.state = 'finished'";
            $finishedquizzes = array_flip($DB->get_fieldset_sql($quizsql, [$course->id, $userid]));
        } catch (\Throwable $e) {}

        try {
            // Submitted assignments.
            $assignsql = "SELECT DISTINCT a.id FROM {assign} a
                            JOIN {assign_submission} s ON s.assignment = a.id
                           WHERE a.course = ? AND s.userid = ? AND s.status = 'submitted'";
            $submittedassigns = array_flip($DB->get_fieldset_sql($assignsql, [$course->id, $userid]));
        } catch (\Throwable $e) {}

        try {
            // H5P attempts.
            $h5psql = "SELECT DISTINCT h.id FROM {h5pactivity} h
                         JOIN {h5pactivity_attempts} ha ON ha.h5pactivityid = h.id
                        WHERE h.course = ? AND ha.userid = ?";
            $finishedh5p = array_flip($DB->get_fieldset_sql($h5psql, [$course->id, $userid]));
        } catch (\Throwable $e) {}

        try {
            // SCORM completions.
            $scormsql = "SELECT DISTINCT s.id FROM {scorm} s
                           JOIN {scorm_scoes_track} st ON st.scormid = s.id
                          WHERE s.course = ? AND st.userid = ?
                            AND st.element IN ('cmi.core.lesson_status', 'cmi.completion_status')
                            AND st.value IN ('completed', 'passed')";
            $finishedscorm = array_flip($DB->get_fieldset_sql($scormsql, [$course->id, $userid]));
        } catch (\Throwable $e) {}

        try {
            // Viewed context modules from logstore.
            $viewedcontextids = array_flip($DB->get_fieldset_select(
                'logstore_standard_log',
                'DISTINCT contextid',
                'courseid = ? AND userid = ? AND action = ? AND contextlevel = ?',
                [$course->id, $userid, 'viewed', CONTEXT_MODULE]
            ));
        } catch (\Throwable $e) {}

        $flat = [];            // Ordered accessible cms for prev/next.
        $totaltracked = 0;     // All activities in the course.
        $donetracked = 0;      // Completed activities.
        $totallisted = 0;      // Every activity shown in the table of contents.
        $modules = [];         // Sidebar "modules" (course sections).
        $modnum = 0;           // Running visible-section index for the badge.

        $currentsectionname = '';
        $currentactindex = 1;
        $currentsectionacttotal = 1;
        $currentduration = '';
        $iscurrentdone = false;

        foreach ($modinfo->get_section_info_all() as $section) {
            // Skip fully hidden sections that the user cannot see at all.
            if (!$section->uservisible && empty($section->availableinfo)) {
                continue;
            }

            $cmids = $modinfo->sections[$section->section] ?? [];
            $activities = [];
            $sectionhascurrent = false;
            $sectionalldone = true;
            $sectionhastracked = false;

            foreach ($cmids as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (in_array($cm->modname, self::EXCLUDED_MODS, true)) {
                    continue;
                }
                if (!$cm->has_view() || !empty($cm->deletioninprogress)) {
                    continue;
                }
                // Show accessible activities, plus restricted ones that still
                // advertise themselves (locked rows in the table of contents).
                if (!$cm->is_visible_on_course_page() && empty($cm->availableinfo)) {
                    continue;
                }

                $iscurrent = ((int) $cm->id === (int) $currentcm->id);
                $islocked = !$cm->uservisible;
                $isdone = false;

                // 1. Check Moodle native completion if configured.
                $details = null;
                if ($completionon && $cm->uservisible) {
                    $details = cm_completion_details::get_instance($cm, $userid);
                    if ($details->has_completion() && $details->is_overall_complete()) {
                        $isdone = true;
                    }
                }

                // 2. Activity-specific completion rules.
                if (!$isdone && $cm->uservisible) {
                    if ($cm->modname === 'quiz') {
                        // For quiz, only consider it done if the learner has actually completed an attempt.
                        if (isset($finishedquizzes[$cm->instance])) {
                            $isdone = true;
                        }
                    } else if ($cm->modname === 'assign') {
                        // For assignments, complete if submitted.
                        if (isset($submittedassigns[$cm->instance])) {
                            $isdone = true;
                        }
                    } else if ($cm->modname === 'h5pactivity') {
                        if (isset($finishedh5p[$cm->instance])) {
                            $isdone = true;
                        }
                    } else if ($cm->modname === 'scorm') {
                        if (isset($finishedscorm[$cm->instance])) {
                            $isdone = true;
                        }
                    } else if (in_array($cm->modname, ['page', 'resource', 'url', 'book', 'folder'])) {
                        // For reading and viewable content, viewing it counts as completion.
                        $cmcontext = \context_module::instance($cm->id, IGNORE_MISSING);
                        $hasviewlog = $cmcontext && isset($viewedcontextids[$cmcontext->id]);
                        if ($iscurrent || $hasviewlog) {
                            $isdone = true;
                        }
                    }
                }

                // Keep Moodle manual completion in sync for completed activities.
                if ($completionon && $cm->uservisible && $isdone && $details && $details->has_completion() && $details->is_manual()) {
                    if (!$details->is_overall_complete()) {
                        try {
                            $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
                        } catch (\Throwable $e) {}
                    }
                }

                // Every activity in the course is treated as an activity.
                $totaltracked++;
                $sectionhastracked = true;
                if ($isdone) {
                    $donetracked++;
                } else {
                    $sectionalldone = false;
                }

                if ($iscurrent) {
                    $iscurrentdone = $isdone;
                }

                if ($cm->uservisible && $cm->url) {
                    $flat[] = $cm;
                }

                // Use the Learnova colored icon set (same mapping as the course
                // landing activity list) instead of Moodle's default icon.
                $icontype = course_landing::activity_icon_type($cm);

                // Restriction caption under the name — the real reason straight
                // from the availability API ("Not available unless: ...").
                $restriction = self::restriction_text($cm->availableinfo, $course);

                $secactindex = count($activities) + 1;
                $duration = self::activity_duration($cm);

                if ($iscurrent) {
                    $sectionhascurrent = true;
                    $currentsectionname = html_entity_decode(get_section_name($course, $section), ENT_QUOTES, 'UTF-8');
                    $currentactindex = $secactindex;
                    $currentduration = $duration;
                }

                $activities[] = [
                    'id'             => (int) $cm->id,
                    'cmid'           => (int) $cm->id,
                    'name'           => self::clean_activity_name($cm),
                    'url'            => course_landing::activity_url($cm),
                    'hasurl'         => (bool) $cm->url,
                    'iconurl'        => course_landing::act_icon_url($icontype),
                    'icontype'       => $icontype,
                    'modname'        => $cm->modname,
                    'iscurrent'      => $iscurrent,
                    'isdone'         => $isdone,
                    'islocked'       => $islocked,
                    'isavailable'    => !$islocked && !$isdone && !$iscurrent,
                    'restriction'    => $restriction,
                    'hasrestriction' => $restriction !== '',
                    'duration'       => $duration,
                    'hasduration'    => $duration !== '',
                    'actindex'       => $secactindex,
                ];
                $totallisted++;
            }

            if (empty($activities)) {
                continue;
            }

            if ($sectionhascurrent) {
                $currentsectionacttotal = count($activities);
            }

            $modnum++;
            $islockedsection = !$section->uservisible;
            $isdonesection = $sectionhastracked && $sectionalldone && !$sectionhascurrent && !$islockedsection;

            // Status word shown after the activity count in the module header.
            if ($islockedsection) {
                $status = get_string('fm_status_locked', 'theme_learnovatheme');
            } else if ($isdonesection) {
                $status = get_string('fm_status_completed', 'theme_learnovatheme');
            } else if ($sectionhascurrent) {
                $status = get_string('fm_status_progress', 'theme_learnovatheme');
            } else {
                $status = get_string('fm_status_open', 'theme_learnovatheme');
            }

            $sectionrestriction = self::restriction_text($section->availableinfo, $course);

            $modules[] = [
                'name'           => html_entity_decode(get_section_name($course, $section), ENT_QUOTES, 'UTF-8'),
                'num'            => $modnum,
                'count'          => count($activities),
                'meta'           => get_string('fm_module_meta', 'theme_learnovatheme', (object) [
                    'count'  => count($activities),
                    'status' => $status,
                ]),
                'activities'     => $activities,
                'iscurrent'      => $sectionhascurrent,
                'isdone'         => $isdonesection,
                'islocked'       => $islockedsection,
                'isopen'         => $sectionhascurrent,
                'restriction'    => $sectionrestriction,
                'hasrestriction' => $sectionrestriction !== '',
            ];
        }

        if (empty($currentsectionname)) {
            $currentsectionname = html_entity_decode(format_string($course->fullname), ENT_QUOTES, 'UTF-8');
            $currentactindex = 1;
            $currentsectionacttotal = max(1, $totallisted);
        }
        $currentactivitysub = get_string('fm_breadcrumb_sub', 'theme_learnovatheme', (object) [
            'index' => $currentactindex,
            'total' => $currentsectionacttotal,
        ]);
        $useravatar = '';
        if (isloggedin() && !isguestuser()) {
            $useravatar = $output->user_picture($USER, ['size' => 32, 'link' => false]);
        }

        // Course-wide progress across tracked activities. The topbar shows the
        // raw "x of y activities" count, not a percentage — the bar is decoration.
        $hasprogress = $totaltracked > 0;
        $percent = $hasprogress ? (int) round(($donetracked / $totaltracked) * 100) : 0;

        // Two-state course label. Course completion criteria win when the site
        // defines them; otherwise "everything tracked is done" is the signal.
        $coursedone = ($completionon && $completion->is_course_complete($userid))
            || ($hasprogress && $donetracked === $totaltracked);

        // Previous / next within the flattened ordered activity list.
        $hasprev = $hasnext = false;
        $prev = $next = null;
        foreach ($flat as $i => $cm) {
            if ((int) $cm->id === (int) $currentcm->id) {
                if ($i > 0) {
                    $prev = $flat[$i - 1];
                    $hasprev = true;
                }
                if ($i < count($flat) - 1) {
                    $next = $flat[$i + 1];
                    $hasnext = true;
                }
                break;
            }
        }

        // Status indicator is read-only (learners cannot manually toggle mark done).
        // Everything is tracked through real activity completion.

        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

        // Gear icon — only rendered when it has a real destination, so learners
        // never get a dead control. Teachers land on the activity settings form.
        $cansettings = has_capability('moodle/course:manageactivities', context_module::instance($currentcm->id));
        $settingsurl = $cansettings
            ? (new moodle_url('/course/modedit.php', ['update' => $currentcm->id, 'return' => 1]))->out(false)
            : '';

        $currenticontype = course_landing::activity_icon_type($currentcm);

        // Intro strip description — the module's own intro, already formatted by
        // core. Rendering it here is safe because the focus layout never emits
        // full_header, so this is the only place it appears.
        $description = (string) ($page->activityheader->export_for_template($output)['description'] ?? '');
        if (trim(html_to_text($description, 0, false)) === '') {
            // Resource-style modules (Page, File, URL, Folder) blank the header
            // description when their "display description" option is off, because
            // core would otherwise print it above their content. Focus mode never
            // renders full_header, so honouring that blanking would hide the
            // description everywhere — read it from the module instead. The
            // accordion is collapsed by default, so nothing is forced on anyone.
            $description = self::module_intro($currentcm);
        }

        $kebab = self::admin_menu($page);

        // Content mode: Page/Book/Assignment/Forum read as a centered card;
        // File/SCORM fill the width as a player; a quiz attempt gets its own
        // column with the progress indicator above the questions.
        $blank = ['isdocument' => false, 'ismedia' => false, 'isquiz' => false, 'suppressnative' => false];
        if (focus_quiz::applies($page)) {
            $content = focus_quiz::context($page) + $blank;
        } else if (focus_document::applies($currentcm)) {
            $content = focus_document::context($page) + $blank;
        } else {
            $content = $blank;
        }

        return $content + focus_discussion::context($course, $currentcm) + [
            'coursename'       => html_entity_decode(format_string($course->fullname), ENT_QUOTES, 'UTF-8'),
            'courseurl'        => $courseurl->out(false),
            'currentname'      => self::clean_activity_name($currentcm),
            'currenticonurl'   => course_landing::act_icon_url($currenticontype),
            'currenticontype'  => $currenticontype,
            'typelabel'        => course_landing::activity_type_label($currentcm),
            'typecategory'     => course_landing::category_for_icon_type($currenticontype),
            'modules'          => $modules,

            'hasdescription'   => trim(html_to_text($description, 0, false)) !== '',
            'description'      => $description,

            'kebab'            => $kebab,
            'haskebab'         => !empty($kebab),

            'coursedone'       => $coursedone,
            'hasprogress'      => $hasprogress,
            'percent'          => $percent,
            'donecount'        => $donetracked,
            'totalcount'       => $totaltracked,
            'progresslabel'    => get_string('fm_progress_label', 'theme_learnovatheme', (object) [
                'done'  => $donetracked,
                'total' => $totaltracked,
            ]),
            'tocsub'           => get_string('fm_toc_sub', 'theme_learnovatheme', (object) [
                'modules'    => count($modules),
                'activities' => $totallisted,
            ]),

            'hassettings'      => $cansettings,
            'settingsurl'      => $settingsurl,

            'iscurrentdone'    => $iscurrentdone,

            'hasprev'          => $hasprev,
            'prevurl'          => $hasprev ? course_landing::activity_url($prev) : '',
            'prevname'         => $hasprev ? self::clean_activity_name($prev) : '',
            'hasnext'          => $hasnext,
            'nexturl'          => $hasnext ? course_landing::activity_url($next) : '',
            'nextname'         => $hasnext ? self::clean_activity_name($next) : '',

            'currentsectionname'     => $currentsectionname,
            'currentactindex'        => $currentactindex,
            'currentsectionacttotal' => $currentsectionacttotal,
            'currentactivitysub'     => $currentactivitysub,
            'currentduration'        => $currentduration,
            'useravatar'             => $useravatar,
        ];
    }

    /**
     * Generic per-context admin entries that every module gets. They are split
     * off below the module's own teaching actions so the type-specific items
     * (Questions, Submissions, Subscriptions, …) stay at the top of the menu.
     */
    private const GENERIC_MENU_KEYS = [
        'roleassign', 'roleoverride', 'rolecheck', 'filtermanage', 'logreport',
        'backup', 'restore', 'competencybreakdown', 'coursereports',
    ];

    /**
     * Per-activity admin actions for the kebab menu.
     *
     * Read straight off Moodle's secondary navigation for the current module, so
     * the entries are whatever core decides this module offers this user —
     * Quiz gets Questions/Results/Question bank, Assignment gets Submissions/
     * Advanced grading, Forum gets Subscriptions/Reports — and every one of them
     * has already been capability-checked by core. Nothing is hand-listed per
     * type here, and a user without the rights (a student) gets an empty array,
     * which is what hides the kebab entirely.
     *
     * @param moodle_page $page
     * @return array Menu rows, generic entries last and flagged to draw a divider.
     */
    private static function admin_menu(moodle_page $page): array {
        $currenturl = $page->url;
        $module = [];
        $generic = [];

        foreach ($page->secondarynav->children as $node) {
            // The module's own view tab — that is the page we are already on.
            if ($node->key === 'modulepage') {
                continue;
            }
            // Only plain links travel into the menu; action_link nodes carry JS
            // handlers that need the markup core would have rendered for them.
            if (!($node->action instanceof moodle_url)) {
                continue;
            }
            // Exact match only. Several modules hang a real destination off the
            // view script with an extra param (Assignment's Submissions is
            // view.php?id=N&action=grading) — a looser comparison swallows them.
            if ($currenturl && $node->action->compare($currenturl, URL_MATCH_EXACT)) {
                continue;
            }

            $row = [
                'text' => (string) $node->text,
                'url'  => $node->action->out(false),
            ];

            if (in_array((string) $node->key, self::GENERIC_MENU_KEYS, true)) {
                $generic[] = $row;
            } else {
                $module[] = $row;
            }
        }

        if (empty($module) && empty($generic)) {
            return [];
        }

        // Only draw the separator when there is something on both sides of it.
        if (!empty($module) && !empty($generic)) {
            $generic[0]['divider'] = true;
        }

        return array_merge($module, $generic);
    }

    /**
     * The activity's own intro text, formatted the way core would format it.
     *
     * Fallback for when the module has cleared the activity header description;
     * see the caller for why that has to be worked around here.
     *
     * @param cm_info $cm
     * @return string Formatted HTML, or '' when the module has no intro.
     */
    private static function module_intro(cm_info $cm): string {
        global $DB;

        try {
            $record = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        } catch (\dml_exception $e) {
            // A module without a conventional instance table — nothing to read.
            return '';
        }
        if (!$record || empty($record->intro)) {
            return '';
        }
        return format_module_intro($cm->modname, $record, $cm->id);
    }

    /**
     * Single-line caption describing why an activity or section is restricted.
     *
     * The raw string comes from Moodle's availability API and still carries the
     * placeholder markup that {@see info::format_info()} resolves (activity
     * names, dates, groups). It is rendered as a plain caption under the
     * activity name, so tags and line breaks are flattened away.
     *
     * @param string|null $availableinfo Raw availability info from cm_info/section_info.
     * @param \stdClass $course
     * @return string Plain-text caption, or '' when there is no restriction.
     */
    private static function restriction_text(?string $availableinfo, \stdClass $course): string {
        if (empty($availableinfo)) {
            return '';
        }
        // strip_tags rather than html_to_text: the latter shouts <strong> spans
        // in upper case, and the emphasised part here is the activity name.
        $text = html_entity_decode(strip_tags(info::format_info($availableinfo, $course)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Estimated duration or type indicator for an activity.
     *
     * @param cm_info $cm
     * @return string
     */
    /**
     * Estimated duration or type indicator for an activity.
     *
     * How to edit/override duration:
     * 1. ID Number (Activity Settings -> Common module settings -> ID number):
     *    Set e.g. "10m", "15 min", "30 menit", "dur:20m".
     * 2. Tag in activity name: e.g. "Modul 1 — Dasar Komunikasi [15m]"
     *    The tag [15m] is automatically used as duration and stripped from title display.
     * 3. Tag in activity description/intro: e.g. "[duration: 10m]" or "[10m]".
     * 4. Quiz setting (Quiz Settings -> Timing -> Time limit): uses quiz time limit.
     * 5. Page content: auto-calculated reading time (~180 words/min).
     * 6. Default by module type.
     *
     * @param cm_info $cm
     * @return string
     */
    public static function activity_duration(cm_info $cm): string {
        global $DB;

        // 1. Check ID number (Common module settings -> ID number).
        if (!empty($cm->idnumber)) {
            $idnum = trim($cm->idnumber);
            if (preg_match('/(?:dur(?:ation)?[:=]\s*)?(\d+\s*(?:m|min|mins|menit|h|jam|hours?))/i', $idnum, $m)) {
                return $m[1];
            }
        }

        // 2. Check for duration tag in activity name: e.g. "Modul 1 [15 min]", "Video (10m)".
        $rawname = (string) $cm->name;
        if (preg_match('/[\[\(](\d+\s*(?:m|min|mins|menit|h|jam|hours?))[\]\)]/i', $rawname, $m)) {
            return $m[1];
        }

        // 3. Check for duration tag in intro/description: e.g. "[duration: 15m]" or "(15 min)".
        try {
            $record = $DB->get_record($cm->modname, ['id' => $cm->instance], 'intro');
            if ($record && !empty($record->intro)) {
                if (preg_match('/[\[\(](?:dur(?:ation)?[:=]\s*)?(\d+\s*(?:m|min|mins|menit|h|jam|hours?))[\]\)]/i', $record->intro, $m)) {
                    return $m[1];
                }
            }
        } catch (\Throwable $e) {}

        // 4. Activity-specific automatic calculations.
        try {
            if ($cm->modname === 'quiz') {
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'timelimit');
                if ($quiz && !empty($quiz->timelimit)) {
                    $mins = (int) ceil($quiz->timelimit / 60);
                    return $mins . 'm';
                }
                return '5m';
            }
            if ($cm->modname === 'page') {
                $page = $DB->get_record('page', ['id' => $cm->instance], 'content');
                if ($page && !empty($page->content)) {
                    $words = str_word_count(strip_tags($page->content));
                    $mins = max(1, (int) round($words / 180));
                    return $mins . ' min';
                }
                return '5 min';
            }
            if ($cm->modname === 'book') {
                return '10 min';
            }
            if ($cm->modname === 'scorm') {
                return '15 min';
            }
            if ($cm->modname === 'resource') {
                return 'PDF';
            }
            if ($cm->modname === 'assign') {
                return 'Tugas';
            }
            if ($cm->modname === 'url') {
                return 'Web';
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * Cleans duration tags like [15m] from the activity display name.
     *
     * @param cm_info $cm
     * @return string
     */
    public static function clean_activity_name(cm_info $cm): string {
        $name = html_entity_decode($cm->get_formatted_name(), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s*[\[\(]\d+\s*(?:m|min|mins|menit|h|jam|hours?)[\]\)]/i', '', $name));
    }
}
