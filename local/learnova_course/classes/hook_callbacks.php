<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_learnova_course;

use core_course\hook\after_form_definition;
use core_course\hook\after_form_validation;
use core_course\hook\after_form_submission;
use core_course\hook\before_course_deleted;

/**
 * Hook callbacks that wire Learnova course fields into the core course form.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var string Name of the notification channels form group. */
    const CHANNELS_GROUP = 'notif_channels_grp';

    /** @var string[] Integer fields that must be non-negative. */
    const INT_FIELDS = [
        'learning_hours_minutes',
        'cert_validity_days',
        'deadline_days',
        'notif_reminder_days',
        'notif_repeat_days',
    ];

    /**
     * Add the Learnova fields to the course settings form.
     *
     * @param after_form_definition $hook
     */
    public static function extend_course_form(after_form_definition $hook): void {
        global $PAGE;

        $mform = $hook->mform;
        $course = $hook->formwrapper->get_course();
        $courseid = empty($course->id) ? 0 : (int) $course->id;
        $meta = meta_manager::get($courseid);

        // Enforce numeric-only typing on the number fields across all browsers.
        $PAGE->requires->js_call_amd('local_learnova_course/numeric_input', 'init');

        $mform->addElement('header', 'learnovacoursehdr', get_string('sectionheading', 'local_learnova_course'));
        $mform->setExpanded('learnovacoursehdr', false);

        // Learning Hours (minutes).
        $el = $mform->addElement('text', 'learning_hours_minutes',
            get_string('learning_hours_minutes', 'local_learnova_course'), ['size' => 6]);
        $el->updateAttributes(['inputmode' => 'numeric', 'data-learnova-numeric' => '1']);
        $mform->setType('learning_hours_minutes', PARAM_INT);
        $mform->setDefault('learning_hours_minutes', $meta->learning_hours_minutes);
        $mform->addHelpButton('learning_hours_minutes', 'learning_hours_minutes', 'local_learnova_course');

        // Course Level.
        $mform->addElement('select', 'course_level',
            get_string('course_level', 'local_learnova_course'), meta_manager::level_options());
        $mform->setDefault('course_level', $meta->course_level);

        // Learning Objectives (textarea, one objective per line).
        $mform->addElement('textarea', 'learning_objectives',
            get_string('learning_objectives', 'local_learnova_course'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('learning_objectives', PARAM_TEXT);
        $mform->setDefault('learning_objectives', $meta->learning_objectives);
        $mform->addHelpButton('learning_objectives', 'learning_objectives', 'local_learnova_course');

        // Certificate Enabled (toggle).
        $mform->addElement('advcheckbox', 'cert_enabled',
            get_string('cert_enabled', 'local_learnova_course'), get_string('yes'));
        $mform->setDefault('cert_enabled', $meta->cert_enabled);

        // Certificate Template.
        $mform->addElement('select', 'cert_template_id',
            get_string('cert_template_id', 'local_learnova_course'), meta_manager::cert_template_options());
        $mform->setDefault('cert_template_id', $meta->cert_template_id);
        $mform->disabledIf('cert_template_id', 'cert_enabled');

        // Certificate Issuer.
        $mform->addElement('text', 'cert_issuer',
            get_string('cert_issuer', 'local_learnova_course'), ['size' => 40]);
        $mform->setType('cert_issuer', PARAM_TEXT);
        $mform->setDefault('cert_issuer', $meta->cert_issuer);
        $mform->disabledIf('cert_issuer', 'cert_enabled');

        // Certificate Expiry (days).
        $el = $mform->addElement('text', 'cert_validity_days',
            get_string('cert_validity_days', 'local_learnova_course'), ['size' => 6]);
        $el->updateAttributes(['inputmode' => 'numeric', 'data-learnova-numeric' => '1']);
        $mform->setType('cert_validity_days', PARAM_INT);
        $mform->setDefault('cert_validity_days', $meta->cert_validity_days);
        $mform->addHelpButton('cert_validity_days', 'cert_validity_days', 'local_learnova_course');
        $mform->disabledIf('cert_validity_days', 'cert_enabled');

        // Deadline Type.
        $mform->addElement('select', 'deadline_type',
            get_string('deadline_type', 'local_learnova_course'), meta_manager::deadline_options());
        $mform->setDefault('deadline_type', $meta->deadline_type);

        // Deadline Duration (days) - only relevant for enrollment-based deadlines.
        $el = $mform->addElement('text', 'deadline_days',
            get_string('deadline_days', 'local_learnova_course'), ['size' => 6]);
        $el->updateAttributes(['inputmode' => 'numeric', 'data-learnova-numeric' => '1']);
        $mform->setType('deadline_days', PARAM_INT);
        $mform->setDefault('deadline_days', $meta->deadline_days);
        $mform->hideIf('deadline_days', 'deadline_type', 'neq', 'enrollment');

        // Notification Reminder (days).
        $el = $mform->addElement('text', 'notif_reminder_days',
            get_string('notif_reminder_days', 'local_learnova_course'), ['size' => 6]);
        $el->updateAttributes(['inputmode' => 'numeric', 'data-learnova-numeric' => '1']);
        $mform->setType('notif_reminder_days', PARAM_INT);
        $mform->setDefault('notif_reminder_days', $meta->notif_reminder_days);

        // Notification Repeat (days).
        $el = $mform->addElement('text', 'notif_repeat_days',
            get_string('notif_repeat_days', 'local_learnova_course'), ['size' => 6]);
        $el->updateAttributes(['inputmode' => 'numeric', 'data-learnova-numeric' => '1']);
        $mform->setType('notif_repeat_days', PARAM_INT);
        $mform->setDefault('notif_repeat_days', $meta->notif_repeat_days);

        // Notification Channels (checkboxes).
        $channels = [];
        foreach (meta_manager::channel_options() as $key => $label) {
            $channels[] = $mform->createElement('advcheckbox', $key, '', $label);
        }
        $mform->addGroup($channels, self::CHANNELS_GROUP,
            get_string('notif_channels', 'local_learnova_course'), ['&nbsp;&nbsp;'], true);
        $selected = meta_manager::parse_channels($meta->notif_channels);
        foreach (array_keys(meta_manager::channel_options()) as $key) {
            $mform->setDefault(self::CHANNELS_GROUP . '[' . $key . ']', in_array($key, $selected) ? 1 : 0);
        }

        // ── Course Announcement band ─────────────────────────────────────────
        // Conditional, 3-tier banner shown under the topbar on the course
        // landing page. Rendered server-side by theme_learnovatheme; the
        // visibility logic lives in meta_manager::announcement_is_visible().
        $mform->addElement('header', 'learnovaannchdr', get_string('annc_sectionheading', 'local_learnova_course'));
        $mform->setExpanded('learnovaannchdr', false);

        // Announcement Enabled (toggle that gates the band entirely).
        $mform->addElement('advcheckbox', 'learnova_annc_enabled',
            get_string('annc_enabled', 'local_learnova_course'), get_string('yes'));
        $mform->setDefault('learnova_annc_enabled', $meta->learnova_annc_enabled);
        $mform->addHelpButton('learnova_annc_enabled', 'annc_enabled', 'local_learnova_course');

        // Announcement Tier (urgent / warning / info).
        $mform->addElement('select', 'learnova_annc_tier',
            get_string('annc_tier', 'local_learnova_course'), meta_manager::annc_tier_options());
        $mform->setDefault('learnova_annc_tier', $meta->learnova_annc_tier);
        $mform->disabledIf('learnova_annc_tier', 'learnova_annc_enabled');

        // Announcement Text — rich text (TinyMCE) so authors can bold/emphasise
        // the lead line. Submits as ['text' => ..., 'format' => ...]; only the
        // HTML body is persisted (see save_course_form). No embedded files.
        $annccontext = $courseid ? \context_course::instance($courseid) : \context_system::instance();
        $mform->addElement('editor', 'learnova_annc_text',
            get_string('annc_text', 'local_learnova_course'), null, [
                'maxfiles'              => 0,
                'maxbytes'              => 0,
                'context'               => $annccontext,
                'enable_filemanagement' => false,
                'autosave'              => false,
            ]);
        $mform->setType('learnova_annc_text', PARAM_RAW);
        $mform->setDefault('learnova_annc_text',
            ['text' => (string) $meta->learnova_annc_text, 'format' => FORMAT_HTML]);
        $mform->disabledIf('learnova_annc_text', 'learnova_annc_enabled');

        // Start Date (optional — empty = show immediately while enabled).
        $mform->addElement('date_selector', 'learnova_annc_start',
            get_string('annc_start', 'local_learnova_course'), ['optional' => true]);
        $mform->setDefault('learnova_annc_start', $meta->learnova_annc_start);
        $mform->addHelpButton('learnova_annc_start', 'annc_start', 'local_learnova_course');
        $mform->disabledIf('learnova_annc_start', 'learnova_annc_enabled');

        // End Date (optional — empty = show indefinitely while enabled).
        $mform->addElement('date_selector', 'learnova_annc_end',
            get_string('annc_end', 'local_learnova_course'), ['optional' => true]);
        $mform->setDefault('learnova_annc_end', $meta->learnova_annc_end);
        $mform->addHelpButton('learnova_annc_end', 'annc_end', 'local_learnova_course');
        $mform->disabledIf('learnova_annc_end', 'learnova_annc_enabled');

        // Visibility Scope (all visitors / enrolled only).
        $mform->addElement('select', 'learnova_annc_scope',
            get_string('annc_scope', 'local_learnova_course'), meta_manager::annc_scope_options());
        $mform->setDefault('learnova_annc_scope', $meta->learnova_annc_scope);
        $mform->addHelpButton('learnova_annc_scope', 'annc_scope', 'local_learnova_course');
        $mform->disabledIf('learnova_annc_scope', 'learnova_annc_enabled');

        // ── Discussion tab ───────────────────────────────────────────────────
        // Gates the custom course-level Discussion tab on the course landing
        // page. When off, the tab is not rendered at all.
        $mform->addElement('header', 'learnovadischdr', get_string('disc_sectionheading', 'local_learnova_course'));
        $mform->setExpanded('learnovadischdr', false);

        $mform->addElement('advcheckbox', 'learnova_discussion_enabled',
            get_string('disc_enabled', 'local_learnova_course'), get_string('yes'));
        $mform->setDefault('learnova_discussion_enabled', $meta->learnova_discussion_enabled);
        $mform->addHelpButton('learnova_discussion_enabled', 'disc_enabled', 'local_learnova_course');
    }

    /**
     * Validate the Learnova fields.
     *
     * @param after_form_validation $hook
     */
    public static function validate_course_form(after_form_validation $hook): void {
        $data = $hook->get_data();
        $errors = [];

        foreach (self::INT_FIELDS as $field) {
            if (isset($data[$field]) && $data[$field] !== ''
                    && (!is_numeric($data[$field]) || $data[$field] < 0)) {
                $errors[$field] = get_string('error_nonnegative', 'local_learnova_course');
            }
        }

        // Announcement end date, when set, must not precede the start date.
        $start = (int) ($data['learnova_annc_start'] ?? 0);
        $end   = (int) ($data['learnova_annc_end'] ?? 0);
        if ($start > 0 && $end > 0 && $end < $start) {
            $errors['learnova_annc_end'] = get_string('annc_error_enddate', 'local_learnova_course');
        }

        if ($errors) {
            $hook->add_errors($errors);
        }
    }

    /**
     * Persist the Learnova fields after the course form is submitted.
     *
     * Fires for both course creation and update; $data->id is the course id.
     * Skips when our fields are absent (e.g. programmatic course creation).
     *
     * @param after_form_submission $hook
     */
    public static function save_course_form(after_form_submission $hook): void {
        $data = $hook->get_data();

        if (empty($data->id)) {
            return;
        }

        $values = [];
        foreach (array_keys(meta_manager::get_defaults()) as $field) {
            // Handled separately below (group / editor element shapes).
            if ($field === 'notif_channels' || $field === 'learnova_annc_text') {
                continue;
            }
            if (property_exists($data, $field)) {
                $values[$field] = $data->{$field};
            }
        }

        // The announcement editor submits as ['text' => ..., 'format' => ...];
        // persist just the HTML body in the text column.
        if (property_exists($data, 'learnova_annc_text')) {
            $editor = $data->learnova_annc_text;
            $values['learnova_annc_text'] = is_array($editor) ? (string) ($editor['text'] ?? '') : (string) $editor;
        }

        // Collapse the channels checkbox group into a CSV string.
        $haschannels = property_exists($data, self::CHANNELS_GROUP);
        if ($haschannels && is_array($data->{self::CHANNELS_GROUP})) {
            $selected = [];
            foreach (array_keys(meta_manager::channel_options()) as $key) {
                if (!empty($data->{self::CHANNELS_GROUP}[$key])) {
                    $selected[] = $key;
                }
            }
            $values['notif_channels'] = implode(',', $selected);
        }

        // Only touch storage when this submission actually carried our fields.
        if (empty($values) && !$haschannels) {
            return;
        }

        meta_manager::save((int) $data->id, $values);
    }

    /**
     * Remove stored meta when its course is deleted.
     *
     * @param before_course_deleted $hook
     */
    public static function delete_course_meta(before_course_deleted $hook): void {
        if (!empty($hook->course->id)) {
            meta_manager::delete((int) $hook->course->id);
            discussion_manager::delete_course_posts((int) $hook->course->id);
        }
    }
}
