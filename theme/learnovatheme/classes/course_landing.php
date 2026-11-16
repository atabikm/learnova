<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use core_course_category;
use context_course;
use moodle_url;
use completion_info;
use core_completion\progress;
use local_learnova_course\meta_manager;
use local_learnova_course\discussion_manager;

/**
 * Course Landing page (course/view.php) data builder.
 *
 * Mirrors the learnova_course_landing_v6 mockup but is driven by real data
 * wherever it exists: the course record, its Learnova meta
 * ({@see \local_learnova_course\meta_manager}), section/activity structure with
 * per-user completion, enrolment, the assigned instructor and the gradebook.
 *
 * A handful of fields have no source yet (course rating, the discussion
 * thread). Those are filled with clearly marked dummy data so
 * the page matches the mockup; they are the obvious candidates to wire to real
 * data once those features exist.
 */
class course_landing {

    /**
     * Build the full template context for the course landing page.
     *
     * @return array
     */
    public static function context(): array {
        global $CFG, $USER, $PAGE;

        $course  = $PAGE->course;
        $cid     = (int) $course->id;
        $context = context_course::instance($cid);
        $meta    = meta_manager::get($cid);

        $isloggedin = isloggedin() && !isguestuser();

        // Edit mode: true only for a teacher/admin who has
        // toggled Moodle's native edit switch. Drives every edit control below;
        // when false, no edit markup is emitted at all.
        $editing = $PAGE->user_is_editing()
            && has_capability('moodle/course:manageactivities', $context);

        // ── Course structure + per-user completion ──────────────────────────
        $structure  = self::build_structure($course, $isloggedin, $editing);
        $modules    = $structure['modules'];
        $totalacts  = $structure['total'];
        $doneacts   = $structure['done'];
        $continue   = $structure['continue']; // First resumable activity, or null.

        // Progress percentage (real completion). Null when not trackable.
        $percent = null;
        if ($isloggedin) {
            $raw = progress::get_course_progress_percentage($course, $USER->id);
            if ($raw !== null) {
                $percent = (int) round($raw);
            }
        }

        $ctx = [
            'wwwroot'   => $CFG->wwwroot,
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $cid]))->out(false),
        ];

        // ── Hero ────────────────────────────────────────────────────────────
        $ctx += self::hero($course, $cid, $context, $meta, $totalacts, count($modules));

        // ── Announcement band (conditional, 3-tier, from meta) ──────────────
        $ctx += self::announcement($meta, $context, $isloggedin);

        // ── Tabs ────────────────────────────────────────────────────────────
        $ctx['modules']    = $modules;
        $ctx['hasmodules'] = !empty($modules);
        $ctx['about']      = self::about($meta);
        // The About tab is driven by the learning objectives; hide it when empty.
        $ctx['hasabout']   = !empty($ctx['about']['hasobjectives']);
        // Discussion tab — conditional, gated by the course's Learnova toggle.
        $ctx['hasdiscussion'] = !empty($meta->learnova_discussion_enabled);
        if ($ctx['hasdiscussion']) {
            $ctx['discussion'] = self::discussion($cid, $context);
        }
        // Progress tab (was "Grades"): learner sees a completion ring + per-
        // activity list; instructors/admins see the participant roster.
        $ctx['progress']    = self::progress($course, $cid, $context, $structure, $percent, $isloggedin);
        $ctx['hasprogress'] = !empty($ctx['progress']['has']);

        // ── "More →" exit button: navigates to the Course Management page.
        $ctx['hasmore'] = $isloggedin;
        if ($ctx['hasmore']) {
            $ctx['moreurl'] = (new moodle_url('/local/learnova_course/management.php',
                ['id' => $cid]))->out(false);
        }

        // ── Sidebar progress card ───────────────────────────────────────────
        $ctx['sidebar'] = self::sidebar(
            $course, $cid, $context, $meta, $percent, $totalacts, $doneacts, $continue, $isloggedin
        );

        // ── Edit mode  ──────────────────────────────────────────
        $ctx['editing'] = $editing;
        if ($editing) {
            $ctx['courseid'] = $cid;
            // Course context id — used by the JS fragment loader to re-render the
            // content tab in place after +Add section / +Subsection (no reload).
            $ctx['contextid'] = $context->id;
            $ctx['sesskey']  = sesskey();
            // Course format component drives the section inline-rename web
            // service (core_update_inplace_editable → format_<fmt>/sectionname).
            $ctx['courseformat'] = 'format_' . $course->format;
            // Add Activity/Resource chooser grid, split by
            // archetype (Activities / Resources). Tiles link to the real add
            // form; the section number is appended client-side per section.
            $ctx['addgrid'] = self::add_activity_grid($cid, $course);
        }

        return $ctx;
    }

    /**
     * Context for the Course Content tab alone (module list + add-section
     * button), used by the fragment renderer to re-draw that tab in place after
     * +Add section / +Subsection — no full page reload.
     *
     * Always renders in edit mode: the fragment is only ever requested from the
     * edit-mode buttons, and the caller ({@see theme_learnovatheme_output_fragment_course_content})
     * enforces the capability. Mirrors the content-tab slice of {@see context()}.
     *
     * @param int $courseid
     * @return array{modules: array, hasmodules: bool, editing: bool}
     */
    public static function content_context(int $courseid): array {
        $course     = get_course($courseid);
        $isloggedin = isloggedin() && !isguestuser();
        $structure  = self::build_structure($course, $isloggedin, true);
        return [
            'modules'    => $structure['modules'],
            'hasmodules' => !empty($structure['modules']),
            'editing'    => true,
        ];
    }

    // ── Announcement band ────────────────────────────────────────────────────

    /**
     * Announcement band context: the conditional, 3-tier notice shown under the
     * topbar. Returns an empty array (band hidden) unless
     * {@see meta_manager::announcement_is_visible()} passes for this viewer.
     *
     * The tier drives both the CSS modifier and which stroke icon the template
     * renders; the matching colour tokens live in post.scss.
     *
     * @param \stdClass       $meta
     * @param \context_course $context
     * @param bool            $isloggedin
     * @return array
     */
    private static function announcement($meta, $context, bool $isloggedin): array {
        global $USER;

        // "Enrolled only" is satisfied by an active enrolment or the ability to
        // manage the course, so teachers/managers/admins always see their notices.
        $insider = ($isloggedin && is_enrolled($context, $USER, '', true))
            || has_capability('moodle/course:update', $context);

        if (!meta_manager::announcement_is_visible($meta, $insider)) {
            return [];
        }

        $tier = meta_manager::annc_tier_key($meta->learnova_annc_tier ?? 'info');

        return [
            'hasannounce'         => true,
            'announce_tier'       => $tier,
            'announce_is_urgent'  => $tier === 'urgent',
            'announce_is_warning' => $tier === 'warning',
            'announce_is_info'    => $tier === 'info',
            'announce_label'      => get_string('cl_annc_' . $tier, 'theme_learnovatheme'),
            'announce_text'       => format_text(
                (string) $meta->learnova_annc_text, FORMAT_HTML, ['context' => $context, 'filter' => false]
            ),
        ];
    }

    // ── Hero ────────────────────────────────────────────────────────────────

    /**
     * Hero block: category + type pills, title, summary, competency chips
     * (real), (dummy) rating, enrolled count, duration, module/activity counts
     * and the assigned instructor.
     *
     * @param \stdClass        $course
     * @param int              $cid
     * @param \context_course  $context
     * @param \stdClass        $meta
     * @param int              $totalacts
     * @param int              $modulecount
     * @return array
     */
    private static function hero($course, int $cid, $context, $meta, int $totalacts, int $modulecount): array {
        $hero = [];

        // Category → primary pill (real).
        $cat = core_course_category::get($course->category, IGNORE_MISSING, true);
        if ($cat) {
            $hero['hero_category'] = format_string($cat->get_formatted_name());
        }

        $hero['hero_title'] = format_string($course->fullname);

        // Summary → sub line (real; plain text, styling is CSS-only).
        $summary = '';
        if (!empty($course->summary)) {
            $summary = content_to_text(format_text($course->summary, $course->summaryformat ?? FORMAT_HTML,
                ['context' => $context, 'filter' => false]), false);
        }
        if ($summary !== '') {
            $hero['hero_sub'] = shorten_text($summary, 260);
        }

        // Background image: the course overview image when present.
        $cover = self::course_image($course, $context);
        if ($cover !== null) {
            $hero['hero_bg'] = $cover;
        }

        // Competency chips — real, from the course's associated competencies.
        // The chip row is hidden by the template when this is empty, so courses
        // without competencies (or sites with the subsystem disabled) show none.
        $hero['hero_competencies'] = self::competencies($cid);

        // Rating — DUMMY (no course-rating feature yet).
        $hero['hero_rating']       = '4.8';
        $hero['hero_rating_count'] = '876';

        // Enrolled count (real).
        $enrolled = count_enrolled_users($context);
        if ($enrolled > 0) {
            $hero['hero_enrolled'] = $enrolled;
        }

        // Duration (real, from meta).
        if ((int) $meta->learning_hours_minutes > 0) {
            $hero['hero_duration'] = meta_manager::format_learning_hours((int) $meta->learning_hours_minutes);
        }

        // Module / activity counts (real).
        if ($modulecount > 0) {
            $hero['hero_modules'] = $modulecount;
        }
        if ($totalacts > 0) {
            $hero['hero_activities'] = $totalacts;
        }

        // Instructor (real — first editing teacher in the course).
        $instructor = self::instructor($context);
        if ($instructor !== null) {
            $hero['instructor'] = $instructor;
        }

        return $hero;
    }

    /**
     * The course's primary instructor (first editing teacher, then teacher).
     *
     * @param \context_course $context
     * @return array|null  {name, role, avatar} or null when none assigned.
     */
    private static function instructor($context): ?array {
        global $PAGE;

        $roleids = array_keys(get_archetype_roles('editingteacher')
            + get_archetype_roles('teacher'));
        if (empty($roleids)) {
            return null;
        }

        // Field list covering fullname() + user_picture needs (all aliased to u)
        // plus the two profile fields shown on the role line.
        $fields = 'u.id, u.picture, u.firstname, u.lastname, u.middlename, u.alternatename, ' .
            'u.firstnamephonetic, u.lastnamephonetic, u.imagealt, u.email, ' .
            'u.department, u.institution';
        $users = get_role_users($roleids, $context, false, $fields);
        if (empty($users)) {
            return null;
        }
        $teacher = reset($users);

        $picture = new \user_picture($teacher);
        $picture->size = 100;

        // Role line: "<department> · <institution>" from the Moodle profile,
        // skipping whichever is empty. Hidden entirely when both are blank.
        $roleparts = array_filter([
            trim((string) $teacher->department),
            trim((string) $teacher->institution),
        ], fn($p) => $p !== '');
        $role = implode(' · ', array_map('format_string', $roleparts));

        return [
            'name'   => fullname($teacher),
            'role'   => $role,
            'avatar' => $picture->get_url($PAGE)->out(false),
        ];
    }

    /**
     * Competency chips: the competencies associated with this course, labelled
     * by their short name. Uses the low-level course_competency reader (not
     * \core_competency\api::list_course_competencies) so it works for any
     * viewer — that API throws when the user lacks coursecompetencyview.
     *
     * Returns [] when the competency subsystem is disabled or the course has
     * none; the template hides the whole chip row in that case.
     *
     * @param int $cid
     * @return array  List of {label}.
     */
    private static function competencies(int $cid): array {
        if (!\core_competency\api::is_enabled()) {
            return [];
        }

        $chips = [];
        foreach (\core_competency\course_competency::list_competencies($cid) as $competency) {
            $chips[] = ['label' => format_string($competency->get('shortname'))];
        }
        return $chips;
    }

    // ── Course content (sections + activities + completion) ──────────────────

    /**
     * Walk the course sections and their activities, attaching per-user
     * completion + availability. Returns the module list for the template plus
     * roll-up totals and the first resumable activity.
     *
     * @param \stdClass $course
     * @param bool      $isloggedin
     * @param bool      $editing     Edit mode on → attach per-item ids/URLs and
     *                               keep empty sections so teachers can add to them.
     * @return array{modules: array, total: int, done: int, continue: ?array}
     */
    private static function build_structure($course, bool $isloggedin, bool $editing = false): array {
        $modinfo    = get_fast_modinfo($course);
        $completion = new completion_info($course);
        $marker     = (int) ($course->marker ?? 0);

        $modules  = [];
        $total    = 0;
        $done     = 0;
        $continue = null;
        $modnum   = 0;

        foreach ($modinfo->get_section_info_all() as $section) {
            // Skip the top "general" section and delegated (subsection) sections
            // — the latter render nested under their owning subsection module.
            if ($section->section == 0 || $section->is_delegated()) {
                continue;
            }
            $cmids = $modinfo->sections[$section->section] ?? [];
            // Empty sections are dropped when viewing, but kept in edit mode so a
            // teacher can add the first activity to them.
            if (empty($cmids) && !$editing) {
                continue;
            }

            $acts        = [];
            $subsections = [];
            $sectiondone = 0;
            foreach ($cmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);

                // Subsection module → nested block (its activities live in the
                // delegated section). Its activities still count toward totals.
                if ($cm->modname === 'subsection') {
                    $sub = self::build_subsection($cm, $modinfo, $completion, $isloggedin, $editing);
                    if ($sub !== null) {
                        $subsections[] = $sub;
                        $total += $sub['total'];
                        $done  += $sub['donecount'];
                    }
                    continue;
                }

                $entry = self::build_activity_entry($cm, $completion, $isloggedin, $editing);
                if ($entry === null) {
                    continue;
                }

                $total++;
                if ($entry['done']) {
                    $done++;
                    $sectiondone++;
                }

                // First available, incomplete, linkable activity → "continue".
                if ($continue === null && $entry['available'] && $cm->url && !$entry['done']) {
                    $entry['act']['current'] = true;
                    $continue = [
                        'name'      => $cm->get_formatted_name(),
                        'url'       => self::activity_url($cm),
                        'icon_type' => $entry['act']['icon_type'],
                        'icon'      => $entry['act']['icon'],
                        'modname'   => get_string('pluginname', $cm->modname),
                        'section'   => get_section_name($course, $section),
                    ];
                }

                $acts[] = $entry['act'];
            }

            // Non-editing: skip a section with nothing to show.
            if (empty($acts) && empty($subsections) && !$editing) {
                continue;
            }

            $modnum++;
            $count   = count($acts);
            $alldone = ($count > 0 && $sectiondone === $count);
            $started = ($sectiondone > 0);
            $locked  = !$section->available && !$section->uservisible;

            if ($locked) {
                $status = 'locked';
            } else if ($alldone) {
                $status = 'done';
            } else if ($started) {
                $status = 'progress';
            } else {
                $status = 'open';
            }

            $module = [
                'num'        => $modnum,
                'name'       => get_section_name($course, $section),
                'count'      => $count,
                'donecount'  => $sectiondone,
                'meta'       => get_string('cl_section_meta', 'theme_learnovatheme',
                                    (object) ['count' => $count, 'done' => $sectiondone]),
                'status'     => $status,
                'is_done'    => $status === 'done',
                'is_progress'=> $status === 'progress',
                'is_open'    => $status === 'open',
                'is_locked'  => $status === 'locked',
                'activities' => $acts,
                'subsections'    => $subsections,
                'hassubsections' => !empty($subsections),
            ];
            if ($editing) {
                $module += self::section_edit_fields($section, $course, $marker);
            }
            $modules[] = $module;
        }

        return ['modules' => $modules, 'total' => $total, 'done' => $done, 'continue' => $continue];
    }

    /**
     * Build one activity's display array (+ per-item edit fields when editing).
     * Returns null for items that should not appear (labels, invisible items,
     * deletion in progress). Totals/continue bookkeeping stays in the caller.
     *
     * @param \cm_info          $cm
     * @param \completion_info  $completion
     * @param bool              $isloggedin
     * @param bool              $editing
     * @return array{act: array, done: bool, tracked: bool, available: bool}|null
     */
    private static function build_activity_entry($cm, $completion, bool $isloggedin, bool $editing): ?array {
        if (!$cm->uservisible && empty($cm->availableinfo)) {
            return null;
        }
        if ($cm->deletioninprogress || $cm->modname === 'label') {
            return null;
        }

        $tracked = $isloggedin && $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE;
        $iscomplete = false;
        if ($tracked) {
            $data = $completion->get_data($cm, true);
            $iscomplete = in_array((int) $data->completionstate,
                [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
        }

        $available = (bool) $cm->available && $cm->uservisible;
        $icontype  = self::activity_icon_type($cm);

        $act = [
            'name'       => $cm->get_formatted_name(),
            'url'        => self::activity_url($cm),
            'hasurl'     => (bool) $cm->url,
            'icon_type'  => $icontype,
            'icon'       => self::act_icon_url($icontype),
            'done'       => $iscomplete,
            'tracked'    => $tracked,
            'locked'     => !$available,
            'current'    => false,
            'modname'    => $cm->modname,
            'instance'   => (int) $cm->instance,
            'type_label' => self::activity_type_label($cm),
        ];
        if ($editing) {
            $act += [
                'cmid'     => (int) $cm->id,
                'hidden'   => !$cm->visible,
                // clembed=1 → the theme's drawers layout renders this form in a
                // chrome-less embedded shell so only the form shows inside the
                // sliding "Edit settings" panel iframe.
                'edit_url' => (new moodle_url('/course/modedit.php',
                                ['update' => $cm->id, 'return' => 0, 'sr' => 0, 'clembed' => 1]))->out(false),
            ];
        }

        return ['act' => $act, 'done' => $iscomplete, 'tracked' => $tracked, 'available' => $available];
    }

    /**
     * Build a nested subsection block (mod_subsection delegated section) with its
     * own activities and, in edit mode, its ids/URLs. Subsections never nest
     * further, so any subsection module inside is ignored.
     *
     * @param \cm_info          $cm          The subsection module in the parent section.
     * @param \course_modinfo   $modinfo
     * @param \completion_info  $completion
     * @param bool              $isloggedin
     * @param bool              $editing
     * @return array|null
     */
    private static function build_subsection($cm, $modinfo, $completion, bool $isloggedin, bool $editing): ?array {
        $deleg = $cm->get_delegated_section_info();
        if (!$deleg) {
            return null;
        }

        $acts = [];
        $done = 0;
        foreach ($modinfo->sections[$deleg->section] ?? [] as $scid) {
            $scm = $modinfo->get_cm($scid);
            if ($scm->modname === 'subsection') {
                continue; // No nesting beyond one level.
            }
            $entry = self::build_activity_entry($scm, $completion, $isloggedin, $editing);
            if ($entry === null) {
                continue;
            }
            if ($entry['done']) {
                $done++;
            }
            $acts[] = $entry['act'];
        }

        if (empty($acts) && !$editing) {
            return null;
        }

        $sub = [
            'name'       => $cm->get_formatted_name(),
            'activities' => $acts,
            'total'      => count($acts),
            'donecount'  => $done,
        ];
        if ($editing) {
            $sub += [
                'sectionid'  => (int) $deleg->id,
                'sectionnum' => (int) $deleg->section,
                'cmid'       => (int) $cm->id,
                'hidden'     => !$cm->visible,
                'edit_url'   => (new moodle_url('/course/editsection.php',
                                    ['id' => $deleg->id, 'sr' => 0, 'clembed' => 1]))->out(false),
            ];
        }
        return $sub;
    }

    /**
     * Per-section edit fields (ids + real Moodle URLs) used by the section
     * controls in edit mode.
     *
     * @param \section_info $section
     * @param \stdClass     $course
     * @param int           $marker   Currently highlighted section number (0 = none).
     * @return array
     */
    private static function section_edit_fields($section, $course, int $marker): array {
        $num    = (int) $section->section;
        $ishigh = ($marker === $num && $num > 0);
        return [
            'sectionid'     => (int) $section->id,
            'sectionnum'    => $num,
            'hidden'        => !$section->visible,
            'highlighted'   => $ishigh,
            'edit_url'      => (new moodle_url('/course/editsection.php',
                                  ['id' => $section->id, 'sr' => 0, 'clembed' => 1]))->out(false),
            'permalink_url' => (new moodle_url('/course/view.php',
                                  ['id' => $course->id, 'section' => $num]))->out(false),
            // Native marker toggle: set to this section (or 0 to clear).
            'highlight_url' => (new moodle_url('/course/view.php',
                                  ['id' => $course->id, 'marker' => $ishigh ? 0 : $num,
                                   'sesskey' => sesskey()]))->out(false),
        ];
    }

    /**
     * Add Activity/Resource chooser grid: every installable module
     * for this course, grouped into three sections — Activities, Resources and
     * Plugins — and tagged with a Learnova learning-experience category
     * (Learn/Assess/Discuss/Interactive/Live/Survey) that drives the panel's
     * filter pills. Each tile carries the real add-form base URL; the caller
     * appends &section=<num> per section. Never hand-built: the module list,
     * display names and per-module descriptions all come from Moodle core.
     *
     * Section split:
     *   - Plugins   → any non-standard (third-party) module.
     *   - Resources → standard modules with the RESOURCE archetype.
     *   - Activities→ every other standard module.
     *
     * The item list, titles, add links, descriptions and — crucially — the
     * per-user favourite ("starred") flag all come from Moodle's native activity
     * chooser service ({@see \core_course\local\service\content_item_service}), so
     * favourites are the same ones the core chooser uses. Toggling happens over
     * the native web services (see course_landing.js); no parallel store.
     *
     * @param int       $cid
     * @param \stdClass $course
     * @return array{activities: array, resources: array, plugins: array}
     */
    private static function add_activity_grid(int $cid, $course): array {
        global $USER;

        $service = \core_course\local\factory\content_item_service_factory::get_content_item_service();
        $items   = $service->get_content_items_for_user_in_course($USER, $course);

        // Featured order (mirrors the mockup's priority); the rest follow title.
        $featured = ['scorm', 'h5pactivity', 'quiz', 'assign', 'forum', 'feedback',
            'survey', 'lesson', 'resource', 'url', 'page', 'folder', 'book'];
        $order = array_flip($featured);

        $pluginman = \core_plugin_manager::instance();

        $activities = [];
        $resources  = [];
        $plugins    = [];
        foreach ($items as $item) {
            // Module name from the exposing component ("mod_assign" → "assign").
            $modname = preg_replace('/^mod_/', '', (string) $item->componentname);

            // Subsections have their own button; labels aren't in this grid.
            if ($modname === 'subsection' || $modname === 'label') {
                continue;
            }

            $slug     = self::icon_type_for_modname($modname) ?? self::purpose_icon($modname);
            $category = self::category_for_icon_type($slug);

            // Native add link, minus any section param (JS appends the real one).
            $linkurl = new moodle_url($item->link);
            $linkurl->remove_params('section');

            // The native link targets course/mod.php — a pure redirect script
            // that rebuilds the modedit.php URL from a whitelist, silently
            // dropping our clembed flag (so the form would render full-chrome
            // in the panel iframe). Point straight at modedit.php instead,
            // mapping id → course and keeping any extra params (e.g. type for
            // preconfigured tools); modedit accepts the plain section number
            // the JS appends.
            if (strpos($linkurl->get_path(), '/course/mod.php') !== false) {
                $params = $linkurl->params();
                $params['course'] = $params['id'] ?? $cid;
                unset($params['id']);
                $params += ['return' => 0, 'sr' => 0];
                $linkurl = new moodle_url('/course/modedit.php', $params);
            }

            // Description for the info pane. Prefer the module's concise native
            // "summary" (one sentence); fall back to the verbose "help" only when
            // no summary is defined. Both are HTML → flattened to plain text.
            $desc = '';
            foreach ([$item->summary ?? '', $item->help ?? ''] as $source) {
                if ($source === '') {
                    continue;
                }
                $desc = shorten_text(trim(preg_replace('/\s+/', ' ',
                    content_to_text($source, false))), 180);
                if ($desc !== '') {
                    break;
                }
            }

            $tile = [
                'label'         => $item->title,
                'modname'       => $modname,
                'icon_type'     => $slug,
                'icon'          => self::act_icon_url($slug),
                'category'      => $category,
                'catlabel'      => get_string('cl_addcat_' . $category, 'theme_learnovatheme'),
                'desc'          => $desc,
                'addurlbase'    => $linkurl->out(false),
                // Native favourite wiring.
                'contentitemid' => (int) $item->id,
                'component'     => $item->componentname,
                'favourite'     => !empty($item->favourite),
                'canfav'        => empty($item->legacyitem), // Legacy items have no real id.
            ];
            // Carry the featured rank so the grids can be sorted after grouping.
            $tile['_rank'] = $order[$modname] ?? 999;

            // Third-party module → Plugins section, regardless of archetype.
            $info = $pluginman->get_plugin_info('mod_' . $modname);
            $isstandard = $info ? $info->is_standard() : false;
            $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            if (!$isstandard) {
                $plugins[] = $tile;
            } else if ($archetype === MOD_ARCHETYPE_RESOURCE) {
                $resources[] = $tile;
            } else {
                $activities[] = $tile;
            }
        }

        // Featured first, then by title — within each section.
        $sort = function(array &$list) {
            usort($list, function($a, $b) {
                if ($a['_rank'] !== $b['_rank']) {
                    return $a['_rank'] <=> $b['_rank'];
                }
                return strcasecmp($a['label'], $b['label']);
            });
            foreach ($list as &$t) {
                unset($t['_rank']);
            }
        };
        $sort($activities);
        $sort($resources);
        $sort($plugins);

        // Filter-pill icons (one per category), served from
        // pix/icons/course_landing/add_activity/<category>.svg with the category
        // colour baked in — mirrors the icon set used in the mockup.
        $caticons = [];
        foreach (['learn', 'assess', 'discuss', 'interactive', 'live', 'survey'] as $c) {
            $caticons[$c] = self::add_cat_icon_url($c);
        }

        return [
            'activities' => $activities,
            'resources'  => $resources,
            'plugins'    => $plugins,
            'caticons'   => $caticons,
        ];
    }

    /**
     * URL of a category filter-pill icon by its category slug. Resolves to
     * theme/learnovatheme/pix/icons/course_landing/add_activity/<category>.svg.
     *
     * @param string $category
     * @return string
     */
    private static function add_cat_icon_url(string $category): string {
        global $OUTPUT;
        return $OUTPUT->image_url('icons/course_landing/add_activity/' . $category,
            'theme_learnovatheme')->out(false);
    }

    /**
     * Learnova learning-experience category slug for an activity-icon slug. This
     * is the single source of truth that keeps the Add-panel filter pills in sync
     * with the tinted icon groups defined in post.scss. Anything unmapped lands
     * on "learn" (neutral content).
     *
     * @param string $slug  Icon-type slug from {@see self::icon_type_for_modname()}.
     * @return string  One of: learn, assess, discuss, interactive, live, survey.
     */
    public static function category_for_icon_type(string $slug): string {
        static $map = [
            // Assess / amber.
            'assignment' => 'assess', 'quiz' => 'assess', 'lesson' => 'assess',
            'glossary' => 'assess', 'database' => 'assess', 'journal' => 'assess',
            'attendance' => 'assess', 'certificate' => 'assess', 'checklist' => 'assess',
            'pdf-annotation' => 'assess',
            // Discuss / blue.
            'forum' => 'discuss', 'chat' => 'discuss', 'wiki' => 'discuss',
            'workshop' => 'discuss', 'peer-assessment' => 'discuss', 'dialogue' => 'discuss',
            // Interactive / red.
            'h5p' => 'interactive', 'h5p-interactive-video' => 'interactive',
            'scorm' => 'interactive', 'external-tool' => 'interactive', 'game' => 'interactive',
            // Live / green.
            'bigbluebutton' => 'live', 'zoom' => 'live', 'teams' => 'live',
            'google-meet' => 'live', 'scheduler' => 'live',
            // Survey / purple.
            'choice' => 'survey', 'survey' => 'survey', 'feedback' => 'survey',
            'questionnaire' => 'survey',
            // Learn / teal.
            'file' => 'learn', 'folder' => 'learn', 'page' => 'learn', 'url' => 'learn',
            'book' => 'learn', 'label' => 'learn', 'ims-package' => 'learn', 'gallery' => 'learn',
        ];
        return $map[$slug] ?? 'learn';
    }

    // ── About tab ────────────────────────────────────────────────────────────

    /**
     * About tab: the real learning objectives from the course's Learnova meta,
     * parsed one-per-line in PHP and handed to the template as a list of items.
     *
     * Returns hasobjectives = false when no objectives are set; the caller uses
     * that to hide the whole About tab.
     *
     * @param \stdClass $meta
     * @return array
     */
    private static function about($meta): array {
        // Real learning objectives — parsed per line here, never in the template.
        $objectives = meta_manager::parse_objectives($meta->learning_objectives ?? '');

        return [
            'objectives'    => array_map(fn($o) => ['text' => $o], $objectives),
            'hasobjectives' => !empty($objectives),
        ];
    }

    // ── Discussion tab (real, custom) ────────────────────────────────────────

    /**
     * Discussion tab content — the custom course-level discussion (Plan B),
     * backed by {@see \local_learnova_course\discussion_manager}. The whole tab
     * is only built when the course's learnova_discussion_enabled toggle is on.
     *
     * Posts render server-side (chronological); posting, replying and deleting
     * happen over AJAX without a full page reload. Guests and non-enrolled
     * visitors get a read-only feed.
     *
     * @param int             $cid
     * @param \context_course $context
     * @return array
     */
    private static function discussion(int $cid, $context): array {
        global $USER, $PAGE;

        $canpost = discussion_manager::can_post($context);

        // Current user's avatar for the composer row (only when they can post).
        $myavatarurl = '';
        if ($canpost) {
            $picture = new \user_picture($USER);
            $picture->size = 64;
            $myavatarurl = $picture->get_url($PAGE)->out(false);
        }

        return [
            'courseid'    => $cid,
            'posts'       => discussion_manager::get_posts($cid, $context),
            'canpost'     => $canpost,
            'myavatarurl' => $myavatarurl,
        ];
    }

    // ── Progress tab (real, native gradebook + completion) ───────────────────

    /**
     * Progress tab — dual view, all data from native Moodle (completion +
     * gradebook, no custom fields). Role is detected by capability:
     *
     *   - Instructors/admins (moodle/grade:viewall) get the participant roster
     *     with each learner's course total, sortable client-side, plus a link to
     *     the full Moodle gradebook.
     *   - Everyone else gets a completion ring summary + a per-activity list
     *     (type · name · score · status).
     *
     * @param \stdClass       $course
     * @param int             $cid
     * @param \context_course $context
     * @param array           $structure  Output of {@see self::build_structure()}.
     * @param int|null        $percent    Course completion %, or null.
     * @param bool            $isloggedin
     * @return array  {has, is_instructor, ...}
     */
    private static function progress($course, int $cid, $context, array $structure,
            ?int $percent, bool $isloggedin): array {
        global $CFG;

        if (!$isloggedin) {
            return ['has' => false];
        }

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        // Role detection via Moodle capability — not role names.
        if (has_capability('moodle/grade:viewall', $context)) {
            return self::progress_instructor($cid, $context);
        }
        return self::progress_learner($course, $cid, $structure, $percent);
    }

    /**
     * Learner view: a completion-ring summary plus a flat per-activity list
     * (type, name, gradebook score, status). Reuses the already-built course
     * structure so completion is not recomputed; grades are read per activity.
     *
     * @param \stdClass $course
     * @param int       $cid
     * @param array     $structure
     * @param int|null  $percent
     * @return array
     */
    private static function progress_learner($course, int $cid, array $structure, ?int $percent): array {
        global $USER;

        // Grade items for module activities, keyed by "module-instance".
        $itemmap = [];
        foreach (\grade_item::fetch_all(['courseid' => $cid, 'itemtype' => 'mod']) ?: [] as $item) {
            if ($item->is_hidden()) {
                continue;
            }
            $itemmap[$item->itemmodule . '-' . $item->iteminstance] = $item;
        }

        $rows = [];
        foreach ($structure['modules'] as $mod) {
            foreach ($mod['activities'] as $act) {
                $item = $itemmap[$act['modname'] . '-' . $act['instance']] ?? null;

                $hasscore = false;
                $passed   = null;
                $score    = get_string('cl_grade_na', 'theme_learnovatheme');
                if ($item) {
                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $USER->id]);
                    $final = $grade ? $grade->finalgrade : null;
                    if ($final !== null && $final !== false) {
                        $hasscore = true;
                        $passed   = ($item->gradepass > 0) ? ($final >= $item->gradepass) : true;
                        $score    = format_float($final, $item->get_decimals()) . ' / ' .
                                    format_float($item->grademax, $item->get_decimals());
                    }
                }

                // Status precedence: locked → graded → completed → current → to-do.
                if (!empty($act['locked'])) {
                    $sk = 'lk';   $sl = get_string('cl_prog_st_locked', 'theme_learnovatheme');
                } else if ($hasscore) {
                    $sk = $passed ? 'ok' : 'ip';
                    $sl = $passed
                        ? get_string('cl_grade_passed', 'theme_learnovatheme')
                        : get_string('cl_prog_st_inprogress', 'theme_learnovatheme');
                } else if (!empty($act['done'])) {
                    $sk = 'ok';   $sl = get_string('cl_prog_st_completed', 'theme_learnovatheme');
                } else if (!empty($act['current'])) {
                    $sk = 'ip';   $sl = get_string('cl_prog_st_inprogress', 'theme_learnovatheme');
                } else {
                    $sk = 'pend'; $sl = get_string('cl_prog_st_todo', 'theme_learnovatheme');
                }

                $rows[] = [
                    'type'        => $act['type_label'],
                    'name'        => $act['name'],
                    'url'         => $act['url'],
                    'hasurl'      => $act['hasurl'],
                    'score'       => $score,
                    'hasscore'    => $hasscore,
                    'statuskey'   => $sk,
                    'statuslabel' => $sl,
                ];
            }
        }

        if (empty($rows)) {
            return ['has' => false];
        }

        $total = (int) $structure['total'];
        $done  = (int) $structure['done'];
        $pct   = $percent;
        if ($pct === null) {
            $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        }

        // Modules still to finish.
        $modremain = 0;
        foreach ($structure['modules'] as $mod) {
            if (empty($mod['is_done'])) {
                $modremain++;
            }
        }

        // Summary heading by completion state.
        if ($pct >= 100) {
            $headkey = 'completed';
            $heading = get_string('cl_prog_completed', 'theme_learnovatheme');
        } else if ($pct > 0 || $done > 0) {
            $headkey = 'inprogress';
            $heading = get_string('cl_prog_inprogress', 'theme_learnovatheme');
        } else {
            $headkey = 'notstarted';
            $heading = get_string('cl_prog_notstarted', 'theme_learnovatheme');
        }

        // Ring geometry: r=34 → circumference 2πr ≈ 213.6.
        $circ   = 213.6;
        $offset = round($circ * (1 - min(100, max(0, $pct)) / 100), 1);

        // Optional deadline from the course end date.
        $deadline = null;
        if (!empty($course->enddate) && (int) $course->enddate > 0) {
            $deadline = userdate((int) $course->enddate, get_string('strftimedate', 'langconfig'));
        }

        return [
            'has'           => true,
            'is_instructor' => false,
            'rows'          => $rows,
            'pct'           => $pct,
            'ring_dash'     => $circ,
            'ring_offset'   => $offset,
            'head_key'      => $headkey,
            'heading'       => $heading,
            'summary'       => get_string('cl_prog_summary', 'theme_learnovatheme',
                                   (object) ['done' => $done, 'total' => $total, 'modules' => $modremain]),
            'deadline'      => $deadline,
        ];
    }

    /**
     * Instructor/admin view: every gradable participant with their course total,
     * sortable client-side (sort keys are emitted as data-* attributes), plus a
     * link to the full Moodle gradebook report.
     *
     * @param int             $cid
     * @param \context_course $context
     * @return array
     */
    private static function progress_instructor(int $cid, $context): array {
        global $CFG;

        require_once($CFG->dirroot . '/grade/lib.php');

        $users = get_gradable_users($cid);
        if (empty($users)) {
            return ['has' => false];
        }

        // Preload every participant's course-total grade in one read.
        $coursetotal = \grade_item::fetch_course_item($cid);
        $grades = [];
        if ($coursetotal) {
            foreach (\grade_grade::fetch_all(['itemid' => $coursetotal->id]) ?: [] as $g) {
                $grades[$g->userid] = $g;
            }
        }
        $decimals  = $coursetotal ? $coursetotal->get_decimals() : 2;
        $grademax  = $coursetotal ? (float) $coursetotal->grademax : 0.0;
        $gradepass = $coursetotal ? (float) $coursetotal->gradepass : 0.0;

        $rows = [];
        foreach ($users as $user) {
            $grade = $grades[$user->id] ?? null;
            $final = $grade ? $grade->finalgrade : null;

            $hasscore = ($final !== null && $final !== false);
            if ($hasscore) {
                $score     = format_float($final, $decimals) . ' / ' . format_float($grademax, $decimals);
                $percent   = $grademax > 0 ? round(($final / $grademax) * 100) . '%' : null;
                $sortscore = $grademax > 0 ? ($final / $grademax) : 0.0;
            } else {
                $score     = get_string('cl_grade_na', 'theme_learnovatheme');
                $percent   = null;
                $sortscore = -1.0; // Ungraded sorts below any real score.
            }

            [$statuskey, $statuslabel] = self::grade_status($final, $gradepass);

            $name = fullname($user);
            $rows[] = [
                'name'        => $name,
                'sortname'    => \core_text::strtolower($name),
                'score'       => $score,
                'hasscore'    => $hasscore,
                'percent'     => $percent,
                'sortscore'   => $sortscore,
                'statuskey'   => $statuskey,
                'statuslabel' => $statuslabel,
            ];
        }

        // Default order: participant name A→Z (client can re-sort).
        usort($rows, fn($a, $b) => strcmp($a['sortname'], $b['sortname']));

        return [
            'has'           => true,
            'is_instructor' => true,
            'rows'          => $rows,
            'count'         => count($rows),
            'gradebook_url' => (new moodle_url('/grade/report/index.php', ['id' => $cid]))->out(false),
        ];
    }

    /**
     * Map a final grade to a {key, label} status pair for the instructor roster.
     *
     * @param float|null $final     Final grade, or null/false when ungraded.
     * @param float      $gradepass Pass threshold (0 when none configured).
     * @return array{0: string, 1: string}
     */
    private static function grade_status($final, float $gradepass): array {
        if ($final === null || $final === false) {
            return ['pending', get_string('cl_grade_pending', 'theme_learnovatheme')];
        }
        if ($gradepass > 0) {
            return $final >= $gradepass
                ? ['passed', get_string('cl_grade_passed', 'theme_learnovatheme')]
                : ['pending', get_string('cl_grade_pending', 'theme_learnovatheme')];
        }
        return ['passed', get_string('cl_grade_graded', 'theme_learnovatheme')];
    }

    /**
     * Short type label for an activity ("Quiz", "SCORM", …). Falls back to the
     * module's localized plugin name so every module — core or third-party —
     * gets a sensible label.
     *
     * @param \cm_info $cm
     * @return string
     */
    public static function activity_type_label($cm): string {
        static $map = [
            'quiz'           => 'Quiz',
            'scorm'          => 'SCORM',
            'assign'         => 'Assignment',
            'resource'       => 'File',
            'url'            => 'Link',
            'page'           => 'Page',
            'book'           => 'Book',
            'folder'         => 'Folder',
            'forum'          => 'Forum',
            'lesson'         => 'Lesson',
            'h5pactivity'    => 'H5P',
            'glossary'       => 'Glossary',
            'data'           => 'Database',
            'wiki'           => 'Wiki',
            'workshop'       => 'Workshop',
            'choice'         => 'Choice',
            'feedback'       => 'Feedback',
            'survey'         => 'Survey',
            'lti'            => 'Tool',
            'chat'           => 'Chat',
            'bigbluebuttonbn' => 'Live',
        ];
        if (isset($map[$cm->modname])) {
            return $map[$cm->modname];
        }
        return get_string('pluginname', $cm->modname);
    }

    // ── Sidebar progress card ────────────────────────────────────────────────

    /**
     * Sidebar progress/continue card.
     *
     * @param \stdClass        $course
     * @param int              $cid
     * @param \context_course  $context
     * @param \stdClass        $meta
     * @param int|null         $percent
     * @param int              $totalacts
     * @param int              $doneacts
     * @param array|null       $continue
     * @param bool             $isloggedin
     * @return array
     */
    private static function sidebar($course, int $cid, $context, $meta, ?int $percent,
            int $totalacts, int $doneacts, ?array $continue, bool $isloggedin): array {

        $sb = [];

        // Thumbnail (course overview image; falls back to none → CSS bg).
        $cover = self::course_image($course, $context);
        if ($cover !== null) {
            $sb['thumb'] = $cover;
        }

        $sb['hasprogress'] = ($percent !== null);
        $sb['progress']    = $percent ?? 0;

        // CTA state: not started → "Start"; complete → "Review"; else "Continue".
        if ($percent !== null && $percent >= 100) {
            $sb['cta_state'] = 'review';
            $sb['cta_label'] = get_string('cl_cta_review', 'theme_learnovatheme');
        } else if ($percent !== null && $percent > 0) {
            $sb['cta_state'] = 'continue';
            $sb['cta_label'] = get_string('cl_cta_continue', 'theme_learnovatheme');
        } else {
            $sb['cta_state'] = 'start';
            $sb['cta_label'] = get_string('cl_cta_start', 'theme_learnovatheme');
        }
        $sb['cta_url'] = $continue['url']
            ?? (new moodle_url('/course/view.php', ['id' => $cid]))->out(false);

        // Meta rows.
        if ((int) $meta->learning_hours_minutes > 0) {
            $sb['duration'] = meta_manager::format_learning_hours((int) $meta->learning_hours_minutes);
        }
        if ($totalacts > 0) {
            $sb['completed'] = get_string('cl_completed_of', 'theme_learnovatheme',
                (object) ['done' => $doneacts, 'total' => $totalacts]);
        }

        // Enrolment date (real).
        if ($isloggedin) {
            $enroldate = self::enrol_date($cid);
            if ($enroldate !== null) {
                $sb['enrolled'] = userdate($enroldate, get_string('strftimedate', 'langconfig'));
            }
        }

        // Continue-from panel (real).
        if ($continue !== null) {
            $sb['continue'] = $continue;
        }

        // Certificate state (real, from meta cert_enabled).
        $sb['cert_enabled'] = ((int) ($meta->cert_enabled ?? 0) === 1);
        $sb['cert_earned']  = $sb['cert_enabled'] && ($percent !== null && $percent >= 100);

        return $sb;
    }

    /**
     * The current user's enrolment start time for this course (earliest active
     * user_enrolment), or null.
     *
     * @param int $cid
     * @return int|null
     */
    private static function enrol_date(int $cid): ?int {
        global $DB, $USER;

        // Earliest enrolment start, falling back to when the enrolment row was
        // created (covers methods that leave timestart at 0).
        $time = $DB->get_field_sql(
            "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $cid, 'uid' => $USER->id]
        );
        return ($time !== false && $time !== null && $time > 0) ? (int) $time : null;
    }

    /**
     * Native Moodle course links (Reports, Question banks, Badges, …) that the
     * landing page doesn't already surface as a tab.
     *
     * Currently unused on the landing page itself — the "More →" button now
     * navigates to the Course Management page, whose sidebar menu (next story)
     * will be built from exactly these links.
     *
     * These are NOT hand-built links. Each candidate is matched against Moodle's
     * own course navigation ({@see self::nav_index()}); an item is only emitted
     * when core actually placed a matching node in the menu — i.e. when the
     * current user has the capability for it. So visibility is owned entirely by
     * Moodle: we never check capabilities or build URLs ourselves, we only pick
     * which native nodes to show, in which order, and under which display label.
     *
     * @param int             $cid
     * @param \context_course $context
     * @return array  Ordered list of {label, url}.
     */
    private static function more_links(int $cid, $context): array {
        // Story 6.5 order. Each row: [display label, URL-path fragments,
        // node-text fragments]. A node matches when its URL path contains any
        // fragment OR (fallback) its text contains any text fragment (lowercase).
        // Text fallbacks cover plugin nodes whose file path we can't assume.
        $wanted = [
            ['cl_more_reports',          ['/report/view.php'],                       ['reports']],
            ['cl_more_questionbanks',    ['/question/edit.php', '/question/bank'],   ['question bank']],
            ['cl_more_contentbank',      ['/contentbank/'],                          ['content bank']],
            ['cl_more_coursecompletion', ['/course/completion.php'],                 ['course completion']],
            ['cl_more_badges',           ['/badges/view.php'],                       ['badges']],
            ['cl_more_competencies',     ['coursecompetencies.php'],                 ['competencies']],
            ['cl_more_filters',          ['/filter/manage.php'],                     ['filters']],
            ['cl_more_unenrol',          ['/enrol/unenrolself.php'],                 ['unenrol']],
            ['cl_more_accessibility',    ['/admin/tool/brickfield/'],                ['accessibility']],
            ['cl_more_resetcompletion',  ['resetmycompletion'],                      ['reset my activity completion', 'reset activity completion']],
            ['cl_more_recompletion',     ['/local/recompletion/recompletion.php'],   ['recompletion']],
            ['cl_more_modifycompletion', ['editcompletion', 'completiondates'],      ['modify', 'completion dates']],
            ['cl_more_reuse',            ['/backup/import.php', '/backup/view.php', '/backup/'], ['course reuse', 'reuse']],
        ];

        $nodes = self::nav_index();
        if (empty($nodes)) {
            return [];
        }

        $links = [];
        $used  = [];
        foreach ($wanted as [$labelkey, $paths, $texts]) {
            foreach ($nodes as $i => $node) {
                if (isset($used[$i])) {
                    continue;
                }
                if (self::nav_matches($node, $paths, $texts)) {
                    $links[] = [
                        'label' => get_string($labelkey, 'theme_learnovatheme'),
                        'url'   => $node['url'],
                    ];
                    $used[$i] = true;
                    break;
                }
            }
        }

        return $links;
    }

    /**
     * Flatten Moodle's native course navigation (secondary + settings) into a
     * de-duplicated list of {url, path, text} for every node that has an action.
     *
     * The navigation is already capability-filtered by core, so a node is only
     * present here when the current user is allowed to reach it — which is what
     * lets {@see self::more_links()} delegate all visibility to Moodle.
     *
     * @return array  List of ['url' => string, 'path' => string, 'text' => string].
     */
    private static function nav_index(): array {
        global $PAGE;

        $out  = [];
        $seen = [];
        foreach ([$PAGE->secondarynav, $PAGE->settingsnav] as $root) {
            if ($root instanceof \navigation_node) {
                self::flatten_nav($root, $out, $seen);
            }
        }
        return $out;
    }

    /**
     * Recursively collect actionable nodes from a navigation tree.
     *
     * @param \navigation_node $node
     * @param array            $out   Accumulated nodes (by reference).
     * @param array            $seen  URL set for de-duplication (by reference).
     */
    private static function flatten_nav(\navigation_node $node, array &$out, array &$seen): void {
        foreach ($node->children as $child) {
            $url = null;
            if ($child->action instanceof \moodle_url) {
                $url = $child->action;
            } else if ($child->action instanceof \action_link) {
                $url = $child->action->url;
            }
            if ($url !== null) {
                $href = $url->out(false);
                if (!isset($seen[$href])) {
                    $seen[$href] = true;
                    $out[] = [
                        'url'  => $href,
                        'path' => $url->get_path(),
                        'text' => \core_text::strtolower(trim((string) $child->text)),
                    ];
                }
            }
            if ($child->has_children()) {
                self::flatten_nav($child, $out, $seen);
            }
        }
    }

    /**
     * Whether a flattened nav node matches a wanted item by URL path or text.
     *
     * @param array $node   {url, path, text}
     * @param array $paths  URL-path fragments (substring match on the path).
     * @param array $texts  Lowercase node-text fragments (substring match).
     * @return bool
     */
    private static function nav_matches(array $node, array $paths, array $texts): bool {
        foreach ($paths as $p) {
            if (strpos($node['path'], $p) !== false) {
                return true;
            }
        }
        foreach ($texts as $t) {
            if ($t !== '' && strpos($node['text'], $t) !== false) {
                return true;
            }
        }
        return false;
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    /**
     * First usable course overview image URL, or null.
     *
     * @param \stdClass       $course
     * @param \context_course $context
     * @return string|null
     */
    private static function course_image($course, $context): ?string {
        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if (!$file->is_valid_image()) {
                continue;
            }
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out(false);
        }
        return null;
    }

    // ── Activity type icons (Learnova colored set) ───────────────────────────

    /**
     * Link to an activity, asking the module for its page rather than its file.
     *
     * mod_resource sends anything it does not embed straight to pluginfile.php
     * — and a PDF on the default "Automatic" setting is one of those, because
     * resource_get_final_display_type() only auto-embeds images, video and
     * audio. The learner therefore lands on the browser's own PDF viewer and
     * never reaches focus mode at all. `forceview` is the parameter core
     * provides for exactly this case, and core's own activity navigation adds
     * it to every activity link for the same reason (see
     * course/classes/output/activity_navigation.php).
     *
     * Scoped to the activities focus mode can actually render itself. Left
     * alone, every other file type would trade one click straight to the file
     * for a page carrying a link to it — no gain, one more click.
     *
     * @param \cm_info $cm
     * @return string Empty when the module has no view page.
     */
    public static function activity_url($cm): string {
        global $CFG;

        if (!$cm->url) {
            return '';
        }
        // The cm's icon is derived from the main file's mimetype by
        // resource_get_coursemodule_info(), so this reads the file type out of
        // modinfo without touching the database.
        if ($cm->modname !== 'resource' || $cm->icon !== 'f/pdf') {
            return $cm->url->out(false);
        }

        require_once($CFG->libdir . '/resourcelib.php');
        // "Force download" is the one intent the viewer cannot stand in for.
        if (self::resource_display($cm) === RESOURCELIB_DISPLAY_DOWNLOAD) {
            return $cm->url->out(false);
        }

        return (new moodle_url($cm->url, ['forceview' => 1]))->out(false);
    }

    /**
     * Stored display option of a File activity.
     *
     * Read for the whole course at once and kept for the request: the course
     * landing asks this for every PDF it lists, and modinfo does not carry the
     * option itself.
     *
     * @param \cm_info $cm
     * @return int One of the RESOURCELIB_DISPLAY_* constants.
     */
    private static function resource_display($cm): int {
        global $DB;
        static $cache = [];

        $courseid = (int) $cm->course;
        if (!array_key_exists($courseid, $cache)) {
            $cache[$courseid] = $DB->get_records_menu('resource', ['course' => $courseid], '', 'id, display');
        }
        return (int) ($cache[$courseid][$cm->instance] ?? RESOURCELIB_DISPLAY_AUTO);
    }

    /**
     * URL of a Learnova course-landing activity icon by its type slug.
     * Resolves to theme/learnovatheme/pix/icons/course_landing/<type>.svg.
     *
     * @param string $type
     * @return string
     */
    public static function act_icon_url(string $type): string {
        global $OUTPUT;
        return $OUTPUT->image_url('icons/course_landing/' . $type, 'theme_learnovatheme')->out(false);
    }

    /**
     * Map a course module to its Learnova icon type slug. The slug is both the
     * SVG filename and the CSS modifier used for the tinted background.
     *
     * Unknown module types fall back via {@see self::purpose_icon()}, so every
     * activity always renders with a proper colored icon.
     *
     * @param \cm_info $cm
     * @return string
     */
    public static function activity_icon_type($cm): string {
        $slug = self::icon_type_for_modname($cm->modname);
        // Unknown module → map by declared activity purpose (plugin-aware).
        return $slug ?? self::purpose_icon($cm->modname);
    }

    /**
     * Learnova icon-type slug for a module name, or null when unmapped (the
     * caller then falls back by purpose). Split out from {@see self::activity_icon_type()}
     * so the Add Activity grid can resolve an icon from a modname alone.
     *
     * @param string $modname
     * @return string|null
     */
    public static function icon_type_for_modname(string $modname): ?string {
        switch ($modname) {
            // Assess / amber.
            case 'assign':
            case 'assignment':   return 'assignment';
            case 'quiz':         return 'quiz';
            case 'lesson':       return 'lesson';
            case 'glossary':     return 'glossary';
            case 'data':         return 'database';
            case 'journal':      return 'journal';
            case 'attendance':   return 'attendance';
            case 'checklist':    return 'checklist';
            case 'customcert':
            case 'certificate':  return 'certificate';
            case 'pdfannotator': return 'pdf-annotation';
            // Discuss / blue.
            case 'forum':        return 'forum';
            case 'chat':         return 'chat';
            case 'wiki':         return 'wiki';
            case 'workshop':     return 'workshop';
            case 'dialogue':     return 'dialogue';
            case 'peerassessment':
            case 'peerwork':     return 'peer-assessment';
            // Interactive / red.
            case 'h5pactivity':  return 'h5p';
            case 'scorm':        return 'scorm';
            case 'lti':          return 'external-tool';
            case 'game':         return 'game';
            // Live / green.
            case 'bigbluebuttonbn': return 'bigbluebutton';
            case 'zoom':         return 'zoom';
            case 'scheduler':    return 'scheduler';
            // Feedback / purple.
            case 'choice':       return 'choice';
            case 'survey':       return 'survey';
            case 'feedback':     return 'feedback';
            case 'questionnaire': return 'questionnaire';
            // Consume / teal.
            case 'resource':     return 'file';
            case 'folder':       return 'folder';
            case 'page':         return 'page';
            case 'url':          return 'url';
            case 'book':         return 'book';
            case 'label':        return 'label';
            case 'imscp':        return 'ims-package';
            case 'lightboxgallery': return 'gallery';
            // Unmapped → let the caller resolve by activity purpose.
            default:             return null;
        }
    }

    /**
     * Fallback icon slug derived from a module's declared activity purpose
     * ({@see FEATURE_MOD_PURPOSE}). This is what makes the mapping plugin-aware:
     * any installed module — core or third-party — reports a purpose, which we
     * fold onto a representative icon of the matching learning-experience
     * category. Modules with no/own purpose land on the neutral "file" icon.
     *
     * @param string $modname
     * @return string
     */
    private static function purpose_icon(string $modname): string {
        $purpose = plugin_supports('mod', $modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);
        switch ($purpose) {
            case MOD_PURPOSE_ASSESSMENT:         return 'assignment';
            case MOD_PURPOSE_COMMUNICATION:      return 'forum';
            case MOD_PURPOSE_COLLABORATION:      return 'wiki';
            case MOD_PURPOSE_CONTENT:            return 'file';
            case MOD_PURPOSE_INTERACTIVECONTENT: return 'h5p';
            default:                             return 'file';
        }
    }
}
