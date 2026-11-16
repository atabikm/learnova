<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use core_course_category;
use context_course;
use moodle_url;
use local_learnova_course\meta_manager;

/**
 * Course Catalogue page (course/index.php) data builder.
 *
 * Mirrors the learnova_course_catalogue_v1 mockup but is driven entirely by
 * real data: native Moodle courses/categories enriched with the
 * {@see \local_learnova_course\meta_manager} course meta. Nothing is invented —
 * any field for which there is no data is simply omitted from the card so the
 * template can hide it.
 */
class catalogue {

    /** Courses shown per page. */
    public const PER_PAGE = 12;

    /** Default sort key. */
    public const SORT_DEFAULT = 'relevant';

    /** Active search query for the current request (threaded into built URLs). */
    private static string $search = '';

    /**
     * Build the full template context for the catalogue.
     *
     * Reads all filter/sort/page state directly from the request:
     *   cat[]    - selected category ids (multi-select); falls back to the
     *              native single `categoryid` param so homepage links keep working
     *   dur[]    - duration bucket keys (multi-select)
     *   status[] - status keys (multi-select)
     *   sort     - relevant|newest|enrolled|az
     *   page     - zero-based page number
     *
     * @return array
     */
    public static function context(): array {
        global $CFG;

        $page = max(0, optional_param('page', 0, PARAM_INT));
        $sort = self::clean_sort(optional_param('sort', self::SORT_DEFAULT, PARAM_ALPHA));

        // Free-text search, threaded into every built URL so it survives facet
        // toggles, sorting and pagination.
        $search = trim(optional_param('search', '', PARAM_TEXT));
        self::$search = $search;

        // Category filter (multi-select). Accept the native single categoryid as
        // a one-element selection so existing links (homepage, breadcrumbs) work.
        $catfilters = optional_param_array('cat', [], PARAM_INT);
        if (empty($catfilters)) {
            $single = optional_param('categoryid', 0, PARAM_INT);
            if ($single > 0) {
                $catfilters = [$single];
            }
        }
        $catfilters = self::clean_categories($catfilters);

        // Duration / status filters, restricted to known keys.
        $durfilters = array_values(array_intersect(
            optional_param_array('dur', [], PARAM_ALPHANUM),
            array_keys(self::duration_labels())
        ));
        $statusfilters = array_values(array_intersect(
            optional_param_array('status', [], PARAM_ALPHA),
            array_keys(self::status_labels())
        ));

        // Candidate course set: union of the selected categories (recursive), or
        // every visible course when no category is selected. Filtering by
        // duration (meta) and status (per-user) is then done in PHP since the
        // course query can't express them.
        $all = self::courses_for_categories($catfilters);

        // Free-text filter on course name/shortname/summary.
        if ($search !== '') {
            $needle = \core_text::strtolower($search);
            $all = array_values(array_filter($all, function($course) use ($needle) {
                $summary = isset($course->summary) ? (string) $course->summary : '';
                $hay = \core_text::strtolower(
                    $course->fullname . ' ' . $course->shortname . ' ' . strip_tags($summary)
                );
                return strpos($hay, $needle) !== false;
            }));
        }

        $metamin = self::meta_minutes_map();
        list($enrolledids, $completedids) = self::user_status_maps();

        $filtered = [];
        foreach ($all as $course) {
            $cid = (int) $course->id;

            if (!empty($durfilters)) {
                $bucket = isset($metamin[$cid]) ? self::bucket($metamin[$cid]) : null;
                if ($bucket === null || !in_array($bucket, $durfilters, true)) {
                    continue;
                }
            }
            if (!empty($statusfilters)) {
                $st = self::status_of($cid, $enrolledids, $completedids);
                if (!in_array($st, $statusfilters, true)) {
                    continue;
                }
            }
            $filtered[] = $course;
        }

        $filtered = self::sort_courses($filtered, $sort);

        $total   = count($filtered);
        $perpage = self::PER_PAGE;
        $pages   = max(1, (int) ceil($total / $perpage));
        $page    = max(0, min($page, $pages - 1));

        $cards = [];
        foreach (array_slice($filtered, $page * $perpage, $perpage) as $course) {
            $cards[] = self::build_card($course, $enrolledids, $completedids);
        }

        $stats      = self::stats();
        $categories = self::category_facets($catfilters, $durfilters, $statusfilters, $sort);
        $durations  = self::duration_facets($catfilters, $durfilters, $statusfilters, $sort, $metamin, $all);
        $statuses   = self::status_facets($catfilters, $durfilters, $statusfilters, $sort, $enrolledids, $completedids, $all);

        $context = [
            'wwwroot'       => $CFG->wwwroot,
            'allurl'        => (new moodle_url('/course/index.php'))->out(false),
            'herotitle'     => get_string('catalogue_title', 'theme_learnovatheme'),
            'herosubtitle'  => get_string('catalogue_subtitle', 'theme_learnovatheme', $total),
            'searchaction'  => (new moodle_url('/course/index.php'))->out(false),
            'searchvalue'   => $search,
            'hiddenfilters' => self::hidden_filters($catfilters, $durfilters, $statusfilters, $sort),
            'cards'         => $cards,
            'hascards'      => !empty($cards),
            'emptymsg'      => ($search !== '')
                ? get_string('search_empty', 'theme_learnovatheme', $search)
                : get_string('catalogue_empty', 'theme_learnovatheme'),
            'shown'         => count($cards),
            'total'         => $total,
            'stats'         => $stats,
            'hasstats'      => !empty($stats),
            'hassidebar'    => (!empty($categories) || !empty($durations) || !empty($statuses)),
            'categories'    => $categories,
            'hascategories' => !empty($categories),
            'durations'     => $durations,
            'hasdurations'  => !empty($durations),
            'statuses'      => $statuses,
            'hasstatuses'   => !empty($statuses),
            'hassort'       => true,
            'sortoptions'   => self::sort_options($catfilters, $durfilters, $statusfilters, $sort),
        ];

        // Active filter chips (search + each category + duration + status), each
        // with a link that removes just that filter, plus a "clear all" link.
        $active = [];
        if ($search !== '') {
            $active[] = [
                'label' => get_string('catalogue_search_chip', 'theme_learnovatheme', $search),
                // Drop the search term but keep the other filters.
                'url'   => self::build_url($catfilters, $durfilters, $statusfilters, $sort, ''),
            ];
        }
        foreach ($catfilters as $cid) {
            $cat = core_course_category::get($cid, IGNORE_MISSING, true);
            if (!$cat) {
                continue;
            }
            $active[] = [
                'label' => format_string($cat->get_formatted_name()),
                'url'   => self::build_url(self::without_int($catfilters, $cid), $durfilters, $statusfilters, $sort),
            ];
        }
        $durlabels = self::duration_labels();
        foreach ($durfilters as $k) {
            $active[] = [
                'label' => $durlabels[$k],
                'url'   => self::build_url($catfilters, self::without($durfilters, $k), $statusfilters, $sort),
            ];
        }
        $statuslabels = self::status_labels();
        foreach ($statusfilters as $k) {
            $active[] = [
                'label' => $statuslabels[$k],
                'url'   => self::build_url($catfilters, $durfilters, self::without($statusfilters, $k), $sort),
            ];
        }
        $context['activefilters']    = $active;
        $context['hasactivefilters'] = !empty($active);
        $context['clearurl']         = self::build_url([], [], [], $sort, '');

        // Pagination (only when there is more than one page).
        if ($pages > 1) {
            $context['haspagination'] = true;
            $context['pages']         = self::pagination($catfilters, $durfilters, $statusfilters, $sort, $page, $pages);
        }

        return $context;
    }

    // ── Front-page "Latest Courses" section ────────────────────────────────

    /** Fixed number of cards in the front-page courses section (design: one row). */
    public const SECTION_SLOTS = 3;

    /**
     * Newest visible courses as catalogue cards, for the front-page "Latest
     * Courses in Catalogue" section (the Automatic data-source mode). Same card
     * builder as the catalogue, so any field without data (cover image, duration,
     * modules, …) is simply omitted. Returns [] when there are no visible courses.
     *
     * @param int $limit Maximum number of cards.
     * @return array
     */
    public static function latest(int $limit = self::SECTION_SLOTS): array {
        $courses = core_course_category::top()->get_courses(['recursive' => true]);
        if (empty($courses)) {
            return [];
        }

        // Newest first by creation time.
        usort($courses, fn($a, $b) => (int) $b->timecreated <=> (int) $a->timecreated);
        $courses = array_slice($courses, 0, max(0, $limit));

        list($enrolledids, $completedids) = self::user_status_maps();

        $cards = [];
        foreach ($courses as $course) {
            $cards[] = self::build_card($course, $enrolledids, $completedids);
        }
        return $cards;
    }

    // ── Popularity ranking (Automatic mode, without rating) ────────────────

    /**
     * Weight of the completion-rate signal in the popularity score.
     * completion_rate + voluntary-enrolment weights should sum to 1.
     */
    private const WEIGHT_COMPLETION = 0.6;

    /** Weight of the (normalised) voluntary-enrolment signal in the score. */
    private const WEIGHT_ENROLMENT = 0.4;

    /**
     * Most-popular visible courses as catalogue cards (the Automatic data-source
     * mode). Popularity blends two real signals, with rating intentionally left
     * out until a course-rating feature exists:
     *
     *   - completion rate         = completed users / enrolled users (0..1)
     *   - voluntary enrolment      = distinct self-enrolled users, min-max
     *                                normalised across the candidate set (0..1)
     *
     * Ties break on recency (newest first). Returns [] when no visible course
     * exists (the caller may then fall back to {@see self::latest()}).
     *
     * @param int $limit Maximum number of cards.
     * @return array
     */
    public static function popular(int $limit = self::SECTION_SLOTS): array {
        $courses = core_course_category::top()->get_courses(['recursive' => true]);
        if (empty($courses)) {
            return [];
        }

        $candidates = [];
        foreach ($courses as $course) {
            $candidates[(int) $course->id] = $course;
        }

        $ids       = array_keys($candidates);
        $enrolled  = self::enrolment_counts($ids, false); // all enrol methods
        $selfenrol = self::enrolment_counts($ids, true);  // self-enrol only
        $completed = self::completion_counts($ids);

        // Normalisation base for the unbounded voluntary-enrolment signal.
        $maxself = 0;
        foreach ($ids as $cid) {
            $maxself = max($maxself, $selfenrol[$cid] ?? 0);
        }

        // Score = weighted blend of completion rate and normalised voluntary
        // enrolment. Both components are in 0..1 so the weights set their mix.
        $scores = [];
        foreach ($ids as $cid) {
            $enr      = $enrolled[$cid] ?? 0;
            $rate     = $enr > 0 ? (($completed[$cid] ?? 0) / $enr) : 0.0;
            $selfnorm = $maxself > 0 ? (($selfenrol[$cid] ?? 0) / $maxself) : 0.0;
            $scores[$cid] = self::WEIGHT_COMPLETION * $rate + self::WEIGHT_ENROLMENT * $selfnorm;
        }

        // Highest score first; newest first on ties.
        uasort($candidates, function($a, $b) use ($scores) {
            $cmp = $scores[(int) $b->id] <=> $scores[(int) $a->id];
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int) $b->timecreated <=> (int) $a->timecreated;
        });

        $top = array_slice($candidates, 0, max(0, $limit), true);

        list($enrolledids, $completedids) = self::user_status_maps();
        $cards = [];
        foreach ($top as $course) {
            $cards[] = self::build_card($course, $enrolledids, $completedids);
        }
        return $cards;
    }


    /**
     * Distinct enrolled-user counts per course, keyed by course id.
     *
     * @param int[] $courseids
     * @param bool  $selfonly When true, counts only self-enrolment instances
     *                        (voluntary); otherwise counts all enrol methods.
     * @return array<int, int>
     */
    private static function enrolment_counts(array $courseids, bool $selfonly): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $where = "e.courseid $insql";
        if ($selfonly) {
            $where .= " AND e.enrol = :selfenrol";
            $params['selfenrol'] = 'self';
        }
        $rows = $DB->get_records_sql(
            "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS cnt
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE $where
           GROUP BY e.courseid",
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->courseid] = (int) $r->cnt;
        }
        return $map;
    }

    /**
     * Distinct completed-user counts per course (course_completions with a
     * non-null timecompleted), keyed by course id.
     *
     * @param int[] $courseids
     * @return array<int, int>
     */
    private static function completion_counts(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT course AS courseid, COUNT(DISTINCT userid) AS cnt
               FROM {course_completions}
              WHERE timecompleted IS NOT NULL AND course $insql
           GROUP BY course",
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->courseid] = (int) $r->cnt;
        }
        return $map;
    }

    /**
     * Admin-curated courses as catalogue cards, in the stored order (the Admin
     * Curated data-source mode). The admin chooses which courses appear and their
     * order via the courses_items setting in Home Settings (JSON [{courseid}]).
     * Entries pointing at a missing or invisible course are skipped, so cards
     * self-heal when a course is hidden or deleted. Returns [] when nothing
     * resolves (the caller may then fall back to {@see self::latest()}).
     *
     * @param int $limit Maximum number of cards.
     * @return array
     */
    public static function curated(int $limit = self::SECTION_SLOTS): array {
        $ids = self::curated_ids();
        if (empty($ids)) {
            return [];
        }

        // Reuse the visibility-filtered course set so curated cards honour the
        // same access rules as the catalogue, then pick them in the stored order.
        $map = [];
        foreach (core_course_category::top()->get_courses(['recursive' => true]) as $course) {
            $map[(int) $course->id] = $course;
        }

        list($enrolledids, $completedids) = self::user_status_maps();

        $cards = [];
        foreach ($ids as $id) {
            if (count($cards) >= $limit) {
                break;
            }
            if (!isset($map[$id])) {
                continue;
            }
            $cards[] = self::build_card($map[$id], $enrolledids, $completedids);
        }
        return $cards;
    }

    /**
     * Decoded list of admin-curated course ids (deduplicated, in stored order)
     * from the courses_items JSON setting.
     *
     * @return int[]
     */
    public static function curated_ids(): array {
        $raw = get_config('theme_learnovatheme', 'courses_items');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $ids  = [];
        $seen = [];
        foreach ($decoded as $entry) {
            $id = is_array($entry) ? (int) ($entry['courseid'] ?? 0) : 0;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * Visible courses as {id, name} options for the curated-picker dropdown,
     * ordered by full name. The site course is excluded (not returned by the
     * category course listing).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function course_options(): array {
        $options = [];
        foreach (core_course_category::top()->get_courses(['recursive' => true, 'sort' => ['fullname' => 1]]) as $course) {
            $options[] = [
                'id'   => (int) $course->id,
                'name' => format_string($course->get_formatted_name()),
            ];
        }
        return $options;
    }

    /**
     * Build a single course card from a course list element + its Learnova meta.
     *
     * @param \core_course_list_element $course
     * @param array $enrolledids  Set of the user's enrolled course ids (id => true).
     * @param array $completedids Set of the user's completed course ids (id => true).
     * @return array
     */
    private static function build_card($course, array $enrolledids, array $completedids): array {
        $cid = (int) $course->id;
        $card = [
            'url'   => (new moodle_url('/course/view.php', ['id' => $cid]))->out(false),
            'title' => format_string($course->get_formatted_name()),
            'tags'  => [],
        ];

        // Category name → primary tag (always available).
        $cat = core_course_category::get($course->category, IGNORE_MISSING, true);
        if ($cat) {
            $card['tags'][] = ['label' => format_string($cat->get_formatted_name()), 'tone' => 'teal'];
        }

        // Cover image — only the first valid image among overview files.
        $thumb = self::course_image($course);
        if ($thumb !== null) {
            $card['hasthumb'] = true;
            $card['thumb']    = $thumb;
        }

        // Module count (visible course modules).
        $modcount = self::module_count($cid);
        if ($modcount > 0) {
            $card['hasmodules'] = true;
            $card['modules']    = get_string('catalogue_modules', 'theme_learnovatheme', $modcount);
        }

        // Learnova meta — only when a real row exists for this course.
        $meta = meta_manager::get($cid);
        if (!empty($meta->id) && (int) $meta->learning_hours_minutes > 0) {
            $card['hasduration'] = true;
            $card['duration']    = meta_manager::format_learning_hours((int) $meta->learning_hours_minutes);
        }

        // Footer state: enrolled → progress + "Continue"; not enrolled →
        // enrolled count + "Mulai".
        self::apply_card_state($card, $course, $cid, $enrolledids, $completedids);

        return $card;
    }

    /**
     * Decide the card's footer/button state from the user's enrolment + progress.
     *
     * @param array $card        Card data (modified in place).
     * @param \core_course_list_element $course
     * @param int   $cid
     * @param array $enrolledids
     * @param array $completedids
     */
    private static function apply_card_state(array &$card, $course, int $cid, array $enrolledids, array $completedids): void {
        global $USER;

        if (isset($enrolledids[$cid])) {
            // The user is enrolled — show progress and a "Continue" action.
            $card['isenrolled'] = true;
            $card['btnlabel']   = get_string('catalogue_continue', 'theme_learnovatheme');

            if (isset($completedids[$cid])) {
                $card['hasprogress'] = true;
                $card['progress']    = 100;
                $card['footnote']    = get_string('catalogue_completed_note', 'theme_learnovatheme');
            } else {
                $percent = \core_completion\progress::get_course_progress_percentage(get_course($cid), $USER->id);
                if ($percent !== null) {
                    $card['hasprogress'] = true;
                    $card['progress']    = (int) round($percent);
                    $card['footnote']    = get_string('catalogue_progress_note', 'theme_learnovatheme', (int) round($percent));
                } else {
                    $card['footnote'] = get_string('catalogue_enrolled_note', 'theme_learnovatheme');
                }
            }
            return;
        }

        // Not enrolled — show the live enrolled count and a "Mulai" action.
        $enrolled = count_enrolled_users($course->get_context());
        if ($enrolled > 0) {
            $card['footnote'] = get_string('catalogue_enrolled', 'theme_learnovatheme', $enrolled);
        }
        $card['btnlabel'] = get_string('catalogue_view', 'theme_learnovatheme');
    }

    /**
     * Resolve the first usable cover image URL for a course, or null.
     *
     * @param \core_course_list_element $course
     * @return string|null
     */
    private static function course_image($course): ?string {
        if (!$course->has_course_overviewfiles()) {
            return null;
        }
        foreach ($course->get_course_overviewfiles() as $file) {
            if (!$file->is_valid_image()) {
                continue;
            }
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            return $url->out(false);
        }
        return null;
    }

    /**
     * Count visible course modules for a course.
     *
     * @param int $courseid
     * @return int
     */
    private static function module_count(int $courseid): int {
        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (\Throwable $e) {
            return 0;
        }
        $count = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->uservisible) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Hero stat tiles, in display order. Each is omitted when its value is zero.
     *
     * @return array<int, array{num: string, lbl: string, tone: string}>
     */
    public static function stats(): array {
        global $DB;

        $stats = [];

        // Total visible courses (excludes the site course).
        $courses = (int) core_course_category::top()->get_courses_count(['recursive' => true]);
        if ($courses > 0) {
            $stats[] = [
                'num'  => self::compact($courses),
                'lbl'  => get_string('catalogue_stat_courses', 'theme_learnovatheme'),
                'tone' => 'teal',
            ];
        }

        // Visible categories.
        $categories = count(core_course_category::make_categories_list());
        if ($categories > 0) {
            $stats[] = [
                'num'  => self::compact($categories),
                'lbl'  => get_string('catalogue_stat_categories', 'theme_learnovatheme'),
                'tone' => '',
            ];
        }

        // Distinct enrolled learners across the site.
        $learners = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid'
        );
        if ($learners > 0) {
            $stats[] = [
                'num'  => self::compact($learners),
                'lbl'  => get_string('catalogue_stat_learners', 'theme_learnovatheme'),
                'tone' => 'amber',
            ];
        }

        return $stats;
    }

    /**
     * Category sidebar facets (multi-select). Each visible top-level category
     * gets a toggle link that adds/removes it from the selection, preserving the
     * active duration/status/sort. "All Categories" clears the category filter.
     *
     * @param array  $cat    Selected category ids.
     * @param array  $dur    Active duration bucket keys.
     * @param array  $status Active status keys.
     * @param string $sort   Active sort key.
     * @return array
     */
    private static function category_facets(array $cat, array $dur, array $status, string $sort): array {
        $facets = [];

        // "All categories" entry — clears the category filter.
        $facets[] = [
            'name'    => get_string('catalogue_all_categories', 'theme_learnovatheme'),
            'count'   => (int) core_course_category::top()->get_courses_count(['recursive' => true]),
            'url'     => self::build_url([], $dur, $status, $sort),
            'checked' => empty($cat),
        ];

        foreach (core_course_category::top()->get_children() as $coursecat) {
            if (!$coursecat->is_uservisible()) {
                continue;
            }
            $count = (int) $coursecat->get_courses_count(['recursive' => true]);
            if ($count === 0) {
                continue;
            }
            $id      = (int) $coursecat->id;
            $checked = in_array($id, $cat, true);
            $newcat  = $checked ? self::without_int($cat, $id) : array_merge($cat, [$id]);
            $facets[] = [
                'name'    => format_string($coursecat->get_formatted_name()),
                'count'   => $count,
                'url'     => self::build_url($newcat, $dur, $status, $sort),
                'checked' => $checked,
            ];
        }

        // Only worth showing when there is at least one real category.
        return count($facets) > 1 ? $facets : [];
    }

    /**
     * Duration sidebar facets, bucketed from the local_learnova_course
     * learning_hours_minutes field. Counts are taken over the current candidate
     * set; each option toggles its bucket in the URL (multi-select), preserving
     * the active category/status/sort. Buckets with no courses are dropped and
     * the whole facet is omitted when there is no meta data.
     *
     * @param array  $cat     Selected category ids.
     * @param array  $dur     Active duration bucket keys.
     * @param array  $status  Active status keys.
     * @param string $sort    Active sort key.
     * @param array  $metamin Map of courseid => learning minutes.
     * @param array  $all     Candidate course list elements.
     * @return array
     */
    private static function duration_facets(array $cat, array $dur, array $status, string $sort, array $metamin, array $all): array {
        if (empty($metamin)) {
            return [];
        }

        $counts = ['under30' => 0, '30to2h' => 0, '2to5h' => 0, 'over5h' => 0];
        foreach ($all as $course) {
            $cid = (int) $course->id;
            if (!isset($metamin[$cid])) {
                continue;
            }
            $counts[self::bucket($metamin[$cid])]++;
        }
        if (array_sum($counts) === 0) {
            return [];
        }

        $facets = [];
        foreach (self::duration_labels() as $key => $label) {
            if ($counts[$key] <= 0) {
                continue;
            }
            $checked = in_array($key, $dur, true);
            $newdur  = $checked ? self::without($dur, $key) : array_merge($dur, [$key]);
            $facets[] = [
                'name'    => $label,
                'count'   => $counts[$key],
                'checked' => $checked,
                'url'     => self::build_url($cat, $newdur, $status, $sort),
            ];
        }
        return $facets;
    }

    /**
     * Status sidebar facets from the current user's enrolment + completion:
     *   - Completed   : enrolled course with a course-completion timestamp
     *   - In Progress : enrolled course that is not yet complete
     *   - Not Started : visible course the user is not enrolled in
     * Only shown to a logged-in, non-guest user. Each option toggles its key in
     * the URL (multi-select), preserving the active category/duration/sort.
     *
     * @param array  $cat          Selected category ids.
     * @param array  $dur          Active duration bucket keys.
     * @param array  $status       Active status keys.
     * @param string $sort         Active sort key.
     * @param array  $enrolledids  Set of enrolled course ids (id => true).
     * @param array  $completedids Set of completed course ids (id => true).
     * @param array  $all          Candidate course list elements.
     * @return array
     */
    private static function status_facets(array $cat, array $dur, array $status, string $sort, array $enrolledids, array $completedids, array $all): array {
        if (!isloggedin() || isguestuser()) {
            return [];
        }

        $counts = ['inprogress' => 0, 'completed' => 0, 'notstarted' => 0];
        foreach ($all as $course) {
            $counts[self::status_of((int) $course->id, $enrolledids, $completedids)]++;
        }

        $facets = [];
        foreach (self::status_labels() as $key => $label) {
            if ($counts[$key] <= 0) {
                continue;
            }
            $checked   = in_array($key, $status, true);
            $newstatus = $checked ? self::without($status, $key) : array_merge($status, [$key]);
            $facets[] = [
                'name'    => $label,
                'count'   => $counts[$key],
                'checked' => $checked,
                'url'     => self::build_url($cat, $dur, $newstatus, $sort),
            ];
        }
        return $facets;
    }

    /**
     * Map of courseid => learning_hours_minutes for every course that has a
     * Learnova meta row.
     *
     * @return array<int, int>
     */
    private static function meta_minutes_map(): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(meta_manager::TABLE)) {
            return [];
        }
        $map = [];
        $recs = $DB->get_records(meta_manager::TABLE, null, '', 'courseid, learning_hours_minutes');
        foreach ($recs as $r) {
            $map[(int) $r->courseid] = (int) $r->learning_hours_minutes;
        }
        return $map;
    }

    /**
     * The current user's enrolment + completion sets, keyed by course id.
     * Returns [[], []] for guests / not-logged-in.
     *
     * @return array{0: array<int, bool>, 1: array<int, bool>}
     */
    private static function user_status_maps(): array {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return [[], []];
        }

        $enrolled = [];
        foreach (array_keys(enrol_get_all_users_courses($USER->id, true, 'id')) as $id) {
            $enrolled[(int) $id] = true;
        }

        $completed = [];
        if (!empty($enrolled)) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED);
            $params['uid'] = $USER->id;
            $rows = $DB->get_fieldset_sql(
                "SELECT course FROM {course_completions}
                  WHERE userid = :uid AND timecompleted IS NOT NULL AND course $insql",
                $params
            );
            foreach ($rows as $c) {
                $completed[(int) $c] = true;
            }
        }
        return [$enrolled, $completed];
    }

    /**
     * Duration bucket key for a number of minutes.
     *
     * @param int $minutes
     * @return string
     */
    private static function bucket(int $minutes): string {
        if ($minutes < 30) {
            return 'under30';
        }
        if ($minutes < 120) {
            return '30to2h';
        }
        if ($minutes < 300) {
            return '2to5h';
        }
        return 'over5h';
    }

    /**
     * Status key for a course given the user's enrolment/completion sets.
     *
     * @param int   $courseid
     * @param array $enrolled
     * @param array $completed
     * @return string
     */
    private static function status_of(int $courseid, array $enrolled, array $completed): string {
        if (isset($completed[$courseid])) {
            return 'completed';
        }
        if (isset($enrolled[$courseid])) {
            return 'inprogress';
        }
        return 'notstarted';
    }

    /** @return array<string, string> Duration bucket key => label. */
    private static function duration_labels(): array {
        return [
            'under30' => get_string('catalogue_dur_under30', 'theme_learnovatheme'),
            '30to2h'  => get_string('catalogue_dur_30to2h', 'theme_learnovatheme'),
            '2to5h'   => get_string('catalogue_dur_2to5h', 'theme_learnovatheme'),
            'over5h'  => get_string('catalogue_dur_over5h', 'theme_learnovatheme'),
        ];
    }

    /** @return array<string, string> Status key => label. */
    private static function status_labels(): array {
        return [
            'inprogress' => get_string('catalogue_status_inprogress', 'theme_learnovatheme'),
            'completed'  => get_string('catalogue_status_completed', 'theme_learnovatheme'),
            'notstarted' => get_string('catalogue_status_notstarted', 'theme_learnovatheme'),
        ];
    }

    /**
     * Build the catalogue query params for the given filter/sort state.
     *
     * @param array  $cat
     * @param array  $dur
     * @param array  $status
     * @param string $sort
     * @return array
     */
    private static function url_params(array $cat, array $dur, array $status, string $sort, ?string $search = null): array {
        $search = $search ?? self::$search;
        $params = [];
        if (!empty($cat)) {
            $params['cat'] = array_values($cat);
        }
        if (!empty($dur)) {
            $params['dur'] = array_values($dur);
        }
        if (!empty($status)) {
            $params['status'] = array_values($status);
        }
        if ($sort !== self::SORT_DEFAULT) {
            $params['sort'] = $sort;
        }
        if ($search !== '') {
            $params['search'] = $search;
        }
        return $params;
    }

    /**
     * Build a catalogue URL carrying the given filter/sort/search state (page
     * reset). $search defaults to the active query; pass '' to drop it.
     *
     * @param array       $cat
     * @param array       $dur
     * @param array       $status
     * @param string      $sort
     * @param string|null $search
     * @return string
     */
    private static function build_url(array $cat, array $dur, array $status, string $sort, ?string $search = null): string {
        return (new moodle_url('/course/index.php', self::url_params($cat, $dur, $status, $sort, $search)))->out(false);
    }

    /**
     * Hidden input descriptors so the hero search form (GET) preserves the active
     * filters/sort when a new search term is submitted.
     *
     * @param array  $cat
     * @param array  $dur
     * @param array  $status
     * @param string $sort
     * @return array<int, array{name: string, value: string}>
     */
    private static function hidden_filters(array $cat, array $dur, array $status, string $sort): array {
        $inputs = [];
        foreach ($cat as $id) {
            $inputs[] = ['name' => 'cat[]', 'value' => (string) $id];
        }
        foreach ($dur as $k) {
            $inputs[] = ['name' => 'dur[]', 'value' => $k];
        }
        foreach ($status as $k) {
            $inputs[] = ['name' => 'status[]', 'value' => $k];
        }
        if ($sort !== self::SORT_DEFAULT) {
            $inputs[] = ['name' => 'sort', 'value' => $sort];
        }
        return $inputs;
    }

    /**
     * Sort dropdown options, each carrying a URL that applies that sort while
     * preserving the active filters.
     *
     * @param array  $cat
     * @param array  $dur
     * @param array  $status
     * @param string $current Active sort key.
     * @return array
     */
    private static function sort_options(array $cat, array $dur, array $status, string $current): array {
        $labels = [
            'relevant' => get_string('catalogue_sort_relevant', 'theme_learnovatheme'),
            'newest'   => get_string('catalogue_sort_newest', 'theme_learnovatheme'),
            'enrolled' => get_string('catalogue_sort_enrolled', 'theme_learnovatheme'),
            'az'       => get_string('catalogue_sort_az', 'theme_learnovatheme'),
        ];
        $options = [];
        foreach ($labels as $key => $label) {
            $options[] = [
                'label'    => $label,
                'url'      => self::build_url($cat, $dur, $status, $key),
                'selected' => ($key === $current),
            ];
        }
        return $options;
    }

    /**
     * Restrict a sort value to the known keys.
     *
     * @param string $sort
     * @return string
     */
    private static function clean_sort(string $sort): string {
        return in_array($sort, ['relevant', 'newest', 'enrolled', 'az'], true) ? $sort : self::SORT_DEFAULT;
    }

    /**
     * Keep only the category ids that exist and are visible to the user.
     *
     * @param array $ids
     * @return int[]
     */
    private static function clean_categories(array $ids): array {
        $clean = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $cat = core_course_category::get($id, IGNORE_MISSING, true);
            if ($cat && $cat->is_uservisible()) {
                $clean[] = $id;
            }
        }
        return $clean;
    }

    /**
     * Candidate course set for the selected categories (union, recursive,
     * deduplicated), or every visible course when no category is selected.
     *
     * @param array $cat Selected category ids.
     * @return \core_course_list_element[]
     */
    private static function courses_for_categories(array $cat): array {
        if (empty($cat)) {
            return core_course_category::top()->get_courses(['recursive' => true, 'sort' => ['fullname' => 1]]);
        }

        $all  = [];
        $seen = [];
        foreach ($cat as $id) {
            $coursecat = core_course_category::get($id, IGNORE_MISSING, true);
            if (!$coursecat || !$coursecat->is_uservisible()) {
                continue;
            }
            foreach ($coursecat->get_courses(['recursive' => true, 'sort' => ['fullname' => 1]]) as $course) {
                if (!isset($seen[$course->id])) {
                    $seen[$course->id] = true;
                    $all[] = $course;
                }
            }
        }
        return $all;
    }

    /**
     * Sort the filtered course list by the chosen key.
     *
     * @param \core_course_list_element[] $courses
     * @param string                      $sort
     * @return array
     */
    private static function sort_courses(array $courses, string $sort): array {
        switch ($sort) {
            case 'newest':
                usort($courses, fn($a, $b) => (int) $b->timecreated <=> (int) $a->timecreated);
                break;

            case 'enrolled':
                $counts = [];
                foreach ($courses as $c) {
                    $counts[$c->id] = count_enrolled_users(context_course::instance($c->id));
                }
                usort($courses, fn($a, $b) => $counts[$b->id] <=> $counts[$a->id]);
                break;

            case 'az':
                usort($courses, fn($a, $b) => strcasecmp(
                    format_string($a->get_formatted_name()),
                    format_string($b->get_formatted_name())
                ));
                break;

            case 'relevant':
            default:
                // Admin-defined order.
                usort($courses, fn($a, $b) => (int) $a->sortorder <=> (int) $b->sortorder);
                break;
        }
        return $courses;
    }

    /**
     * Return $arr without string value $k, reindexed.
     *
     * @param array  $arr
     * @param string $k
     * @return array
     */
    private static function without(array $arr, string $k): array {
        return array_values(array_filter($arr, fn($x) => $x !== $k));
    }

    /**
     * Return $arr without int value $k, reindexed.
     *
     * @param array $arr
     * @param int   $k
     * @return int[]
     */
    private static function without_int(array $arr, int $k): array {
        return array_values(array_filter($arr, fn($x) => (int) $x !== $k));
    }

    /**
     * Build numeric pagination entries, preserving the active filters/sort.
     *
     * @param array  $cat
     * @param array  $dur
     * @param array  $status
     * @param string $sort
     * @param int    $page  Zero-based current page.
     * @param int    $pages Total number of pages.
     * @return array
     */
    private static function pagination(array $cat, array $dur, array $status, string $sort, int $page, int $pages): array {
        $items = [];
        for ($i = 0; $i < $pages; $i++) {
            $params = self::url_params($cat, $dur, $status, $sort);
            $params['page'] = $i;
            $items[] = [
                'label'  => $i + 1,
                'url'    => (new moodle_url('/course/index.php', $params))->out(false),
                'active' => ($i === $page),
            ];
        }
        return $items;
    }

    /**
     * Compact a large integer for the hero tiles, e.g. 12000 → "12K".
     *
     * @param int $n
     * @return string
     */
    private static function compact(int $n): string {
        if ($n >= 1000) {
            $k = $n / 1000;
            return rtrim(rtrim(number_format($k, 1), '0'), '.') . 'K';
        }
        return (string) $n;
    }
}
