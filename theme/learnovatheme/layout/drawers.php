<?php
// Learnova override of theme_boost/layout/drawers.php.
// Identical to Boost's drawers layout, but adds one Learnova-specific flag
// (isadmin) to the template context so navbar.mustache can conditionally
// render the gear/site-admin icon.
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// ── Learnova embedded edit form ───────────────────────────────────
// Native Moodle edit forms opened inside a Learnova panel iframe carry
// ?clembed=1. Render them in a chrome-less embedded shell — no navbar, drawers
// or footer — so only the form shows. post.scss restyles the form's native
// collapsible fieldsets as on-brand cards; the wrapping class picks which look:
//   • section/activity forms (course-landing sliding panel) → learnova-cl-embed
//   • course settings form   (Course Management panel)      → learnova-cm-embed
//   • course reports + reuse (Reports / Course Reuse tabs)  → learnova-page-embed
//
// The flag has to survive the form POST: moodleform strips the query string from
// its action, so a validation-error re-submit would otherwise lose clembed and
// fall back to the full-chrome page. The tabbed panels need the same latch for
// pages that rebuild their action URL from scratch — report filter forms, and
// the multi-step backup/restore/import/reset/copy wizards. We latch it in the
// session on the initial GET and honour it while still on one of those scripts;
// any other page render (e.g. the redirect back to the course after
// save/cancel/finish) clears the latch, which is also what tells the panel's JS
// the user has left the embedded flow.
global $SESSION, $CFG;
$clembedparam = optional_param('clembed', 0, PARAM_INT);
$editformpath = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';
$iscoursesettings = (strpos($editformpath, '/course/edit.php') !== false);
// Course completion form — same moodleform shape as the settings form (header
// sections + Save changes/Cancel), so it takes the same card dressing.
$iscompletion = (strpos($editformpath, '/course/completion.php') !== false);
// Badge form (Badges panel slide-in). Also a moodleform with header sections —
// Badge details / Issuer details / Badge expiry — so same dressing again.
$isbadgeform = (strpos($editformpath, '/badges/edit.php') !== false);
// Paths are matched RELATIVE to wwwroot: a bare "/report/" search would also
// catch /grade/report/grader/index.php and strip the chrome off the gradebook.
$relpath = substr($editformpath, strlen($CFG->wwwroot));
$isreportpage = (strpos($relpath, '/report/') === 0);
// Course reuse wizards: backup, restore, import, copy (/backup/*) and reset.
$isreusepage = (strpos($relpath, '/backup/') === 0)
    || (strpos($relpath, '/course/reset.php') === 0);
// Of those, the ones that are plain moodleforms — Reset (five header sections)
// and Copy course (a flat field form) — take the card/label dressing. The
// backup / restore / import wizards do NOT: they render their own
// .backup-section markup, which is dressed separately in post.scss.
$isreuseform = (strpos($relpath, '/course/reset.php') === 0)
    || (strpos($relpath, '/backup/copy.php') === 0);
// Gradebook pages behind the Grades View / Grades Setup tabs. The ones that are
// moodleform pages get the SAME card-and-collapse dressing as the course
// settings embed (identical markup: collapsible header fieldsets), the rest —
// grader report, gradebook setup tree — are tables and take the plain shell.
// Question bank pages behind the Question Banks panel (category management, the
// multi-condition filter, the question editor).
$isqbankpage = (strpos($relpath, '/question/') === 0);
$isgradepage = (strpos($relpath, '/grade/') === 0);
// Which of them are moodleform pages, i.e. built from header fieldsets and
// .fitem rows exactly like the course settings form: course grade settings, the
// report preference screens, and the whole grade import/export flow (their forms
// declare 'header' elements, so they become the same accordion cards).
$isgradeform = $isgradepage && (
    (strpos($relpath, '/grade/edit/settings/') === 0)
    || (strpos($relpath, '/grade/import/') === 0)
    || (strpos($relpath, '/grade/export/') === 0)
    || (substr($relpath, -strlen('/preferences.php')) === '/preferences.php')
);
$iseditformpage = $iscoursesettings
    || $iscompletion
    || $isbadgeform
    || (strpos($editformpath, '/course/modedit.php') !== false)
    || (strpos($editformpath, '/course/editsection.php') !== false);
if ($clembedparam) {
    $SESSION->learnova_clembed = 1;
}
if ($clembedparam
        || (!empty($SESSION->learnova_clembed)
            && ($iseditformpage || $isreportpage || $isreusepage || $isgradepage
                || $isqbankpage))) {
    // Course settings: render the summary field as a plain textarea instead of
    // the heavy TinyMCE inside the panel. editors_get_preferred_editor() reads
    // $CFG->texteditors at the form's toHtml() (display) time, which runs after
    // this layout, so forcing it here downgrades the summary editor cleanly.
    if ($iscoursesettings) {
        $CFG->texteditors = 'textarea';
    }
    // Merge the hook class into the body attributes at render time —
    // add_body_class() is illegal here (output has already started).
    if ($iscoursesettings || $iscompletion || $isbadgeform || $isgradeform || $isreuseform) {
        // Same moodleform markup as the course settings form, so it gets the
        // same treatment: collapsible fieldsets as Learnova cards.
        $embedclass = 'learnova-cm-embed';
    } else if ($isreportpage || $isreusepage || $isgradepage || $isqbankpage) {
        // Reports and the reuse wizards are native pages of tables, filters and
        // multi-step forms — they must NOT inherit the edit-form panel styling,
        // only the plain embed shell.
        $embedclass = 'learnova-page-embed';
    } else {
        $embedclass = 'learnova-cl-embed';
    }
    // Marker shared by every embed variant: it is how the panel JS recognises a
    // document that is still inside the embedded flow (vs a page the flow
    // finished on, which it hands to the top window). Keep it on all of them.
    $embedclasses = [$embedclass, 'learnova-embed'];
    $fakeblockshtml = $OUTPUT->blocks('side-pre', [], 'aside', true);
    $hasfakeblocks = strpos($fakeblockshtml, 'data-block="_fake"') !== false;
    $embedrenderer = $PAGE->get_renderer('core');
    echo $OUTPUT->render_from_template('theme_learnovatheme/edit_embed', [
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes($embedclasses),
        'headercontent' => $PAGE->activityheader->export_for_template($embedrenderer),
        'hasfakeblocks' => $hasfakeblocks,
        'fakeblocks' => $fakeblockshtml,
    ]);
    return;
}
// Any non-embedded render drops the latch so normal pages never inherit it.
unset($SESSION->learnova_clembed);

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

// Course Catalogue (course/index.php) uses the 'coursecategory' layout. Flag it
// so the body gets a hook class — the SCSS hides Moodle's default in-region
// course listing and the custom catalogue renders full-width above it.
$iscatalogue = ($PAGE->pagelayout === 'coursecategory');
if ($iscatalogue) {
    $extraclasses[] = 'learnova-catalogue-page';
}

// Course Landing (course/view.php) uses the 'course' layout + a 'course-view-*'
// pagetype. Render the custom landing full-width above the (hidden) default
// course view. It renders BOTH when viewing and when editing: in edit mode the
// landing overlays its own Learnova edit controls (notice bar, per-item menus,
// add buttons, bulk bar) instead of falling back to Moodle's native course
// editor — see course_landing.mustache / course_landing.php.
// NB: use `$PAGE->course` directly, not empty($PAGE->course) — moodle_page's
// magic __get has no __isset, so empty() wrongly reports it as unset.
$iscourselanding = (strpos($PAGE->pagetype, 'course-view-') === 0)
    && $PAGE->course && (int) $PAGE->course->id !== (int) SITEID;
if ($iscourselanding) {
    $extraclasses[] = 'learnova-course-landing-page';
    // Suppress the left course-index drawer on the landing so the hero is
    // full-width: it's redundant with the custom "Course Content" tab. Closing
    // it here also drops the toggle button and the show-drawer-left offset.
    $courseindexopen = false;
    $extraclasses = array_values(array_diff($extraclasses, ['drawer-open-index']));
}

// Course Management (local/learnova_course/management.php) brings its own
// 220px sidebar — the course-index drawer would stack on top of it, so it
// gets the same treatment as the landing: no drawer, no toggle, no offset.
$ismanagement = ($PAGE->pagetype === 'local-learnova_course-management');
if ($ismanagement) {
    $courseindexopen = false;
    // The right block drawer would squeeze the custom layout too — keep it shut.
    $blockdraweropen = false;
    $extraclasses = array_values(array_diff($extraclasses, ['drawer-open-index']));
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
// Front page: the whole Moodle frontpage surface is replaced by the custom
if ($PAGE->pagelayout === 'frontpage' && !$PAGE->user_is_editing()) {
    $hasblocks = false;
}
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}
// On the landing and the management page, drop the course-index drawer
// entirely (no drawer markup, no toggle, no left margin) — both pages provide
// their own navigation.
if ($iscourselanding || $ismanagement) {
    $courseindex = '';
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $selectmenu = new \core\output\select_menu(
            'tertiarynavigation',
            $overflowdata->urls,
            $overflowdata->selected,
        );
        $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
        $overflow = $selectmenu->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$coursefullname = ($PAGE->course?->fullname) ? format_string(
    $PAGE->course->fullname,
    true,
    ['context' => context_course::instance($PAGE->course->id), 'escape' => false],
) : '';
$courseurl = $PAGE->course ? new \core\url('/course/view.php', ['id' => $PAGE->course->id]) : null;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'coursefullname' => $coursefullname,
    'courseurl' => $courseurl ? $courseurl->out(false) : null,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => (function() use ($OUTPUT, $PAGE) {
        $languagemenu = new \core\output\language_menu($PAGE);
        $data = $languagemenu->export_for_template($OUTPUT);
        if (!empty($data)) {
            if (preg_match('/\(([^)]+)\)\s*$/', $data['title'], $m)) {
                $data['langcode'] = strtoupper($m[1]);
            } else {
                $data['langcode'] = strtoupper(current_language());
            }
            // Uppercase the lang code in each dropdown item, e.g.
            //   "English (en)" → "English (EN)"
            if (!empty($data['items'])) {
                $upper = function($label) {
                    return preg_replace_callback(
                        '/\(([^)]+)\)\s*$/',
                        fn($m) => '(' . strtoupper($m[1]) . ')',
                        $label
                    );
                };
                foreach ($data['items'] as &$item) {
                    if (isset($item['text']))  { $item['text']  = $upper($item['text']); }
                    if (isset($item['title'])) { $item['title'] = $upper($item['title']); }
                }
                unset($item);
            }
        }
        return $data;
    })(),
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,

    // Learnova additions ──────────────────────────────────────────────
    'isadmin'    => is_siteadmin(),
    'adminurl'   => (new moodle_url('/admin/search.php'))->out(false),
    'homesetturl' => (new moodle_url('/admin/settings.php',
        ['section' => 'themesettinglearnovatheme']))->out(false),
    'isguest'    => (!isloggedin() || isguestuser()),
    'year'       => date('Y'),

    // Primary nav active-state flags. Pagetype is set by Moodle from the
    // script path, so it's the most reliable signal for "which top-level
    // section am I in?"
    'nav_home_active'      => ($PAGE->pagetype === 'site-index'),
    'nav_dashboard_active' => ($PAGE->pagetype === 'my-index'),
    'nav_catalogue_active' => (strpos($PAGE->pagetype, 'course-index') === 0
                              || strpos($PAGE->pagetype, 'course-search') === 0),
];

// ── Front-page hero ─────────────────────────────────────────────────
// Render the hero (logged-out OR logged-in variant) only on the site home.
if ($PAGE->pagelayout === 'frontpage') {
    $isguest = (!isloggedin() || isguestuser());

    // Faint background image behind the hero. Uploaded in Home Setting, with a
    // bundled default when unset. Shared by both guest and logged-in variants.
    $heroimage = $PAGE->theme->setting_file_url('hero_image', 'hero_image');
    if (empty($heroimage)) {
        $heroimage = 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=1400&q=80&auto=format&fit=crop';
    }

    if ($isguest) {
        // Hero title is HTML edited via the WYSIWYG in Home Setting. Falls
        // back to the localized default if the admin hasn't opened the form
        // yet. format_text() sanitizes (strips dangerous tags / scripts).
        $titlehtml = get_config('theme_learnovatheme', 'hero_title');
        if ($titlehtml === false || $titlehtml === '') {
            $titlehtml = get_string('hero_title_default', 'theme_learnovatheme');
        }
        $titlehtml = format_text($titlehtml, FORMAT_HTML, [
            'context' => context_system::instance(),
            'filter'  => false,
        ]);

        // Description is plain text (styling is CSS-only). Falls back to the
        // localized default when unset.
        $description = get_config('theme_learnovatheme', 'hero_description');
        if ($description === false || $description === '') {
            $description = get_string('hero_description', 'theme_learnovatheme');
        }

        // Eyebrow is plain text. Falls back to the localized default.
        $eyebrow = get_config('theme_learnovatheme', 'hero_eyebrow');
        if ($eyebrow === false || $eyebrow === '') {
            $eyebrow = get_string('hero_eyebrow', 'theme_learnovatheme');
        }

        $herocontext = [
            'isguest'      => true,
            'wwwroot'      => $CFG->wwwroot,
            'bgimage'      => $heroimage,
            'eyebrow'      => $eyebrow,
            'titlehtml'    => $titlehtml,
            'description'  => $description,
            'cta_primary'  => get_string('hero_cta_primary', 'theme_learnovatheme'),
            'cta_secondary' => get_string('hero_cta_secondary', 'theme_learnovatheme'),
            'courses_url'  => (new moodle_url('/course/index.php'))->out(false),
            // 4 chips chosen in Home Setting, resolved to live data (or manual
            // overrides) by the hero_stats catalogue.
            'stats' => \theme_learnovatheme\hero_stats::selected(),
        ];
    } else {
        $hour = (int) userdate(time(), '%H');
        if ($hour < 12) {
            $greetkey = 'hero_greet_morning';
        } else if ($hour < 17) {
            $greetkey = 'hero_greet_afternoon';
        } else {
            $greetkey = 'hero_greet_evening';
        }

        $herocontext = [
            'isguest'         => false,
            'wwwroot'         => $CFG->wwwroot,
            'bgimage'         => $heroimage,
            'datelabel'       => userdate(time(), get_string('strftimedaydate', 'langconfig')),
            'welcomeback'     => get_string('hero_welcome_back', 'theme_learnovatheme'),
            'greeting'        => get_string($greetkey, 'theme_learnovatheme'),
            'firstname'       => format_string($USER->firstname),
            'progressmessage' => \theme_learnovatheme\hero_message::resolve(),
            // 4 chips chosen in Home Setting, resolved against the learner's own
            // data (or manual overrides) by the user_stats catalogue.
            'stats' => \theme_learnovatheme\user_stats::selected(),
            // PLACEHOLDER: the learning-journey feature is not built yet, so the
            // tag and its progress bar stay hardcoded until it lands.
            'journey_label'   => get_string('hero_journey_label', 'theme_learnovatheme'),
            'journey_name'    => 'E-Learning Content Lead Program',
            'journey_progress_label' => get_string('hero_journey_progress', 'theme_learnovatheme'),
            'journey_progress' => 62,
            'card_dashboard'  => get_string('hero_card_dashboard', 'theme_learnovatheme'),
            'dashboard_url'   => (new moodle_url('/my/'))->out(false),
        ] + \theme_learnovatheme\hero_continue::resolve();
    }

    $templatecontext['hashero']    = true;
    $templatecontext['herocontent'] = $OUTPUT->render_from_template('theme_learnovatheme/hero', $herocontext);

    // ── Announcement band ─────────────────────────────────────────────
    // Site-wide notice below the hero, editable + toggleable in Home Setting.
    $announceenabled = get_config('theme_learnovatheme', 'announce_enabled');
    if ($announceenabled === false) {
        $announceenabled = 1; // Default on.
    }

    if ($announceenabled) {
        $atag = get_config('theme_learnovatheme', 'announce_tag');
        if ($atag === false || $atag === '') {
            $atag = get_string('announce_tag', 'theme_learnovatheme');
        }
        $amsg = get_config('theme_learnovatheme', 'announce_message');
        if ($amsg === false || $amsg === '') {
            $amsg = get_string('announce_message', 'theme_learnovatheme');
        }
        // CTA label: empty (explicitly cleared) hides the link, so only fall
        // back to the default when the setting has never been saved.
        $actalabel = get_config('theme_learnovatheme', 'announce_cta_label');
        if ($actalabel === false) {
            $actalabel = get_string('announce_cta', 'theme_learnovatheme');
        }
        $actaurl = get_config('theme_learnovatheme', 'announce_cta_url');
        if ($actaurl === false || $actaurl === '') {
            $actaurl = (new moodle_url('/course/index.php'))->out(false);
        }

        $announcecontext = [
            'tag'       => $atag,
            'message'   => $amsg,
            'cta_label' => $actalabel,
            'cta_url'   => $actaurl,
        ];
        $templatecontext['hasannounce']    = true;
        $templatecontext['announcecontent'] = $OUTPUT->render_from_template(
            'theme_learnovatheme/announcement',
            $announcecontext
        );
    }

    // ── Featured Programs ──────────────────────────────────────────────
    // Dummy data for now — mirrors the v7_4 mockup. One large lead card plus
    // four compact cards. Swap for live program/course data once wired up.
    $featuredurl = (new moodle_url('/course/index.php'))->out(false);
    $featuredcontext = [
        'title'         => 'Featured Programs',
        'subtitle'      => 'Curated by the L&D team — highlighted this quarter',
        'viewall_label' => 'View all',
        'viewall_url'   => $featuredurl,
        'lead'          => [
            'url'      => $featuredurl,
            'thumb'    => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=800&q=80&auto=format&fit=crop',
            'category' => 'Leadership',
            'title'    => 'E-Learning Content Development Lead Program',
            'meta'     => '5 Modules · 8 Competencies',
            'desc'     => 'A structured program to develop senior e-learning professionals into content development leaders.',
            'pills'    => [
                ['label' => 'Mandatory',      'amber' => true],
                ['label' => '8 Competencies', 'amber' => false],
            ],
            // Set to a string (e.g. 'Enrolled · 62%') to show the progress badge.
            'enrolled' => '',
        ],
        'cards'         => [
            [
                'url'      => $featuredurl,
                'thumb'    => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80&auto=format&fit=crop',
                'category' => 'Management',
                'title'    => 'Leadership Decision Making',
                'desc'     => 'Strategic frameworks for leaders in dynamic environments.',
                'pills'    => [
                    ['label' => '6 Modules', 'amber' => false],
                    ['label' => '2h 30m',    'amber' => false],
                ],
            ],
            [
                'url'      => $featuredurl,
                'thumb'    => 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?w=600&q=80&auto=format&fit=crop',
                'category' => 'Coaching',
                'title'    => 'Performance Coaching for Managers',
                'desc'     => 'Build coaching skills to unlock peak performance in your team.',
                'pills'    => [
                    ['label' => '5 Modules', 'amber' => false],
                    ['label' => '2h',        'amber' => false],
                ],
            ],
            [
                'url'      => $featuredurl,
                'thumb'    => 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=600&q=80&auto=format&fit=crop',
                'category' => 'Compliance',
                'title'    => 'Data Privacy & Security Essentials',
                'desc'     => 'Mandatory compliance for all employees covering PDPA.',
                'pills'    => [
                    ['label' => 'Mandatory', 'amber' => true],
                    ['label' => 'Apr 30',    'amber' => false],
                ],
            ],
            [
                'url'      => $featuredurl,
                'thumb'    => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?w=600&q=80&auto=format&fit=crop',
                'category' => 'Communication',
                'title'    => 'Conflict Resolution & Negotiation',
                'desc'     => 'Navigate workplace conflict using proven resolution frameworks.',
                'pills'    => [
                    ['label' => '4 Modules', 'amber' => false],
                    ['label' => '1h 45m',    'amber' => false],
                ],
            ],
        ],
    ];
    $templatecontext['hasfeatured']    = true;
    $templatecontext['featuredcontent'] = $OUTPUT->render_from_template(
        'theme_learnovatheme/featured',
        $featuredcontext
    );

    // ── Browse by Category ─────────────────────────────────────────────
    // Native course categories chosen + ordered + icon-assigned in Home
    // Setting. Renders only when enabled and at least one valid card resolves.
    $catsenabled = get_config('theme_learnovatheme', 'cats_enabled');
    if ($catsenabled === false) {
        $catsenabled = 1; // Default on.
    }

    if ($catsenabled) {
        $catcards = \theme_learnovatheme\categories::cards();
        if (!empty($catcards)) {
            $catstitle = get_config('theme_learnovatheme', 'cats_title');
            if ($catstitle === false || $catstitle === '') {
                $catstitle = get_string('cats_title', 'theme_learnovatheme');
            }
            $catssub = get_config('theme_learnovatheme', 'cats_subtitle');
            if ($catssub === false || $catssub === '') {
                $catssub = get_string('cats_subtitle', 'theme_learnovatheme');
            }
            // View-all label: empty (explicitly cleared) hides the link, so
            // only fall back to the default when never saved. The link always
            // targets the course catalogue index (not configurable).
            $catsviewall = get_config('theme_learnovatheme', 'cats_viewall_label');
            if ($catsviewall === false) {
                $catsviewall = get_string('cats_viewall', 'theme_learnovatheme');
            }

            $categoriescontext = [
                'title'         => $catstitle,
                'subtitle'      => $catssub,
                'viewall_label' => $catsviewall,
                'viewall_url'   => (new moodle_url('/course/index.php'))->out(false),
                'cards'         => $catcards,
                // Grid columns follow the card-layout design (max 5 cols / 2 rows).
                'cols'          => \theme_learnovatheme\categories::column_count(count($catcards)),
            ];
            $templatecontext['hascategories']    = true;
            $templatecontext['categoriescontent'] = $OUTPUT->render_from_template(
                'theme_learnovatheme/categories',
                $categoriescontext
            );
        }
    }

    // ── Latest Courses ─────────────────────────────────────────────────
    // A 3-card row built from real data (same card builder as the course
    // catalogue). Two data-source modes, chosen in Home Setting:
    //   curated   → the admin-picked courses, in order (courses_items)
    //   automatic → the 3 most popular courses (completion rate + voluntary
    //               enrolment, excluding mandatory; rating not yet factored in)
    // Each mode falls back to the newest courses when it resolves nothing, so
    // the section is never empty by accident. Fields without data (cover,
    // duration, modules) are omitted per-card; the section is skipped only when
    // there are no courses at all.
    $coursesenabled = get_config('theme_learnovatheme', 'courses_enabled');
    if ($coursesenabled === false) {
        $coursesenabled = 1; // Default on.
    }

    if ($coursesenabled) {
        $coursesmode = get_config('theme_learnovatheme', 'courses_mode');
        if ($coursesmode === false || $coursesmode === '') {
            $coursesmode = 'curated'; // Default mode.
        }

        if ($coursesmode === 'automatic') {
            // Popularity ranking, falling back to newest if nothing ranks (e.g.
            // every course is mandatory and thus excluded).
            $coursecards = \theme_learnovatheme\catalogue::popular();
            if (empty($coursecards)) {
                $coursecards = \theme_learnovatheme\catalogue::latest();
            }
        } else {
            // Curated, falling back to newest when nothing is picked yet.
            $coursecards = \theme_learnovatheme\catalogue::curated();
            if (empty($coursecards)) {
                $coursecards = \theme_learnovatheme\catalogue::latest();
            }
        }

        if (!empty($coursecards)) {
            $coursestitle = get_config('theme_learnovatheme', 'courses_title');
            if ($coursestitle === false || $coursestitle === '') {
                $coursestitle = get_string('courses_title', 'theme_learnovatheme');
            }
            $coursessub = get_config('theme_learnovatheme', 'courses_subtitle');
            if ($coursessub === false || $coursessub === '') {
                $coursessub = get_string('courses_subtitle', 'theme_learnovatheme');
            }
            // View-all label: empty (explicitly cleared) hides the link, so only
            // fall back to the default when never saved.
            $coursesviewall = get_config('theme_learnovatheme', 'courses_viewall_label');
            if ($coursesviewall === false) {
                $coursesviewall = get_string('courses_viewall', 'theme_learnovatheme');
            }

            $coursescontext = [
                'title'         => $coursestitle,
                'subtitle'      => $coursessub,
                'viewall_label' => $coursesviewall,
                'viewall_url'   => (new moodle_url('/course/index.php'))->out(false),
                'cards'         => $coursecards,
            ];
            $templatecontext['hascourses']    = true;
            $templatecontext['coursescontent'] = $OUTPUT->render_from_template(
                'theme_learnovatheme/courses',
                $coursescontext
            );
        }
    }

    // ── Common Questions (FAQ) ─────────────────────────────────────────
    // Image + heading + Q&A pairs, all editable in Home Setting. Renders
    // only when enabled and at least one Q&A pair is filled in.
    $faqenabled = get_config('theme_learnovatheme', 'faq_enabled');
    if ($faqenabled === false) {
        $faqenabled = 1; // Default on.
    }

    if ($faqenabled) {
        $faqitems = \theme_learnovatheme\faq::items();
        if (!empty($faqitems)) {
            $faqeyebrow = get_config('theme_learnovatheme', 'faq_eyebrow');
            if ($faqeyebrow === false) {
                $faqeyebrow = get_string('faq_eyebrow', 'theme_learnovatheme');
            }
            $faqtitle = get_config('theme_learnovatheme', 'faq_title');
            if ($faqtitle === false || $faqtitle === '') {
                $faqtitle = get_string('faq_title', 'theme_learnovatheme');
            }
            $faqsubtitle = get_config('theme_learnovatheme', 'faq_subtitle');
            if ($faqsubtitle === false) {
                $faqsubtitle = get_string('faq_subtitle', 'theme_learnovatheme');
            }
            // Uploaded image, falling back to the mockup default when unset.
            $faqimage = $PAGE->theme->setting_file_url('faq_image', 'faq_image');
            if (empty($faqimage)) {
                $faqimage = 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=900&q=80';
            }

            $faqcontext = [
                'image'    => $faqimage,
                'eyebrow'  => $faqeyebrow,
                'title'    => $faqtitle,
                'subtitle' => $faqsubtitle,
                'items'    => $faqitems,
            ];
            $templatecontext['hasfaq']    = true;
            $templatecontext['faqcontent'] = $OUTPUT->render_from_template(
                'theme_learnovatheme/faq',
                $faqcontext
            );
        }
    }

    // ── Call to action (CTA) ───────────────────────────────────────────
    // Closing banner near the bottom of the front page. Heading + description
    // editable in Home Setting; buttons are fixed. Button 1 swaps on auth
    // state (sign-in modal vs dashboard link).
    $ctaenabled = get_config('theme_learnovatheme', 'cta_enabled');
    if ($ctaenabled === false) {
        $ctaenabled = 1; // Default on.
    }

    if ($ctaenabled) {
        $ctatitle = get_config('theme_learnovatheme', 'cta_title');
        if ($ctatitle === false || $ctatitle === '') {
            $ctatitle = get_string('cta_title', 'theme_learnovatheme');
        }
        $ctadescription = get_config('theme_learnovatheme', 'cta_description');
        if ($ctadescription === false || $ctadescription === '') {
            $ctadescription = get_string('cta_description', 'theme_learnovatheme');
        }

        $ctacontext = [
            'title'          => $ctatitle,
            'description'    => $ctadescription,
            'isguest'        => (!isloggedin() || isguestuser()),
            'btn1_signin'    => get_string('cta_btn_signin', 'theme_learnovatheme'),
            'btn1_dashboard' => get_string('cta_btn_dashboard', 'theme_learnovatheme'),
            'btn2_label'     => get_string('cta_btn_browse', 'theme_learnovatheme'),
            'login_url'      => (new moodle_url('/login/index.php'))->out(false),
            'dashboard_url'  => (new moodle_url('/my/'))->out(false),
            'courses_url'    => (new moodle_url('/course/index.php'))->out(false),
        ];
        $templatecontext['hascta']    = true;
        $templatecontext['ctacontent'] = $OUTPUT->render_from_template(
            'theme_learnovatheme/cta',
            $ctacontext
        );
    }
}

// ── Course Catalogue ───────────────────────────────────────────────────────
// Real courses + categories, enriched with local_learnova_course meta. Rendered
// full-width above the (hidden) default course listing. See classes/catalogue.php.
if ($iscatalogue) {
    $templatecontext['hascatalogue']    = true;
    $templatecontext['cataloguecontent'] = $OUTPUT->render_from_template(
        'theme_learnovatheme/catalogue',
        \theme_learnovatheme\catalogue::context()
    );
}

// ── Course Landing ───────────────────────────────────────────────────────────
// Custom course/view.php landing, rendered full-width above the (hidden) default
// course view. Real data where available, dummy otherwise. See course_landing.php.
if ($iscourselanding) {
    $templatecontext['hascourselanding']    = true;
    $templatecontext['courselandingcontent'] = $OUTPUT->render_from_template(
        'theme_learnovatheme/course_landing',
        \theme_learnovatheme\course_landing::context()
    );
}

echo $OUTPUT->render_from_template('theme_boost/drawers', $templatecontext);
