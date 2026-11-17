<?php
// Learnova login layout — split screen: brand/hero panel on the left, the
// page's main content (core/loginform, forgot-password form, signup, …) on the
// right. Used by every page with pagelayout 'login', so anything specific to
// the sign-in form itself lives in templates/core/loginform.mustache, not here.

defined('MOODLE_INTERNAL') || die();

use theme_learnovatheme\hero_stats;

$bodyattributes = $OUTPUT->body_attributes(['learnova-login-page']);

// Background photo behind the teal overlay. Shares the front-page hero image
// (Home Setting) so admins upload one picture; same bundled default.
$bgimage = $PAGE->theme->setting_file_url('hero_image', 'hero_image');
if (empty($bgimage)) {
    $bgimage = 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=1600&q=80&auto=format&fit=crop';
}

// Stat strip: same resolver as the front-page hero chips, with mockup fallback
// so fresh/dev environments match the design (200+ / 12K / 98%).
$defaultstats = [
    'courses'      => '200+',
    'learners'     => '12K',
    'satisfaction' => '98%',
];
$stats = [];
foreach (['courses', 'learners', 'satisfaction'] as $key) {
    $stat = hero_stats::resolve($key);
    $num = ($stat !== null && $stat['num'] !== '—') ? $stat['num'] : $defaultstats[$key];
    $stats[] = [
        'num' => $num,
        'lbl' => get_string("login_stat_{$key}", 'theme_learnovatheme'),
    ];
}

// Quote avatar: Rizky Amalia photo.
$quoteavatar = 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?w=160&h=160&fit=crop&crop=face';

// Footer links. The guide link only renders when an admin has set its URL.
$privacyurl = !empty($CFG->sitepolicy)
    ? $CFG->sitepolicy
    : (new moodle_url('/admin/tool/dataprivacy/summary.php'))->out(false);
$guideurl = trim((string) get_config('theme_learnovatheme', 'login_guideurl'));

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'wwwroot'        => $CFG->wwwroot,
    'bgimage'        => $bgimage,
    'stats'          => $stats,
    'hasstats'       => !empty($stats),
    'quoteavatar'    => $quoteavatar,
    'supporturl'     => (new moodle_url('/user/contactsitesupport.php'))->out(false),
    'privacyurl'     => $privacyurl,
    'guideurl'       => $guideurl,
    'year'           => date('Y'),
];

echo $OUTPUT->render_from_template('theme_learnovatheme/login', $templatecontext);
