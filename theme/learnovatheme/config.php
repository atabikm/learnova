<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name    = 'learnovatheme';
$THEME->parents = ['boost'];                  // child of boost — inherit everything

$THEME->sheets        = [];
$THEME->editor_sheets = [];
$THEME->editor_scss   = ['editor'];
$THEME->usefallback   = true;                 // fallback to boost for missing templates

// SCSS callback: inherit Boost's SCSS and append our custom styles.
// Using call_user_func on parent theme's scss callback avoids function-not-found
// issues when the theme system hasn't loaded boost's lib.php yet.
$THEME->scss = function ($theme) {
    global $CFG;
    // Get parent (boost) SCSS
    $boosttheme = theme_config::load('boost');
    $boostscss = $boosttheme->scss;
    $scss = is_callable($boostscss) ? call_user_func($boostscss, $boosttheme) : '';
    // Append our custom SCSS. post.scss = page/feature styles;
    // _management_ui.scss = the reusable lm-* component library;
    // _site_admin.scss = the custom Site Administration UI.
    // All three are read as strings and concatenated onto Boost's SCSS, so
    // @import is never used — order between them is irrelevant (custom
    // properties resolve at runtime and @extend resolves within its own file).
    // Brand variables are NOT set here; they live in scss/pre.scss, which the
    // prescsscallback below injects before Bootstrap compiles.
    $scssdir = $CFG->dirroot . '/theme/learnovatheme/scss/';
    foreach (['post.scss', '_management_ui.scss', '_site_admin.scss'] as $file) {
        if (file_exists($scssdir . $file)) {
            $scss .= "\n" . file_get_contents($scssdir . $file);
        }
    }
    return $scss;
};

// Layout map — copied from Boost (a child theme that defines $THEME->layouts
// replaces the parent's map entirely, so every layout must be listed). The only
// Learnova change: activity pages ('incourse') use our focus-mode layout, which
// itself falls back to drawers.php when focus mode does not apply (edit mode,
// non-view pages). See layout/focusmode.php.
$THEME->layouts = [
    'base' => [
        'file' => 'drawers.php',
        'regions' => [],
    ],
    'standard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'course' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['langmenu' => true],
    ],
    'coursecategory' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    // Part of course, typical for modules — Learnova focus mode.
    'incourse' => [
        'file' => 'focusmode.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true],
    ],
    'admin' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mycourses' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true],
    ],
    'mydashboard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true, 'langmenu' => true],
    ],
    'mypublic' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
    'popup' => [
        'file' => 'columns1.php',
        'regions' => [],
        'options' => [
            'nofooter' => true,
            'nonavbar' => true,
            'activityheader' => [
                'notitle' => true,
                'nocompletion' => true,
                'nodescription' => true,
            ],
        ],
    ],
    'frametop' => [
        'file' => 'columns1.php',
        'regions' => [],
        'options' => [
            'nofooter' => true,
            'nocoursefooter' => true,
            'activityheader' => [
                'nocompletion' => true,
            ],
        ],
    ],
    'embedded' => [
        'file' => 'embedded.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'maintenance' => [
        'file' => 'maintenance.php',
        'regions' => [],
    ],
    'print' => [
        'file' => 'columns1.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => false, 'noactivityheader' => true],
    ],
    'redirect' => [
        'file' => 'embedded.php',
        'regions' => [],
    ],
    'report' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'secure' => [
        'file' => 'secure.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => [
            'activityheader' => [
                'notitle' => false,
            ],
        ],
    ],
];

$THEME->enable_dock            = false;
$THEME->extrascsscallback      = 'theme_learnovatheme_get_extra_scss';
$THEME->prescsscallback        = 'theme_learnovatheme_get_pre_scss';
$THEME->precompiledcsscallback = 'theme_learnovatheme_get_precompiled_css';
$THEME->yuicssmodules          = [];
$THEME->rendererfactory        = 'theme_overridden_renderer_factory';
$THEME->requiredblocks         = '';
$THEME->addblockposition       = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem             = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch          = true;
$THEME->usescourseindex        = true;
$THEME->activityheaderconfig   = ['notitle' => true];
