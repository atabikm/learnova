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

use moodle_url;

/**
 * Course Management page (management.php) data builder.
 *
 * Mirrors the learnova_course_management_v1 mockup: a 220px sidebar with the
 * "← Back to Course" pill on top and 18 menu items in 5 groups, plus one
 * main-area panel per item. Panels are placeholders for now — each follow-up
 * story replaces one placeholder with the real tool.
 *
 * Menu order/keys are the contract between the sidebar buttons
 * (data-cm-nav) and the panels (data-cm-panel); the AMD module
 * local_learnova_course/management switches them client-side.
 */
class management {

    /** @var string Panel shown when the page opens. */
    const DEFAULT_PANEL = 'settings';

    /**
     * Full template context for templates/management.mustache.
     *
     * The menu adapts to the viewer's role (like the old landing "More"
     * dropdown): each item is capability-checked against the course context
     * and hidden items are simply not rendered — panels included. Groups with
     * no visible items are dropped entirely.
     *
     * @param \stdClass $course
     * @param array $params  Request state: 'pane' (open a specific panel on
     *                       load) plus panel-specific filters (participants:
     *                       psearch/prole/pgroup/ppage).
     * @return array
     */
    public static function context(\stdClass $course, array $params = []): array {
        $context    = \context_course::instance($course->id);
        $coursename = format_string($course->fullname);

        $groups  = [];
        $panels  = [];
        $visible = []; // Visible item keys, in menu order.
        foreach (self::menu() as $grpkey => $items) {
            $group = [
                'label' => get_string('management_grp_' . $grpkey, 'local_learnova_course'),
                'items' => [],
            ];

            foreach ($items as $key => $item) {
                if (!self::is_visible($item, $context)) {
                    continue;
                }
                $label     = get_string('management_menu_' . str_replace('-', '', $key), 'local_learnova_course');
                $visible[] = $key;

                $group['items'][] = [
                    'key'    => $key,
                    'label'  => $label,
                    'icon'   => $item['icon'],
                    'tag'    => isset($item['tag'])
                        ? get_string('management_tag_' . $item['tag'], 'local_learnova_course') : null,
                    'danger' => !empty($item['danger']),
                    'active' => false,
                ];
                $panels[] = self::panel($key, $label, $course, $context, $params) + ['active' => false];
            }

            if (!empty($group['items'])) {
                // A divider is drawn above every rendered group except the first.
                $group['divider'] = !empty($groups);
                $groups[] = $group;
            }
        }

        // Which panel opens on load: an explicitly requested one (e.g. after a
        // participants filter/pagination reload), else Course Settings, else the
        // first item the viewer's role gets.
        $requested = $params['pane'] ?? '';
        if ($requested !== '' && in_array($requested, $visible, true)) {
            $default = $requested;
        } else {
            $default = in_array(self::DEFAULT_PANEL, $visible, true)
                ? self::DEFAULT_PANEL
                : ($visible[0] ?? null);
        }
        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                $item['active'] = ($item['key'] === $default);
            }
            unset($item);
        }
        unset($group);
        foreach ($panels as &$panel) {
            $panel['active'] = ($panel['key'] === $default);
        }
        unset($panel);

        return [
            'coursename' => $coursename,
            'courseurl'  => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'groups'     => $groups,
            'panels'     => $panels,
        ];
    }

    /**
     * Build one panel's context.
     *
     * Most panels are placeholders (a header + "under construction" note). The
     * Course Settings panel instead embeds the real native course settings form
     * (/course/edit.php) in an iframe — chrome-less via the theme's clembed
     * layout, restyled as Learnova cards — so all core, plugin and custom
     * fields render and saving is real. No returnto is set, so native edit.php
     * redirects to the course view on save/cancel; the management AMD then sends
     * the top window there — matching the landing edit panels' "save and return
     * to the course" behaviour.
     *
     * @param string    $key    Menu/panel key.
     * @param string    $label  Localised panel title.
     * @param \stdClass $course
     * @param \context_course $context
     * @param array     $params Request state (see {@see self::context()}).
     * @return array{key: string, label: string, subtitle: string, isframe: bool, isparticipants: bool}
     */
    private static function panel(string $key, string $label, \stdClass $course, \context_course $context, array $params): array {
        // array_merge (not +): the later value must win for the shared type
        // flags, otherwise the type flags stay false from $base.
        $base = [
            'key' => $key, 'label' => $label,
            'isframe' => false, 'isparticipants' => false,
            'isactivities' => false, 'isreports' => false,
            'iscoursereuse' => false, 'isrecyclebin' => false,
            'isgradestabs' => false, 'isqbanks' => false,
            'isbadges' => false, 'iscompetencies' => false,
            'isunenrol' => false, 'isresetcompletion' => false,
        ];

        if ($key === 'settings') {
            $frameurl = new moodle_url('/course/edit.php', [
                'id'      => $course->id,
                'clembed' => 1,
            ]);
            return array_merge($base, [
                'subtitle' => get_string('management_settings_sub', 'local_learnova_course'),
                'isframe'  => true,
                'frameurl' => $frameurl->out(false),
            ]);
        }

        if ($key === 'course-completion') {
            // The native completion form IS this panel: its header sections are
            // exactly the story's cards (General, Condition: Activity
            // completion, … Manual completion by others) and it already carries
            // the Save changes / Cancel buttons. Embedded chrome-less, it gets
            // the same collapsible-card dressing as Course Settings.
            $frameurl = new moodle_url('/course/completion.php', [
                'id'      => $course->id,
                'clembed' => 1,
            ]);
            return array_merge($base, [
                'subtitle' => get_string('management_coursecompletion_sub', 'local_learnova_course'),
                'isframe'  => true,
                'frameurl' => $frameurl->out(false),
            ]);
        }

        if ($key === 'participants') {
            return array_merge($base, [
                'subtitle'       => get_string('management_participants_sub', 'local_learnova_course'),
                'isparticipants' => true,
                'participants'   => participants::context($course, $params),
            ]);
        }

        // NOTE: 'grade-overview' and 'grader-report' have no branch of their own
        // any more — those two panels are now TABS of "Grades View" (see
        // grades::view_context(), which renders them in place). Their menu items
        // are commented out below; the panels themselves are very much alive.

        if ($key === 'activities') {
            return array_merge($base, [
                'subtitle'     => get_string('management_activities_sub', 'local_learnova_course'),
                'isactivities' => true,
                'activities'   => activities::context($course),
            ]);
        }

        if ($key === 'reports') {
            return array_merge($base, [
                'subtitle'  => get_string('management_reports_sub', 'local_learnova_course'),
                'isreports' => true,
                'reports'   => reports::context($course, $context),
            ]);
        }

        if ($key === 'grades-view' || $key === 'grades-setup') {
            $isview = ($key === 'grades-view');
            return array_merge($base, [
                'subtitle' => get_string($isview
                    ? 'management_gradesview_sub' : 'management_gradessetup_sub',
                    'local_learnova_course'),
                'isgradestabs' => true,
                'gradestabs'   => $isview
                    ? grades::view_context($course, $context, $params)
                    : grades::setup_context($course),
            ]);
        }

        if ($key === 'unenrol') {
            return array_merge($base, [
                'subtitle'  => get_string('management_unenrol_sub', 'local_learnova_course'),
                'isunenrol' => true,
                'unenrol'   => my_access::unenrol_context($course, $context),
            ]);
        }

        if ($key === 'reset-completion') {
            return array_merge($base, [
                'subtitle'          => get_string('management_resetcompletion_sub', 'local_learnova_course'),
                'isresetcompletion' => true,
                'resetcompletion'   => my_access::completion_context($course, $context),
            ]);
        }

        if ($key === 'competencies') {
            return array_merge($base, [
                'subtitle'       => get_string('management_competencies_sub', 'local_learnova_course'),
                'iscompetencies' => true,
                'competencies'   => competencies::context($course, $context),
            ]);
        }

        if ($key === 'badges') {
            return array_merge($base, [
                'subtitle' => get_string('management_badges_sub', 'local_learnova_course'),
                'isbadges' => true,
                'badges'   => badges::context($course, $context),
            ]);
        }

        if ($key === 'question-banks') {
            return array_merge($base, [
                'subtitle'  => get_string('management_questionbanks_sub', 'local_learnova_course'),
                'isqbanks'  => true,
                'qbanks'    => question_banks::context($course, $context, $params),
            ]);
        }

        if ($key === 'course-reuse') {
            return array_merge($base, [
                'subtitle'      => get_string('management_coursereuse_sub', 'local_learnova_course'),
                'iscoursereuse' => true,
                'coursereuse'   => course_reuse::context($course, $context),
            ]);
        }

        if ($key === 'recycle-bin') {
            return array_merge($base, [
                'subtitle'     => get_string('management_recyclebin_sub', 'local_learnova_course'),
                'isrecyclebin' => true,
                'recyclebin'   => recyclebin::context($course, $context),
            ]);
        }

        return array_merge($base, [
            'subtitle' => get_string('management_panel_soon', 'local_learnova_course'),
        ]);
    }

    /**
     * Should this menu item render for the current user?
     *
     * 'cap' items require the capability in the course context; 'enrolledonly'
     * items (the MY ACCESS group) require an active enrolment — they act on
     * the viewer's own participation, which admins hovering over the course
     * don't have. Items with neither key are visible to everyone.
     *
     * @param array           $item    Menu definition entry.
     * @param \context_course $context
     * @return bool
     */
    private static function is_visible(array $item, \context_course $context): bool {
        global $CFG;

        if (!empty($item['enrolledonly']) && !is_enrolled($context, null, '', true)) {
            return false;
        }
        if (!empty($item['cap']) && !has_capability($item['cap'], $context)) {
            return false;
        }
        // 'config' items need a site feature switched on — the tool they front
        // is meaningless otherwise, and Moodle's own navigation hides it too.
        if (!empty($item['config']) && empty($CFG->{$item['config']})) {
            return false;
        }
        return true;
    }

    /**
     * The sidebar menu: 5 groups, in mockup order.
     *
     * Keys double as the panel keys. Icons are the mockup's inline heroicons
     * (fill, viewBox 0 0 20 20) injected raw into the template.
     *
     * Visibility ('cap' / 'enrolledonly', see {@see self::is_visible()})
     * mirrors the native Moodle page each tool fronts, so the menu adapts to
     * the viewer's role exactly like Moodle's own course navigation — core
     * capabilities only, never role names. Tool stories that swap a
     * placeholder for a plugin-backed panel (accessibility/brickfield,
     * recompletion) should refine these to the plugin's own capability.
     *
     * @return array<string, array<string, array{icon: string, cap?: string, enrolledonly?: bool, tag?: string, danger?: bool}>>
     */
    private static function menu(): array {
        return [
            'management' => [
                'settings' => [
                    'cap'  => 'moodle/course:update',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
                ],
                'participants' => [
                    'cap'  => 'moodle/course:viewparticipants',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>',
                ],
                // Hidden: superseded by the GRADES group, whose "Grades View"
                // panel fronts Moodle's own User report and Grader report (the
                // full native ones). The panels themselves are kept — see
                // grade_overview / grader_report — so restoring these two lines
                // brings them straight back.
                // 'grade-overview' => [
                //     'cap'  => 'moodle/grade:view',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>',
                // ],
                // 'grader-report' => [
                //     'cap'  => 'moodle/grade:viewall',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>',
                // ],
                'course-completion' => [
                    'cap'    => 'moodle/course:update',
                    'config' => 'enablecompletion',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
                ],
                'activities' => [
                    'cap'  => 'moodle/course:manageactivities',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>',
                ],
            ],
            'grades' => [
                // The two halves of Moodle's own gradebook dropdown: its View
                // section and its Setup section, each as a tabbed panel.
                'grades-view' => [
                    'cap'  => 'moodle/grade:view',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>',
                ],
                'grades-setup' => [
                    'cap'  => 'moodle/grade:manage',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732L14.146 12.8l-1.179 4.456a1 1 0 01-1.934 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732L9.854 7.2l1.179-4.456A1 1 0 0112 2z" clip-rule="evenodd"/></svg>',
                ],
            ],
            'content' => [
                'question-banks' => [
                    'cap'  => 'moodle/question:managecategory',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
                ],
                // Out of scope for now — no story yet. Uncomment to bring the
                // item (and its placeholder panel) back.
                // 'content-bank' => [
                //     'cap'  => 'moodle/contentbank:access',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>',
                // ],
                'competencies' => [
                    'cap'  => 'moodle/competency:coursecompetencyview',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                ],
                'badges' => [
                    'cap'  => 'moodle/badges:viewbadges',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"/></svg>',
                ],
                // 'filters' => [
                //     'cap'  => 'moodle/filter:manage',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>',
                // ],
            ],
            'reports' => [
                'reports' => [
                    'cap'  => 'moodle/site:viewreports',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>',
                ],
            ],
            'reuse' => [
                'course-reuse' => [
                    'cap'  => 'moodle/backup:backupcourse',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"/><path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"/></svg>',
                ],
                'recycle-bin' => [
                    'cap'  => 'tool/recyclebin:viewitems',
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                ],
                // Out of scope for now — no story yet. Uncomment to bring the
                // item (and its placeholder panel) back.
                // 'lti' => [
                //     'cap'  => 'mod/lti:addcoursetool',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>',
                // ],
                // 'accessibility' => [
                //     'cap'  => 'moodle/course:update',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>',
                // ],
                // 'recompletion' => [
                //     'cap'  => 'moodle/course:update',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>',
                // ],
                // 'modify-completion' => [
                //     'cap'  => 'moodle/course:overridecompletion',
                //     'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
                // ],
            ],
            'myaccess' => [
                'unenrol' => [
                    'enrolledonly' => true,
                    'icon'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>',
                ],
                'reset-completion' => [
                    'enrolledonly' => true,
                    'icon' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1z" clip-rule="evenodd"/></svg>',
                ],
            ],
        ];
    }
}
