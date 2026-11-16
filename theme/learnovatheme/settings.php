<?php
// Learnova theme settings — exposed at Site administration → Appearance →
// Themes → Learnova (URL: /admin/settings.php?section=themesettinglearnovatheme).
//
// This is the "Home Setting" surface linked from the profile dropdown.
// Editable defaults live here; templates fall back to language strings if
// the admin has never opened the form.

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage(
        'themesettinglearnovatheme',
        get_string('configtitle', 'theme_learnovatheme')
    );

    // ── Hero ───────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_hero',
        get_string('settings_hero_heading', 'theme_learnovatheme'),
        get_string('settings_hero_heading_desc', 'theme_learnovatheme')
    ));

    // Hero eyebrow — short uppercase label above the title. Plain text.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/hero_eyebrow',
        get_string('settings_hero_eyebrow', 'theme_learnovatheme'),
        get_string('settings_hero_eyebrow_desc', 'theme_learnovatheme'),
        get_string('hero_eyebrow', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Hero title — full headline, edited via the WYSIWYG editor.
    // Italic (<em>/<i>) renders amber as the accent; <br> for line break.
    // Default copy lives in the language file so it's localizable.
    $settings->add(new admin_setting_confightmleditor(
        'theme_learnovatheme/hero_title',
        get_string('settings_hero_title', 'theme_learnovatheme'),
        get_string('settings_hero_title_desc', 'theme_learnovatheme'),
        get_string('hero_title_default', 'theme_learnovatheme'),
        PARAM_RAW,
        60,
        4
    ));

    // Hero description — plain text only. All styling comes from CSS, so this
    // is a plain textarea (no editor); PARAM_TEXT strips any markup.
    $settings->add(new admin_setting_configtextarea(
        'theme_learnovatheme/hero_description',
        get_string('settings_hero_description', 'theme_learnovatheme'),
        get_string('settings_hero_description_desc', 'theme_learnovatheme'),
        get_string('hero_description', 'theme_learnovatheme'),
        PARAM_TEXT,
        60,
        3
    ));

    // Hero background image. Sits faintly behind the hero; falls back to a
    // bundled default when empty.
    $settings->add(new admin_setting_configstoredfile(
        'theme_learnovatheme/hero_image',
        get_string('settings_hero_image', 'theme_learnovatheme'),
        get_string('settings_hero_image_desc', 'theme_learnovatheme'),
        'hero_image',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['web_image']]
    ));

    // ── Hero stats ───────────────────────────────────────────────────────────
    // Admin picks which 4 of the 6 available stat chips appear, and in which
    // order. Each chip shows a live auto value unless overridden below.
    // $settings->add(new admin_setting_heading(
    //     'theme_learnovatheme/heading_hero_stats',
    //     get_string('settings_hero_stats_heading', 'theme_learnovatheme'),
    //     get_string('settings_hero_stats_heading_desc', 'theme_learnovatheme')
    // ));

    $statoptions  = \theme_learnovatheme\hero_stats::menu_options();
    $statdefaults = ['courses', 'learners', 'satisfaction', 'instructors'];
    for ($i = 1; $i <= 4; $i++) {
        $settings->add(new admin_setting_configselect(
            "theme_learnovatheme/hero_stat_$i",
            get_string('settings_hero_stat_slot', 'theme_learnovatheme', $i),
            '',
            $statdefaults[$i - 1],
            $statoptions
        ));
    }

    // ── Hero stats — manual overrides ──────────────────────────────────────
    // One free-text field per stat. Blank = use live data; a value (e.g.
    // "200+", "12K", "98%") bypasses the auto computation.
    // $settings->add(new admin_setting_heading(
    //     'theme_learnovatheme/heading_hero_stats_manual',
    //     get_string('settings_hero_stats_manual_heading', 'theme_learnovatheme'),
    //     get_string('settings_hero_stats_manual_heading_desc', 'theme_learnovatheme')
    // ));

    foreach (\theme_learnovatheme\hero_stats::definitions() as $statkey => $statdef) {
        $settings->add(new admin_setting_configtext(
            "theme_learnovatheme/hero_stat_{$statkey}_manual",
            $statdef['label'],
            get_string('settings_hero_stat_manual_desc', 'theme_learnovatheme'),
            '',
            PARAM_TEXT
        ));
    }

    // ── Hero stats (logged-in) ─────────────────────────────────────────────
    // Same 4-slot picker as above, but the chips describe the learner rather
    // than the site. Chips whose feature does not exist yet (learning hours)
    // show an em-dash until a manual override is filled in.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_hero_userstats',
        get_string('settings_hero_userstats_heading', 'theme_learnovatheme'),
        get_string('settings_hero_userstats_heading_desc', 'theme_learnovatheme')
    ));

    $useroptions = \theme_learnovatheme\user_stats::menu_options();
    for ($i = 1; $i <= 4; $i++) {
        $settings->add(new admin_setting_configselect(
            "theme_learnovatheme/hero_userstat_$i",
            get_string('settings_hero_userstat_slot', 'theme_learnovatheme', $i),
            '',
            \theme_learnovatheme\user_stats::SLOT_DEFAULTS[$i - 1],
            $useroptions
        ));
    }

    // Manual overrides — blank = live data.
    foreach (\theme_learnovatheme\user_stats::definitions() as $statkey => $statdef) {
        $settings->add(new admin_setting_configtext(
            "theme_learnovatheme/hero_userstat_{$statkey}_manual",
            $statdef['label'],
            get_string('settings_hero_stat_manual_desc', 'theme_learnovatheme'),
            '',
            PARAM_TEXT
        ));
    }

    // ── Hero motivational messages (logged-in) ─────────────────────────────
    // One editable message per learner-state condition. Conditions are checked
    // in priority order (see the heading description); the first match shows.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_heromsg',
        get_string('settings_heromsg_heading', 'theme_learnovatheme'),
        get_string('settings_heromsg_heading_desc', 'theme_learnovatheme')
    ));

    foreach (\theme_learnovatheme\hero_message::keys() as $msgkey) {
        $settings->add(new admin_setting_configtext(
            "theme_learnovatheme/heromsg_$msgkey",
            get_string("settings_heromsg_$msgkey", 'theme_learnovatheme'),
            '',
            get_string("heromsg_{$msgkey}_default", 'theme_learnovatheme'),
            PARAM_TEXT
        ));
    }

    // ── Announcement band ──────────────────────────────────────────────────
    // Site-wide notice shown below the hero. Toggle on/off + editable parts.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_announce',
        get_string('settings_announce_heading', 'theme_learnovatheme'),
        get_string('settings_announce_heading_desc', 'theme_learnovatheme')
    ));

    // Show/hide toggle.
    $settings->add(new admin_setting_configcheckbox(
        'theme_learnovatheme/announce_enabled',
        get_string('settings_announce_enabled', 'theme_learnovatheme'),
        get_string('settings_announce_enabled_desc', 'theme_learnovatheme'),
        1
    ));

    // Tag — small uppercase label (e.g. "Info Program").
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/announce_tag',
        get_string('settings_announce_tag', 'theme_learnovatheme'),
        get_string('settings_announce_tag_desc', 'theme_learnovatheme'),
        get_string('announce_tag', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Description.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/announce_message',
        get_string('settings_announce_message', 'theme_learnovatheme'),
        get_string('settings_announce_message_desc', 'theme_learnovatheme'),
        get_string('announce_message', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // CTA label — leave blank to hide the link.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/announce_cta_label',
        get_string('settings_announce_cta_label', 'theme_learnovatheme'),
        get_string('settings_announce_cta_label_desc', 'theme_learnovatheme'),
        get_string('announce_cta', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // CTA URL.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/announce_cta_url',
        get_string('settings_announce_cta_url', 'theme_learnovatheme'),
        get_string('settings_announce_cta_url_desc', 'theme_learnovatheme'),
        '',
        PARAM_URL
    ));

    // ── Browse by Category ─────────────────────────────────────────────
    // Front-page section built from native course categories. Admin toggles
    // the section, edits the heading copy, then fills the slots below: each
    // slot picks a category (slot order = display order) and an icon from the
    // bundled library. Course counts come live from Moodle.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_cats',
        get_string('settings_cats_heading', 'theme_learnovatheme'),
        get_string('settings_cats_heading_desc', 'theme_learnovatheme')
    ));

    // Show/hide toggle.
    $settings->add(new admin_setting_configcheckbox(
        'theme_learnovatheme/cats_enabled',
        get_string('settings_cats_enabled', 'theme_learnovatheme'),
        get_string('settings_cats_enabled_desc', 'theme_learnovatheme'),
        1
    ));

    // Title.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/cats_title',
        get_string('settings_cats_title', 'theme_learnovatheme'),
        '',
        get_string('cats_title', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Subtitle.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/cats_subtitle',
        get_string('settings_cats_subtitle', 'theme_learnovatheme'),
        '',
        get_string('cats_subtitle', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // "View all" link label — leave blank to hide the link. The link target is
    // always the course catalogue index (not configurable).
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/cats_viewall_label',
        get_string('settings_cats_viewall_label', 'theme_learnovatheme'),
        get_string('settings_cats_viewall_label_desc', 'theme_learnovatheme'),
        get_string('cats_viewall', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Maximum number of category cards the admin may add (and that the front
    // page renders). Dropdown of MIN_LIMIT..MAX_LIMIT; default SLOTS.
    $settings->add(new admin_setting_configselect(
        'theme_learnovatheme/cats_max',
        get_string('settings_cats_max', 'theme_learnovatheme'),
        get_string('settings_cats_max_desc', 'theme_learnovatheme', (object) [
            'min' => \theme_learnovatheme\categories::MIN_LIMIT,
            'max' => \theme_learnovatheme\categories::MAX_LIMIT,
        ]),
        \theme_learnovatheme\categories::SLOTS,
        \theme_learnovatheme\categories::max_options()
    ));

    // Dynamic category list — admin adds/removes/reorders up to cats_max
    // entries, each picking a native category and an icon. Stored as JSON.
    $settings->add(new \theme_learnovatheme\admin_setting_catitems(
        'theme_learnovatheme/cats_items',
        get_string('settings_cats_items', 'theme_learnovatheme'),
        get_string('settings_cats_items_desc', 'theme_learnovatheme'),
        ''
    ));

    // ── Latest Courses ──────────────────────────────────────────────────
    // Front-page section: a 3-card row of courses. The data source has two
    // modes the admin chooses below. Admin Curated (default): pick exactly the
    // courses to show, in order, from the list further down. Automatic: the 3
    // most recently created visible courses. Cards reuse the catalogue card
    // builder, so fields without data (cover, duration, modules) are omitted.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_courses',
        get_string('settings_courses_heading', 'theme_learnovatheme'),
        get_string('settings_courses_heading_desc', 'theme_learnovatheme')
    ));

    // Show/hide toggle.
    $settings->add(new admin_setting_configcheckbox(
        'theme_learnovatheme/courses_enabled',
        get_string('settings_courses_enabled', 'theme_learnovatheme'),
        get_string('settings_courses_enabled_desc', 'theme_learnovatheme'),
        1
    ));

    // Title.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/courses_title',
        get_string('settings_courses_title', 'theme_learnovatheme'),
        '',
        get_string('courses_title', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Subtitle.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/courses_subtitle',
        get_string('settings_courses_subtitle', 'theme_learnovatheme'),
        '',
        get_string('courses_subtitle', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // "View all" link label — leave blank to hide the link. The link target is
    // always the course catalogue index (not configurable).
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/courses_viewall_label',
        get_string('settings_courses_viewall_label', 'theme_learnovatheme'),
        get_string('settings_courses_viewall_label_desc', 'theme_learnovatheme'),
        get_string('courses_viewall', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Data source mode: curated (admin picks the courses) or automatic (newest).
    $settings->add(new admin_setting_configselect(
        'theme_learnovatheme/courses_mode',
        get_string('settings_courses_mode', 'theme_learnovatheme'),
        get_string('settings_courses_mode_desc', 'theme_learnovatheme'),
        'curated',
        [
            'curated'   => get_string('settings_courses_mode_curated', 'theme_learnovatheme'),
            'automatic' => get_string('settings_courses_mode_automatic', 'theme_learnovatheme'),
        ]
    ));

    // Curated course list — admin adds/removes/reorders up to SECTION_SLOTS
    // courses. Only used in Curated mode. Stored as JSON [{courseid}].
    $settings->add(new \theme_learnovatheme\admin_setting_courseitems(
        'theme_learnovatheme/courses_items',
        get_string('settings_courses_items', 'theme_learnovatheme'),
        get_string('settings_courses_items_desc', 'theme_learnovatheme', (object) [
            'max' => \theme_learnovatheme\catalogue::SECTION_SLOTS,
        ]),
        ''
    ));

    // ── Common Questions (FAQ) ─────────────────────────────────────────
    // Front-page section: a left image card + a right accordion of Q&A
    // pairs. Admin toggles the section, edits the image and heading copy,
    // then fills the slots below. Each slot is one question + answer (slot
    // order = display order). Empty questions are skipped, so items are
    // added / removed / reordered by filling or clearing slots. Max 10.
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_faq',
        get_string('settings_faq_heading', 'theme_learnovatheme'),
        get_string('settings_faq_heading_desc', 'theme_learnovatheme')
    ));

    // Show/hide toggle.
    $settings->add(new admin_setting_configcheckbox(
        'theme_learnovatheme/faq_enabled',
        get_string('settings_faq_enabled', 'theme_learnovatheme'),
        get_string('settings_faq_enabled_desc', 'theme_learnovatheme'),
        1
    ));

    // Left card image. Falls back to a bundled default when empty.
    $settings->add(new admin_setting_configstoredfile(
        'theme_learnovatheme/faq_image',
        get_string('settings_faq_image', 'theme_learnovatheme'),
        get_string('settings_faq_image_desc', 'theme_learnovatheme'),
        'faq_image',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['web_image']]
    ));

    // Eyebrow — small uppercase label over the title.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/faq_eyebrow',
        get_string('settings_faq_eyebrow', 'theme_learnovatheme'),
        '',
        get_string('faq_eyebrow', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Title.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/faq_title',
        get_string('settings_faq_title', 'theme_learnovatheme'),
        '',
        get_string('faq_title', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Subtitle.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/faq_subtitle',
        get_string('settings_faq_subtitle', 'theme_learnovatheme'),
        '',
        get_string('faq_subtitle', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Q&A list — dynamic add / remove / reorder control, capped at faq::MAX.
    // Stored as JSON; a blank question is dropped on save.
    $settings->add(new \theme_learnovatheme\admin_setting_faqitems(
        'theme_learnovatheme/faq_items',
        get_string('settings_faq_items', 'theme_learnovatheme'),
        get_string('settings_faq_items_desc', 'theme_learnovatheme'),
        \theme_learnovatheme\faq::default_items_json()
    ));

    // ── Call to action (CTA) ───────────────────────────────────────────
    // Closing banner near the bottom of the front page. Admin toggles the
    // section and edits the heading + description; the two buttons are fixed
    // (sign-in / dashboard, and browse catalogue).
    $settings->add(new admin_setting_heading(
        'theme_learnovatheme/heading_cta',
        get_string('settings_cta_heading', 'theme_learnovatheme'),
        get_string('settings_cta_heading_desc', 'theme_learnovatheme')
    ));

    // Show/hide toggle.
    $settings->add(new admin_setting_configcheckbox(
        'theme_learnovatheme/cta_enabled',
        get_string('settings_cta_enabled', 'theme_learnovatheme'),
        get_string('settings_cta_enabled_desc', 'theme_learnovatheme'),
        1
    ));

    // Heading.
    $settings->add(new admin_setting_configtext(
        'theme_learnovatheme/cta_title',
        get_string('settings_cta_title', 'theme_learnovatheme'),
        '',
        get_string('cta_title', 'theme_learnovatheme'),
        PARAM_TEXT
    ));

    // Description — plain text (styling is CSS-only).
    $settings->add(new admin_setting_configtextarea(
        'theme_learnovatheme/cta_description',
        get_string('settings_cta_description', 'theme_learnovatheme'),
        '',
        get_string('cta_description', 'theme_learnovatheme'),
        PARAM_TEXT,
        60,
        3
    ));
}
