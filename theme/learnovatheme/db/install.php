<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook: seed the FAQ section defaults so the front page and the
 * Home Settings form are populated out of the box.
 */
function xmldb_theme_learnovatheme_install() {
    global $CFG;

    // The plugin's lib.php is not loaded automatically during installation.
    require_once($CFG->dirroot . '/theme/learnovatheme/lib.php');

    theme_learnovatheme_apply_faq_defaults();
}
