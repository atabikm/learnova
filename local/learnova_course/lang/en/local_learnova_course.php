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
 * English strings for local_learnova_course.
 *
 * @package    local_learnova_course
 * @copyright  2026 Look Media Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Learnova Course Fields';
$string['sectionheading'] = 'Course settings';

// Field labels.
$string['learning_hours_minutes'] = 'Learning hours (minutes)';
$string['learning_hours_minutes_help'] = 'Estimated time to complete this course, in minutes. Shown elsewhere as "Xh Ym" and used for dashboard stats, reports and the homepage popular courses list.';
$string['course_level'] = 'Course level';
$string['learning_objectives'] = 'Learning objectives';
$string['learning_objectives_help'] = 'What learners will be able to do after completing this course. Enter one objective per line. These appear on the course landing page "About" tab as a "What You Will Learn" checklist; the tab is hidden when this is empty.';
$string['cert_enabled'] = 'Certificate enabled';
$string['cert_template_id'] = 'Certificate template';
$string['cert_issuer'] = 'Certificate issuer';
$string['cert_validity_days'] = 'Certificate expiry (days)';
$string['cert_validity_days_help'] = 'Number of days the certificate remains valid after it is issued. Use 0 for no expiry.';
$string['deadline_type'] = 'Deadline type';
$string['deadline_days'] = 'Deadline duration (days)';
$string['notif_reminder_days'] = 'Notification reminder (days)';
$string['notif_repeat_days'] = 'Notification repeat (days)';
$string['notif_channels'] = 'Notification channels';

// Course level options.
$string['level_beginner'] = 'Beginner';
$string['level_intermediate'] = 'Intermediate';
$string['level_advanced'] = 'Advanced';

// Deadline type options.
$string['deadline_enrollment'] = 'Enrollment date';
$string['deadline_coursestart'] = 'Course start date';
$string['deadline_courseend'] = 'Course end date';

// Notification channel options.
$string['channel_email'] = 'Email';
$string['channel_push'] = 'Push notification';

// Certificate template options.
$string['cert_template_default'] = 'Default';

// Course Announcement band.
$string['annc_sectionheading'] = 'Course Announcement';
$string['annc_enabled'] = 'Announcement enabled';
$string['annc_enabled_help'] = 'Show the announcement band under the topbar on the course landing page. When off, the band is not rendered.';
$string['annc_tier'] = 'Announcement tier';
$string['annc_text'] = 'Announcement text';
$string['annc_start'] = 'Start date';
$string['annc_start_help'] = 'Optional. The band only appears from this date onwards. Leave unset to show it immediately while enabled.';
$string['annc_end'] = 'End date';
$string['annc_end_help'] = 'Optional. The band stops appearing after this date. Leave unset to show it indefinitely while enabled.';
$string['annc_scope'] = 'Visibility scope';
$string['annc_scope_help'] = '"All visitors" shows the band to everyone, including guests. "Enrolled only" hides it from guests and non-enrolled users.';

// Announcement tier options.
$string['annc_tier_urgent'] = 'Urgent';
$string['annc_tier_warning'] = 'Warning';
$string['annc_tier_info'] = 'Info';

// Announcement scope options.
$string['annc_scope_all'] = 'All visitors';
$string['annc_scope_enrolled'] = 'Enrolled only';

// Validation.
$string['error_nonnegative'] = 'Enter a number of 0 or greater.';
$string['annc_error_enddate'] = 'The end date cannot be earlier than the start date.';

// Discussion tab.
$string['disc_sectionheading'] = 'Discussion';
$string['disc_enabled'] = 'Discussion enabled';
$string['disc_enabled_help'] = 'Show the course Discussion tab on the course landing page, where enrolled participants can post messages and replies. When off, the tab is not rendered.';

// Discussion capability.
$string['learnova_course:moderatediscussion'] = 'Moderate (delete) course discussion posts';

// Discussion runtime strings.
$string['disc_unknown_user'] = 'Unknown user';
$string['disc_error_empty'] = 'Enter a message before posting.';
$string['disc_error_toolong'] = 'Your message is too long (maximum {$a} characters).';
$string['disc_error_parentmissing'] = 'The post you are replying to no longer exists.';
$string['disc_error_nestedreply'] = 'Replies can only be one level deep.';
$string['disc_error_cannotpost'] = 'You do not have permission to post in this discussion.';
$string['disc_error_wrongcourse'] = 'That post does not belong to this course.';

// Activity-scoped discussion (focus mode panel).
$string['disc_thread_meta'] = 'Started by {$a->author} · {$a->time}';
$string['disc_thread_untitled'] = 'Untitled topic';
$string['disc_thread_noreply'] = 'No replies';
$string['disc_thread_reply'] = '1 reply';
$string['disc_thread_replies'] = '{$a} replies';

// Course Management page.
$string['management_title'] = 'Course Management';
$string['management_back']  = 'Back to Course';
$string['management_empty'] = 'Management tools will appear here soon.';
$string['management_panel_soon'] = 'This section is under construction';
$string['management_settings_sub'] = 'Edit general course information, format, and behaviour';

// Course Management sidebar — group labels.
$string['management_grp_management'] = 'Management';
$string['management_grp_grades']     = 'Grades';
$string['management_grp_reports']    = 'Reports';
$string['management_grp_content']    = 'Content';
$string['management_grp_reuse']      = 'Reuse & Admin';
$string['management_grp_myaccess']   = 'My Access';

// Course Management sidebar — menu items.
$string['management_menu_settings']         = 'Course Settings';
$string['management_menu_participants']     = 'Participants';
$string['management_menu_gradeoverview']    = 'Grade Overview';
$string['management_menu_graderreport']     = 'Grader Report';
$string['management_menu_activities']       = 'Activities';
$string['management_menu_gradesview']       = 'Grades View';
$string['management_menu_gradessetup']      = 'Grades Setup';
$string['management_menu_reports']          = 'Reports';
$string['management_menu_questionbanks']    = 'Question Banks';
$string['management_menu_contentbank']      = 'Content Bank';
$string['management_menu_competencies']     = 'Competencies';
$string['management_menu_coursecompletion'] = 'Course Completion';
$string['management_menu_badges']           = 'Badges';
$string['management_menu_filters']          = 'Filters';
$string['management_menu_coursereuse']      = 'Course Reuse';
$string['management_menu_recyclebin']       = 'Recycle Bin';
$string['management_menu_lti']              = 'LTI External Tools';
$string['management_menu_accessibility']    = 'Accessibility Toolkit';
$string['management_menu_recompletion']     = 'Course Recompletion';
$string['management_menu_modifycompletion'] = 'Modify Completion Dates';
$string['management_menu_unenrol']          = 'Unenrol Me';
$string['management_menu_resetcompletion']  = 'Reset My Completion';

// Course Management sidebar — small role tags on menu items.
$string['management_tag_learner'] = 'Learner';
$string['management_tag_teacher'] = 'Teacher';

// Course Management — Participants panel.
$string['management_participants_sub'] = 'Manage enrolled users, roles, and groups';
$string['pt_enrolled_users'] = 'Enrolled Users';
$string['pt_enrol_users']    = '+ Enrol Users';
$string['pt_search_ph']      = 'Search by name or email...';
$string['pt_apply']          = 'Apply filters';
$string['pt_all_roles']      = 'All roles';
$string['pt_all_groups']     = 'All groups';
$string['pt_col_name']       = 'Name';
$string['pt_col_email']      = 'Email';
$string['pt_col_roles']      = 'Roles';
$string['pt_col_groups']     = 'Groups';
$string['pt_col_lastaccess'] = 'Last access';
$string['pt_col_status']     = 'Status';
$string['pt_no_groups']      = 'No groups';
$string['pt_edit']           = 'Edit';
$string['pt_edit_title']     = 'Edit {$a}';
$string['pt_delete']         = 'Delete';
$string['pt_delete_title']   = 'Unenrol {$a}';
$string['pt_delete_confirm'] = 'Unenrol {$a} from this course? This removes all of their enrolments and cannot be undone.';
$string['pt_roles']          = 'Roles';
$string['pt_groups']         = 'Groups';
$string['pt_enrolment']      = 'Enrolment';
$string['pt_no_assignable_roles'] = 'No assignable roles';
$string['pt_no_course_groups']    = 'This course has no groups';
$string['pt_status_active']    = 'Active';
$string['pt_status_suspended'] = 'Suspended';
$string['pt_found']   = '{$a} participants found';
$string['pt_showing'] = 'Showing {$a->from}–{$a->to} of {$a->total} participants';
$string['pt_prev']    = '← Prev';
$string['pt_next']    = 'Next →';
$string['pt_never']       = 'Never';
$string['pt_ago_now']     = 'Just now';
$string['pt_ago_minutes'] = '{$a} min ago';
$string['pt_ago_hours']   = '{$a} hours ago';
$string['pt_ago_days']    = '{$a} days ago';
$string['pt_ago_months']  = '{$a} months ago';
$string['pt_ago_years']   = '{$a} years ago';
$string['pt_none_title'] = 'No participants found';
$string['pt_none_desc']  = 'Try adjusting your search or filters.';
$string['pt_selectall'] = 'Select all';
$string['pt_selected']  = '{$a} selected';
$string['pt_message']   = 'Send message';
$string['pt_clear']     = 'Clear';

// Course Management — Grade Overview panel (single learner, grouped by module).
$string['management_gradeoverview_sub'] = 'Individual grade report — as seen by the learner';
$string['go_viewing_as']   = 'Viewing as';
$string['go_col_range']    = 'Range';
$string['go_col_feedback'] = 'Feedback';
$string['go_col_weight']   = 'Contribution to total';
$string['go_empty']        = '(Empty)';
$string['go_course_total'] = 'Course Total';
$string['go_no_items_title'] = 'No graded activities';
$string['go_no_items_desc']  = 'This course has no graded activities in the gradebook yet.';
$string['go_no_learners_title'] = 'No learners to grade';
$string['go_no_learners_desc']  = 'No gradable users are enrolled in this course yet.';

// Course Management — Grader Report panel (all learners, horizontal table).
$string['management_graderreport_sub'] = 'All learner grades across every activity — full horizontal view';
$string['gr_col_name']     = 'First name / Last name';
$string['gr_col_email']    = 'Email';
$string['gr_col_total']    = 'Course Total';
$string['gr_overall_avg']  = 'Overall average';
$string['gr_search_ph']    = 'Search users...';
$string['gr_apply']        = 'Search';
$string['gr_export']       = 'Export CSV';
$string['gr_showing']      = 'Showing {$a->shown} of {$a->total} participants';
$string['gr_show_all']     = 'Show all';
$string['gr_none_title']   = 'No grades to show';
$string['gr_none_desc']    = 'No gradable users match your search, or the course has no graded activities.';

// Course Management — Activities panel (one collapsible card per activity type).
$string['management_activities_sub'] = 'Every activity in this course, grouped by type';
$string['act_count']      = '{$a} activities';
$string['act_count_one']  = '1 activity';
$string['act_none_title'] = 'No activities yet';
$string['act_none_desc']  = 'Activities and resources added to this course will be listed here, grouped by type.';

// Course Management — Reports panel (sub-tabs over the embedded course reports).
$string['management_reports_sub'] = 'Detailed analytics and learning data for this course';
$string['rp_none_title'] = 'No reports available';
$string['rp_none_desc']  = 'No course report is available to you here — reports appear once you have permission to view them and the course has the data they need.';

// Course Management — Course Completion panel (the native completion form).
$string['management_coursecompletion_sub'] = 'Define what learners must do to complete this course';

// Course Management — My Access panels (act on the viewer's own participation).
$string['management_unenrol_sub'] = 'Remove yourself from this course';
$string['management_resetcompletion_sub'] = 'Clear your own completion status so you can start over';
$string['um_warning']    = 'Are you sure you want to unenrol from <strong>{$a}</strong>? Your progress and grades in this course will be removed and cannot be recovered.';
$string['um_confirm']    = 'Confirm unenrol';
$string['um_none_title'] = 'You cannot unenrol yourself';
$string['um_none_desc']  = 'None of the enrolment methods used in this course lets you remove yourself. Ask a teacher or administrator to do it for you.';
$string['rc_intro']      = 'Select which activities to reset. Their completion is cleared, so you can work through them again.';
$string['rc_note']       = 'Only activities you ticked complete yourself are listed. Completion that Moodle works out for you — from grades, submissions or views — is recalculated automatically and cannot be reset here.';
$string['rc_reset']      = 'Reset selected';
$string['rc_confirm_title']  = 'Reset completion?';
$string['rc_confirm_body']   = 'The selected activities will be marked as not completed. You will have to complete them again.';
$string['rc_none_title'] = 'Nothing to reset';
$string['rc_none_desc']  = 'You have not ticked any activity in this course as complete yourself.';
$string['rc_off_title']  = 'Completion tracking is off';
$string['rc_off_desc']   = 'This course does not track completion, so there is nothing to reset.';

// Course Management — Competencies panel (card grid + native picker web service).
$string['management_competencies_sub'] = 'Competencies linked to this course and their completion criteria';
$string['cp_title']         = 'Competencies';
$string['cp_add']           = 'Add competencies to course';
$string['cp_add_selected']  = 'Add selected';
$string['cp_badge']         = 'Competency';
$string['cp_summary']       = 'You are proficient in <b>{$a->proficient} out of {$a->total}</b> competencies in this course.';
$string['cp_filter']        = 'Filter competencies by resource or activity';
$string['cp_filter_none']   = 'No filter';
$string['cp_path']          = 'Path:';
$string['cp_framework']     = 'Competency framework';
$string['cp_search']        = 'Search competencies';
$string['cp_search_ph']     = 'Search…';
$string['cp_inuse']         = 'Already added';
$string['cp_manage']        = 'Manage competencies and frameworks →';
$string['cp_status_met']     = 'Met';
$string['cp_status_pending'] = 'Not met yet';
$string['cp_status_none']    = 'Not configured';
$string['cp_none_title']    = 'No competencies yet';
$string['cp_none_desc']     = 'Add competencies from a framework to describe what learners should be able to do after this course.';
$string['cp_nomatch_title'] = 'No competencies for that activity';
$string['cp_nomatch_desc']  = 'No competency in this course is attached to the activity you picked.';
$string['cp_off_title']     = 'Competencies are switched off';
$string['cp_off_desc']      = 'Competency-based education is disabled for this site, so this course cannot use competencies.';

// Course Management — Badges panel (badge list + the native badge form).
$string['management_badges_sub'] = 'Create and manage badges for this course';
$string['bd_title']          = 'Badges';
$string['bd_new']            = '+ New Badge';
$string['bd_new_title']      = 'New badge';
$string['bd_badge']          = 'Badge';
$string['bd_col_name']       = 'Name';
$string['bd_col_status']     = 'Status';
$string['bd_col_recipients'] = 'Recipients';
$string['bd_col_actions']    = 'Actions';
$string['bd_edit']           = 'Edit';
$string['bd_open_hint']      = 'Open badge overview →';
$string['bd_none_title']     = 'No badges yet';
$string['bd_none_desc']      = 'Create a badge to recognise what learners achieve in this course. You choose its image, criteria and expiry.';

// Course Management — Question Banks panel (bank list + the native bank page).
$string['management_questionbanks_sub'] = 'Reusable question pools for the quizzes in this course';
$string['qb_banks_title']    = 'Question banks';
$string['qb_add_bank']       = '+ Add question bank';
$string['qb_col_bank']       = 'Bank';
$string['qb_col_type']       = 'Type';
$string['qb_col_categories'] = 'Categories';
$string['qb_col_questions']  = 'Questions';
$string['qb_col_actions']    = 'Actions';
$string['qb_manage']         = 'Manage';
$string['qb_type_shared']    = 'Shared';
$string['qb_type_private']   = 'Private';
$string['qb_new_question']       = 'Create a new question…';
$string['qb_new_question_title'] = 'Create a new question';
$string['qb_new_question_badge'] = 'Question';
$string['qb_add_bank_title']     = 'Add a question bank';
$string['qb_choose_type']        = 'Choose a question type. The next step is Moodle\'s own form for that type, so every field it needs is there.';
$string['qb_none_title']     = 'No question bank yet';
$string['qb_none_desc']      = 'Add a question bank activity to build a reusable pool of questions, or create questions inside a quiz to give it a private one.';

// Course Management — Course Reuse panel (sub-tabs over the native reuse pages).
$string['management_coursereuse_sub'] = 'Import, back up, restore, reset or copy this course';
$string['cr_note_import']  = 'Copy activities, resources and settings into this course from another course you teach. Nothing in this course is removed.';
$string['cr_note_backup']  = 'Create a downloadable backup (.mbz) of this course — its content, and optionally its user data.';
$string['cr_note_restore'] = 'Restore a backup file (.mbz) into this course, or into a new one. Upload a file or pick one from a backup area below.';
$string['cr_note_reset']   = 'Resetting empties this course of user data — enrolments, submissions, grades, posts and logs — while keeping the activities themselves. This cannot be undone.';
$string['cr_note_copy']    = 'Create a new course with the same content as this one. The original course is left untouched.';
$string['cr_none_title']   = 'No reuse tools available';
$string['cr_none_desc']    = 'You do not have permission to import, back up, restore, reset or copy this course.';

// Course Management — Recycle Bin panel (Moodle's tool_recyclebin, course bin).
$string['management_recyclebin_sub'] = 'Activities deleted from this course, before they are removed for good';
$string['rb_col_item']       = 'Item';
$string['rb_col_deleted']    = 'Deleted on';
$string['rb_col_actions']    = 'Actions';
$string['rb_restore']        = 'Restore';
$string['rb_delete']         = 'Delete permanently';
$string['rb_delete_title']   = 'Delete permanently?';
$string['rb_delete_confirm'] = '"{$a}" will be removed for good. This cannot be undone.';
$string['rb_expiry']         = 'Items left here are deleted automatically after {$a}.';
$string['rb_empty_title']    = 'Recycle bin is empty';
$string['rb_empty_desc']     = 'Activities you delete from this course are held here for a while, so you can put them back.';
$string['rb_off_title']      = 'Recycle bin unavailable';
$string['rb_off_desc']       = 'The course recycle bin is switched off for this site, or you do not have permission to view it.';

// Grades View — "Overview report" tab (one learner across their courses).
$string['or_col_course']  = 'Course name';
$string['or_col_grade']   = 'Grade';
$string['or_col_rank']    = 'Rank';
$string['or_this_course'] = 'This course';
$string['or_none']        = '-';
$string['or_noaccess']    = 'You do not have permission to view this learner\'s grades across their courses.';
$string['or_none_title']  = 'No courses to show';
$string['or_none_desc']   = 'This learner is not enrolled in any course whose grades you can see.';

// Grades View — "Grade summary" tab (course-wide average per grade item).
$string['gs_col_item']    = 'Grade item';
$string['gs_col_average'] = 'Average';
$string['gs_type_manual'] = 'Manual item';

// Course Management — grade panels: gradebook still being recalculated.
$string['grade_regrading_title'] = 'Grades are being recalculated';
$string['grade_regrading_desc']  = 'This course\'s gradebook changed and Moodle has queued the recalculation as a background task. Grades appear here once it has run.';

// Course Management — Grades View / Grades Setup panels (native gradebook tabs).
$string['management_gradesview_sub']  = 'Every gradebook view for this course — grader, user, history and summary';
$string['management_gradessetup_sub'] = 'Set up the gradebook: items and categories, course grade settings, preferences';
$string['gt_none_title'] = 'No gradebook pages available';
$string['gt_none_desc']  = 'You do not have permission to open any of the gradebook pages in this section.';

// Privacy.
$string['privacy:metadata:learnova_course_meta'] = 'Learnova course-level settings. This data describes a course and contains no personal data.';
$string['privacy:metadata:learnova_discussion_posts'] = 'Posts authored by users in the course Discussion tab.';
$string['privacy:metadata:learnova_discussion_posts:courseid'] = 'The course the post belongs to.';
$string['privacy:metadata:learnova_discussion_posts:cmid'] = 'The activity the post is scoped to, if it was posted in an activity discussion.';
$string['privacy:metadata:learnova_discussion_posts:userid'] = 'The user who wrote the post.';
$string['privacy:metadata:learnova_discussion_posts:parentid'] = 'The post being replied to, if any.';
$string['privacy:metadata:learnova_discussion_posts:message'] = 'The message body.';
$string['privacy:metadata:learnova_discussion_posts:timecreated'] = 'When the post was created.';
$string['privacy:metadata:learnova_discussion_posts:timemodified'] = 'When the post was last modified.';
$string['privacy:discussionpath'] = 'Course discussion posts';
