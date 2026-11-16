<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for the Site administration outline (/admin/search.php).
 *
 * The outline itself is a Mustache override of core/settings_link_page; this
 * class supplies the one piece of data that override cannot reach, because
 * admin/search.php renders that template with a fixed context (the settings
 * nav node) we do not control: the current user's admin bookmarks.
 */
class site_admin {

    /**
     * The current user's admin bookmarks, resolved to label + link.
     *
     * Reproduces the resolution in block_admin_bookmarks: the bookmarks live in
     * the 'admin_bookmarks' user preference as a comma-separated list of admin
     * tree keys, each located in the admin root and turned into the same URL
     * the block would use (settings page, external page, or category). Keys that
     * no longer resolve — a plugin was removed, a page renamed — are skipped, so
     * a stale preference never produces a dead chip.
     *
     * @return array[] List of ['label' => string, 'url' => string], in the
     *                 user's saved order. Empty when there are no bookmarks.
     */
    public static function bookmarks(): array {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $pref = get_user_preferences('admin_bookmarks', '');
        if ($pref === '') {
            return [];
        }

        // Pages only (settings not required) — matches the block, and is enough
        // to read visiblename + URL without building every setting.
        $root = admin_get_root(false, false);
        $out = [];

        foreach (explode(',', $pref) as $key) {
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $node = $root->locate($key);
            if ($node instanceof \admin_settingpage) {
                $url = new \moodle_url('/admin/settings.php', ['section' => $key]);
            } else if ($node instanceof \admin_externalpage) {
                $url = new \moodle_url($node->url);
            } else if ($node instanceof \admin_category) {
                $url = new \moodle_url('/admin/category.php', ['category' => $key]);
            } else {
                continue;
            }

            $out[] = [
                'label' => (string) $node->visiblename,
                'url'   => $url->out(false),
            ];
        }

        return $out;
    }
}
