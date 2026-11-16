<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting: a dynamic, repeatable list of category + icon pairs.
 *
 * The value is stored as a JSON array of {catid, icon} objects. On screen the
 * AMD module theme_learnovatheme/cat_settings progressively enhances the backing
 * textarea into an add / remove / reorder form, capped at {@see categories::SLOTS}
 * items. With JavaScript disabled the raw JSON textarea remains editable.
 */
class admin_setting_catitems extends \admin_setting_configtextarea {

    /**
     * Renders the widget: the standard admin-setting frame wrapping our
     * enhanced control. Loads the AMD enhancer only when the control is shown.
     *
     * @param string $data Current stored value (JSON).
     * @param string $query Search query (for highlighting).
     * @return string HTML.
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('theme_learnovatheme/cat_settings', 'init');

        // Category options: array of {id, name} for the select in each row.
        $catoptions = [];
        foreach (\core_course_category::make_categories_list() as $id => $name) {
            $catoptions[] = ['id' => (int) $id, 'name' => (string) $name];
        }

        // Icon options: array of {key, label, section, sectionlabel, svg} for
        // the modal grid picker (inline SVG so the real icon is shown).
        $iconitems = icons::picker_items();

        $max = categories::max();
        $context = [
            'name'           => $this->get_full_name(),
            'id'             => $this->get_id(),
            'value'          => (string) $data,
            'max'            => $max,
            'minlimit'       => categories::MIN_LIMIT,
            'maxlimit'       => categories::MAX_LIMIT,
            'categoriesjson' => json_encode($catoptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'iconsjson'      => json_encode($iconitems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'addlabel'       => get_string('cats_add_label', 'theme_learnovatheme'),
            'removelabel'    => get_string('cats_remove_label', 'theme_learnovatheme'),
            'uplabel'        => get_string('cats_move_up', 'theme_learnovatheme'),
            'downlabel'      => get_string('cats_move_down', 'theme_learnovatheme'),
            'catlabel'       => get_string('settings_cats_slot_cat', 'theme_learnovatheme'),
            'iconlabel'      => get_string('settings_cats_slot_icon', 'theme_learnovatheme'),
            'pickerlabel'    => get_string('cats_icon_pick', 'theme_learnovatheme'),
            'searchph'       => get_string('cats_icon_search', 'theme_learnovatheme'),
            'colorlabel'     => get_string('cats_color', 'theme_learnovatheme'),
            'colorhint'      => get_string('cats_color_hint', 'theme_learnovatheme'),
            'colorreset'     => get_string('cats_color_reset', 'theme_learnovatheme'),
            'maxnote'        => get_string('cats_max_note', 'theme_learnovatheme', $max),
        ];

        $element = $OUTPUT->render_from_template('theme_learnovatheme/cat_setting', $context);

        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            true,
            '',
            null,
            $query
        );
    }

    /**
     * Sanitises and stores the submitted JSON: caps to SLOTS items, strips rows
     * with no category, deduplicates cat IDs, and validates icon keys.
     *
     * @param string $data Submitted JSON.
     * @return string Empty on success, error string otherwise.
     */
    public function write_setting($data) {
        $decoded = json_decode((string) $data, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $clean = [];
        $seencats = [];
        $max = categories::max();
        foreach ($decoded as $entry) {
            if (count($clean) >= $max) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $catid = (int) ($entry['catid'] ?? 0);
            if ($catid <= 0 || isset($seencats[$catid])) {
                continue;
            }
            $icon = isset($entry['icon']) ? clean_param((string) $entry['icon'], PARAM_ALPHANUMEXT) : '';
            $icon = icons::resolve($icon);
            if ($icon === '') {
                $icon = icons::default_key();
            }
            // Optional per-card colour (hex). Stored only when valid; an empty
            // value means "use the cycling default".
            $color = categories::normalise_color((string) ($entry['color'] ?? ''));
            $seencats[$catid] = true;
            $row = ['catid' => $catid, 'icon' => $icon];
            if ($color !== '') {
                $row['color'] = $color;
            }
            $clean[] = $row;
        }

        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->config_write($this->name, $json) ? '' : get_string('errorsetting', 'admin');
    }
}
