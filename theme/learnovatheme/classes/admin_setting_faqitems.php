<?php
namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting: a dynamic, repeatable list of FAQ question + answer pairs.
 *
 * The value is stored as a JSON array of {q, a} objects. On screen the AMD
 * module theme_learnovatheme/faq_settings progressively enhances the backing
 * textarea into an add / remove / reorder form, capped at {@see faq::MAX}
 * items. With JavaScript disabled the raw JSON textarea remains editable, so
 * the setting always persists.
 */
class admin_setting_faqitems extends \admin_setting_configtextarea {

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

        $PAGE->requires->js_call_amd('theme_learnovatheme/faq_settings', 'init');

        $context = [
            'name'       => $this->get_full_name(),
            'id'         => $this->get_id(),
            'value'      => (string) $data,
            'max'        => faq::MAX,
            'addlabel'   => get_string('faq_add_label', 'theme_learnovatheme'),
            'removelabel' => get_string('faq_remove_label', 'theme_learnovatheme'),
            'uplabel'    => get_string('faq_move_up', 'theme_learnovatheme'),
            'downlabel'  => get_string('faq_move_down', 'theme_learnovatheme'),
            'questionph' => get_string('faq_question_ph', 'theme_learnovatheme'),
            'answerph'   => get_string('faq_answer_ph', 'theme_learnovatheme'),
            'maxnote'    => get_string('faq_max_note', 'theme_learnovatheme', faq::MAX),
        ];
        $element = $OUTPUT->render_from_template('theme_learnovatheme/faq_setting', $context);

        // No "Default:" hint — a raw JSON blob is meaningless to admins, and the
        // dynamic list UI is self-explanatory.
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
     * Sanitises and stores the submitted JSON: caps to MAX items, strips rows
     * with an empty question, and cleans each field as plain text.
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
        foreach ($decoded as $entry) {
            if (count($clean) >= faq::MAX) {
                break;
            }
            if (!is_array($entry)) {
                continue;
            }
            $q = isset($entry['q']) ? trim(clean_param((string) $entry['q'], PARAM_TEXT)) : '';
            if ($q === '') {
                continue;
            }
            $a = isset($entry['a']) ? clean_param((string) $entry['a'], PARAM_TEXT) : '';
            $clean[] = ['q' => $q, 'a' => $a];
        }

        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->config_write($this->name, $json) ? '' : get_string('errorsetting', 'admin');
    }
}
