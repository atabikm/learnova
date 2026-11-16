<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Seeds the FAQ section defaults into config, but only for keys that have
 * never been set. This makes the defaults appear both on the homepage and
 * pre-filled in the Home Settings form out of the box, while preserving any
 * value an admin later edits or clears. Called from install + upgrade.
 */
function theme_learnovatheme_apply_faq_defaults() {
    $defaults = [
        'faq_enabled'  => 1,
        'faq_eyebrow'  => get_string('faq_eyebrow', 'theme_learnovatheme'),
        'faq_title'    => get_string('faq_title', 'theme_learnovatheme'),
        'faq_subtitle' => get_string('faq_subtitle', 'theme_learnovatheme'),
        'faq_items'    => \theme_learnovatheme\faq::default_items_json(),
    ];

    foreach ($defaults as $name => $value) {
        if (get_config('theme_learnovatheme', $name) === false) {
            set_config($name, $value, 'theme_learnovatheme');
        }
    }
}

/**
 * Page init — called on every page render.
 * Auto-loads Learnova AMD modules for interactivity only (no HTML injection).
 * HTML structure is handled by Mustache template overrides.
 */
function theme_learnovatheme_page_init(moodle_page $page) {
    // Brand fonts are self-hosted via @font-face in pre.scss; no external CSS needed.
    $page->requires->js_call_amd('theme_learnovatheme/login_modal', 'init');

    // Course Catalogue sidebar filter interactivity (collapse + visual toggles).
    if ($page->pagelayout === 'coursecategory') {
        $page->requires->js_call_amd('theme_learnovatheme/catalogue', 'init');
    }

    // Site administration outline: collapse/expand the cards, keep the sidebar
    // in step with the visible pane, and fill the bookmark bar. The body class
    // scopes the SCSS that hides core's secondary navigation, which the sidebar
    // replaces. Bookmarks are resolved here (server-side) because core renders
    // core/settings_link_page with a fixed context we cannot extend; the module
    // paints the pre-resolved list into the Mustache placeholder, no re-fetch.
    if ($page->pagetype === 'admin-search') {
        $page->add_body_class('learnova-siteadmin-page');
        $page->requires->js_call_amd('theme_learnovatheme/site_admin', 'init', [
            ['bookmarks' => \theme_learnovatheme\site_admin::bookmarks()],
        ]);
    }
}

/**
 * User preferences owned by the theme.
 *
 * Both are focus-mode chrome state. They are stored server-side (rather than in
 * localStorage) so the layout can stamp the body class before first paint —
 * restoring them from JS would flash the wrong sidebar/theme on every activity.
 *
 * @return array Preference definitions, keyed by preference name.
 */
function theme_learnovatheme_user_preferences(): array {
    return [
        'theme_learnovatheme_fm_sidebar' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'theme_learnovatheme_fm_dark' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Fragment: re-render the Course Landing "Course Content" tab in place.
 *
 * Called from course_landing.js after +Add section / +Subsection so the new
 * section/subsection appears without a full page reload. Returns the same markup
 * as the content tab of course_landing.mustache (module list + add-section
 * button), always in edit mode.
 *
 * @param array $args Fragment args; $args['context'] is the course context.
 * @return string Rendered HTML for the content tab.
 */
function theme_learnovatheme_output_fragment_course_content($args) {
    global $OUTPUT;

    $context = $args['context'];
    require_capability('moodle/course:manageactivities', $context);

    $data = \theme_learnovatheme\course_landing::content_context((int) $context->instanceid);
    return $OUTPUT->render_from_template('theme_learnovatheme/course_landing_content', $data);
}

/**
 * Pre-SCSS — brand variable overrides injected before Bootstrap compilation.
 *
 * scss/pre.scss is the single source of truth for brand colour, typography,
 * radius and border. Wired via $THEME->prescsscallback in config.php; the main
 * SCSS is assembled by the $THEME->scss closure there, not here.
 */
function theme_learnovatheme_get_pre_scss($theme) {
    global $CFG;
    $scss = theme_boost_get_pre_scss($theme);
    $prefile = $CFG->dirroot . '/theme/learnovatheme/scss/pre.scss';
    if (file_exists($prefile)) {
        $scss .= "\n" . file_get_contents($prefile);
    }
    return $scss;
}

/**
 * Extra SCSS appended after main compilation.
 */
function theme_learnovatheme_get_extra_scss($theme) {
    return theme_boost_get_extra_scss($theme);
}

/**
 * Precompiled CSS fallback when SCSS compilation fails.
 */
function theme_learnovatheme_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/boost/style/moodle.css');
}

/**
 * Serves theme-uploaded files (logos, background images).
 */
function theme_learnovatheme_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, ['logo', 'backgroundimage', 'loginbackgroundimage', 'faq_image', 'hero_image'])) {
        $theme = theme_config::load('learnovatheme');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
