<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting: a dynamic, ordered list of curated courses for the front-page
 * "Latest Courses" section (Admin Curated data-source mode).
 *
 * The value is stored as a JSON array of {courseid} objects, capped at
 * {@see catalogue::SECTION_SLOTS}. On screen the AMD module
 * theme_learnovatheme/course_settings progressively enhances the backing
 * textarea into an add / remove / reorder form; each row is a course <select>.
 * With JavaScript disabled the raw JSON textarea remains editable.
 */
class admin_setting_courseitems extends \admin_setting_configtextarea {

    /**
     * Renders the widget: the standard admin-setting frame wrapping our enhanced
     * control. Loads the AMD enhancer only when the control is shown.
     *
     * @param string $data Current stored value (JSON).
     * @param string $query Search query (for highlighting).
     * @return string HTML.
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('theme_learnovatheme/course_settings', 'init');

        $max = catalogue::SECTION_SLOTS;
        $context = [
            'name'        => $this->get_full_name(),
            'id'          => $this->get_id(),
            'value'       => (string) $data,
            'max'         => $max,
            'coursesjson' => json_encode(catalogue::course_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'addlabel'    => get_string('courses_add_label', 'theme_learnovatheme'),
            'removelabel' => get_string('courses_remove_label', 'theme_learnovatheme'),
            'uplabel'     => get_string('courses_move_up', 'theme_learnovatheme'),
            'downlabel'   => get_string('courses_move_down', 'theme_learnovatheme'),
            'courselabel' => get_string('settings_courses_slot_course', 'theme_learnovatheme'),
            'maxnote'     => get_string('courses_max_note', 'theme_learnovatheme', $max),
        ];

        $element = $OUTPUT->render_from_template('theme_learnovatheme/course_setting', $context);

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
     * Sanitises and stores the submitted JSON: caps to SECTION_SLOTS items,
     * strips rows with no course, and deduplicates course ids.
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
        $seen  = [];
        $max   = catalogue::SECTION_SLOTS;
        foreach ($decoded as $entry) {
            if (count($clean) >= $max) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $courseid = (int) ($entry['courseid'] ?? 0);
            if ($courseid <= 0 || isset($seen[$courseid])) {
                continue;
            }
            $seen[$courseid] = true;
            $clean[] = ['courseid' => $courseid];
        }

        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->config_write($this->name, $json) ? '' : get_string('errorsetting', 'admin');
    }
}
