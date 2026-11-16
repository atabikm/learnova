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

/**
 * Hook callbacks for local_learnova_course.
 *
 * Hooks into the core course editing form to add Learnova custom fields,
 * validate them, and persist them to {learnova_course_meta}.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => \local_learnova_course\hook_callbacks::class . '::extend_course_form',
    ],
    [
        'hook' => \core_course\hook\after_form_validation::class,
        'callback' => \local_learnova_course\hook_callbacks::class . '::validate_course_form',
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => \local_learnova_course\hook_callbacks::class . '::save_course_form',
    ],
    [
        'hook' => \core_course\hook\before_course_deleted::class,
        'callback' => \local_learnova_course\hook_callbacks::class . '::delete_course_meta',
    ],
];
