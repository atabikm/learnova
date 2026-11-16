<?php
// This file is part of the Learnova theme.

/**
 * Activity Discussion panel context builder.
 *
 * The slide-over that opens from the Discussion button in the activity intro
 * strip. It is a *presentation* of the existing Learnova discussion engine
 * (local_learnova_course), not a discussion model of its own: the same table,
 * the same 1-level reply rule and the same moderation capability as the course
 * landing Discussion tab, only scoped to a course module instead of a course.
 *
 * @package    theme_learnovatheme
 */

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use context_module;
use local_learnova_course\discussion_manager;

/**
 * Builds the template context for the focus-mode Activity Discussion panel.
 */
class focus_discussion {

    /**
     * Whether the engine that backs the panel is installed.
     *
     * The theme must render without local_learnova_course present, so the panel
     * (and its trigger badge) simply stands down when the plugin is missing.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(discussion_manager::class);
    }

    /**
     * Panel context for one activity.
     *
     * @param \stdClass $course
     * @param cm_info   $cm
     * @return array Template context under the "discussion" key, plus the badge count.
     */
    public static function context(\stdClass $course, cm_info $cm): array {
        if (!self::available()) {
            return [
                'hasdiscussion'      => false,
                'discussioncount'    => 0,
                'hasdiscussioncount' => false,
            ];
        }

        $context = context_module::instance($cm->id);
        $threads = discussion_manager::get_threads((int) $course->id, (int) $cm->id, $context);
        $count = discussion_manager::count_posts((int) $course->id, (int) $cm->id);

        return [
            'hasdiscussion' => true,
            'discussion'    => [
                'courseid'   => (int) $course->id,
                'cmid'       => (int) $cm->id,
                'activity'   => $cm->get_formatted_name(),
                'threads'    => $threads,
                'hasthreads' => !empty($threads),
                'canpost'    => discussion_manager::can_post($context),
            ],
            'discussioncount'    => $count,
            'hasdiscussioncount' => $count > 0,
        ];
    }
}
