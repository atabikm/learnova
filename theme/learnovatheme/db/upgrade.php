<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Theme upgrade steps.
 *
 * @param int $oldversion Version we are upgrading from.
 * @return bool
 */
function xmldb_theme_learnovatheme_upgrade($oldversion) {

    if ($oldversion < 2025010111) {
        // Seed FAQ section defaults into config (only keys never set).
        theme_learnovatheme_apply_faq_defaults();
        upgrade_plugin_savepoint(true, 2025010111, 'theme', 'learnovatheme');
    }

    return true;
}
