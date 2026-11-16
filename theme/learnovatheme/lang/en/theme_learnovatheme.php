<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']         = 'Learnova';
$string['choosereadme']       = 'Learnova is a custom Boost child theme for the Learnova LMS platform.';
$string['region-side-pre']    = 'Right';
$string['configtitle']        = 'Learnova';

// ── Admin / Home Setting menu ──────────────────────────────────────────────
$string['homesetting']                 = 'Home Setting';
$string['settings_hero_heading']       = 'Hero (logged-out)';
$string['settings_hero_heading_desc']  = 'Content shown in the front-page hero section for visitors who are not logged in.';
$string['settings_hero_eyebrow']       = 'Hero eyebrow';
$string['settings_hero_eyebrow_desc']  = 'Short uppercase label shown above the hero title (e.g. a tagline or category).';
$string['settings_hero_title']         = 'Hero title';
$string['settings_hero_title_desc']    = 'Headline shown in the hero. Use <strong>Strong</strong> to render the amber accent line, and Shift+Enter for a line break.';
$string['hero_title_default']          = 'Learn Smarter,<br>Grow <strong>Faster.</strong>';
$string['settings_hero_description']      = 'Hero description';
$string['settings_hero_description_desc'] = 'Supporting paragraph shown below the hero title. Plain text only — styling is handled by the theme.';
$string['settings_hero_image']            = 'Hero background image';
$string['settings_hero_image_desc']       = 'Faint background image behind the hero. Leave empty to use the default. A wide/landscape image works best.';

// Hero stat chips.
$string['statchip_courses']       = 'Available Courses';
$string['statchip_learners']      = 'Active Learners';
$string['statchip_satisfaction']  = 'Satisfaction Rate';
$string['statchip_instructors']   = 'Expert Instructors';
$string['statchip_materials']     = 'Total Learning Materials';
$string['statchip_hours']         = 'Total Learning Hours';
$string['settings_hero_stats_heading']             = 'Hero stats';
$string['settings_hero_stats_heading_desc']        = 'Choose which 4 stat chips appear in the hero, and in what order. Each chip shows a live, auto-computed value unless you set a manual override below.';
$string['settings_hero_stat_slot']                 = 'Stat chip {$a}';
$string['settings_hero_stat_none']                 = '— None —';
$string['settings_hero_stats_manual_heading']      = 'Stat manual overrides';
$string['settings_hero_stats_manual_heading_desc'] = 'Optionally override the auto-computed value for any stat — useful when the LMS is new and data is still sparse. Leave blank to use live data. You can enter values like “200+”, “12K” or “98%”. Note: <em>Satisfaction Rate</em> and <em>Total Learning Hours</em> have no live source yet, so they require a manual value for now.';
$string['settings_hero_stat_manual_desc']          = 'Leave blank to use the live auto-computed value.';

// Navbar primary nav labels.
$string['nav_home']           = 'Home';
$string['nav_dashboard']      = 'Dashboard';
$string['nav_catalogue']      = 'Course Catalogue';

// Auth / user menu.
$string['login']              = 'Sign In';
$string['messages']           = 'Messages';

// Message drawer.
$string['messagedrawer_title'] = 'Messages';
$string['learnerprofile']     = 'Learner Profile';
$string['siteadministration'] = 'Site Administration';
$string['comingsoon']         = 'Coming soon';

// Footer.
$string['footer_aria']        = 'Footer links';
$string['footer_privacy']     = 'Kebijakan Privasi';
$string['footer_userguide']   = 'Panduan Pengguna';
$string['footer_contact']     = 'Hubungi Tim L&D';

// ── Front-page hero (logged-out) ───────────────────────────────────────────
$string['hero_eyebrow']            = 'Corporate Learning Platform';
$string['hero_description']        = 'A unified learning ecosystem built for ambitious professionals. Explore interactive courses, earn certifications, and track your growth — all in one place.';
$string['hero_cta_primary']        = 'Explore Courses';
$string['hero_cta_secondary']      = 'Sign In to Get Started';

// ── Front-page hero (logged-in) ────────────────────────────────────────────
$string['hero_welcome_back']       = 'Welcome back';
$string['hero_greet_morning']      = 'Good morning';
$string['hero_greet_afternoon']    = 'Good afternoon';
$string['hero_greet_evening']      = 'Good evening';
// Hero motivational messages — defaults (overridable in Home Setting).
$string['heromsg_completed_today_default']  = 'Great work today! Keep the momentum going.';
$string['heromsg_completed_course_default'] = "You just completed a course. What's next on your journey?";
$string['heromsg_progress_week_default']    = "You're making great progress this week. Keep it up!";
$string['heromsg_overdue_default']          = "You have items that need your attention. Let's get back on track.";
$string['heromsg_away_short_default']       = "It's been a while. Ready to continue where you left off?";
$string['heromsg_away_long_default']        = "Your learning journey is waiting. Let's pick up where you left off.";
$string['heromsg_default_default']          = 'Welcome back. Your learning continues.';
// Learner stat chips (logged-in hero) — labels for the user_stats catalogue.
$string['userstat_courses_inprogress'] = 'In progress';
$string['userstat_courses_completed']  = 'Completed';
$string['userstat_activities_done']    = 'Activities';
$string['userstat_badges']             = 'Badges';
$string['userstat_competency']         = 'Competency';
$string['userstat_competency_met']     = 'Met';
$string['userstat_due']                = 'Due';
$string['userstat_hours']              = 'Hours';
$string['hero_journey_label']      = 'Active Journey';
$string['hero_journey_progress']   = 'Journey Progress';
$string['hero_card_label']         = 'Continue where you left off';
$string['hero_card_continue']      = 'Continue';
$string['hero_card_dashboard']     = 'Go to Dashboard';
$string['hero_card_activity_pos']  = 'Activity {$a->pos} of {$a->total}';
// Continue card — empty state (learner has opened nothing yet).
$string['hero_card_empty_label']   = 'Start learning';
$string['hero_card_empty_title']   = 'No activity yet';
$string['hero_card_empty_meta']    = 'Browse the catalogue and open your first course.';
$string['hero_card_browse']        = 'Browse courses';

// ── Front-page announcement band ───────────────────────────────────────────
$string['announce_tag']       = 'Info Program';
$string['announce_message']   = 'AI Tools for Learning Designers — now open for enrollment.';
$string['announce_cta']       = 'View course';
$string['settings_announce_heading']         = 'Announcement band';
$string['settings_announce_heading_desc']    = 'A site-wide notice shown directly below the hero on the front page.';
$string['settings_announce_enabled']         = 'Show announcement band';
$string['settings_announce_enabled_desc']    = 'Turn the announcement band on or off.';
$string['settings_announce_tag']             = 'Tag';
$string['settings_announce_tag_desc']        = 'Short label shown in the pill (e.g. "Info Program"). Displayed in uppercase.';
$string['settings_announce_message']         = 'Description';
$string['settings_announce_message_desc']    = 'The announcement text shown next to the tag.';
$string['settings_announce_cta_label']       = 'CTA label';
$string['settings_announce_cta_label_desc']  = 'Call-to-action link text (e.g. "View course →"). Leave blank to hide the link.';
$string['settings_announce_cta_url']         = 'CTA link';
$string['settings_announce_cta_url_desc']    = 'Where the call-to-action links to. Leave blank to default to the course catalogue.';

// Hero stats (logged-in) settings.
$string['settings_hero_userstats_heading']      = 'Hero stats (logged-in)';
$string['settings_hero_userstats_heading_desc'] = 'The four chips shown to a logged-in learner. Values are computed from that learner\'s own data — courses, activity completion, badges, competency plans and upcoming due dates. "Hours" has no data source yet, so it only shows when you fill in its manual override below.';
$string['settings_hero_userstat_slot']          = 'Learner stat slot {$a}';

// Hero motivational message settings.
$string['settings_heromsg_heading']      = 'Hero (logged-in)';
$string['settings_heromsg_heading_desc'] = 'Content shown in the front-page hero section for visitors who are logged in.';
$string['settings_heromsg_completed_today']  = 'Completed an activity today';
$string['settings_heromsg_completed_course'] = 'Completed a course (last 24h)';
$string['settings_heromsg_progress_week']    = 'Made progress this week';
$string['settings_heromsg_overdue']          = 'Has overdue items';
$string['settings_heromsg_away_long']        = 'Inactive 7+ days';
$string['settings_heromsg_away_short']       = 'Inactive 3+ days';
$string['settings_heromsg_default']          = 'Default (no special condition)';

// ── Browse by Category — front-page section ────────────────────────────────
$string['cats_title'] = 'Browse by Category';
$string['cats_subtitle'] = 'Find courses by your learning focus area';
$string['cats_viewall'] = 'All categories';
$string['cats_course_count'] = '{$a} courses';
$string['cats_slot_none'] = '— None —';

// Browse by Category — Home Settings
$string['settings_cats_heading'] = 'Browse by Category';
$string['settings_cats_heading_desc'] = 'Front-page section built from native course categories. Toggle it, edit the heading, then fill the slots below — each slot picks a category (slot order = display order) and an icon. Course counts are live. Empty slots are skipped.';
$string['settings_cats_enabled'] = 'Show Browse by Category';
$string['settings_cats_enabled_desc'] = 'Display the category section on the front page.';
$string['settings_cats_title'] = 'Section title';
$string['settings_cats_subtitle'] = 'Section subtitle';
$string['settings_cats_viewall_label'] = '"View all" link label';
$string['settings_cats_viewall_label_desc'] = 'Leave blank to hide the link. The link always points to the course catalogue.';
$string['settings_cats_slot'] = 'Category slot {$a}';
$string['settings_cats_slot_cat'] = 'Category';
$string['settings_cats_slot_icon'] = 'Icon';

// ── Common Questions (FAQ) — front-page section ────────────────────────────
$string['faq_eyebrow']  = 'FAQ';
$string['faq_title']    = 'Common questions';
$string['faq_subtitle'] = 'Everything you need to know about learning on Learnova.';

// Seeded default for the first FAQ item (editable in Home Settings afterwards).
$string['faq_default_q1'] = 'Who can access Learnova?';
$string['faq_default_a1'] = 'Learnova is available to all active employees. Sign in with your company account to start learning right away.';

// Common Questions — Home Settings
$string['settings_faq_heading'] = 'Common questions (FAQ)';
$string['settings_faq_heading_desc'] = 'Front-page FAQ section: a left image card and a right accordion of question + answer pairs. Toggle it, set the image and heading, then manage the items below. The section is hidden until at least one item is added.';
$string['settings_faq_enabled'] = 'Show Common questions';
$string['settings_faq_enabled_desc'] = 'Display the FAQ section on the front page.';
$string['settings_faq_image'] = 'Image';
$string['settings_faq_image_desc'] = 'Background image for the left card. Leave empty to use the default. A wide/landscape image works best.';
$string['settings_faq_eyebrow'] = 'Eyebrow label';
$string['settings_faq_title'] = 'Section title';
$string['settings_faq_subtitle'] = 'Section subtitle';
$string['settings_faq_items'] = 'FAQ items';
$string['settings_faq_items_desc'] = 'Add, remove and reorder question + answer pairs. Display order matches the list order. Up to 10 items.';
$string['faq_add_label'] = 'Add question';
$string['faq_remove_label'] = 'Remove';
$string['faq_move_up'] = 'Move up';
$string['faq_move_down'] = 'Move down';
$string['faq_question_ph'] = 'Question';
$string['faq_answer_ph'] = 'Answer';
$string['faq_max_note'] = 'Maximum of {$a} items reached.';

// Category icon picker (modal grid). Icon labels themselves come from the icon
// catalogue in classes/icons.php, not from lang strings.
$string['cats_icon_pick'] = 'Choose icon';
$string['cats_icon_search'] = 'Search icons…';
$string['cats_color'] = 'Colour';
$string['cats_color_hint'] = 'Pick a colour for this card: the icon uses it, its tile uses a lighter tint. Click Default to fall back to the built-in palette.';
$string['cats_color_reset'] = 'Default';

// ── Call to action (CTA) — front-page section ──────────────────────────────
$string['cta_title']         = 'Ready to level up your career?';
$string['cta_description']   = 'Explore courses designed for professionals. Build competencies, earn certificates, and grow — at your own pace.';
// Fixed button labels (behaviour is fixed; labels are localizable only).
$string['cta_btn_signin']    = 'Sign In to Get Started';
$string['cta_btn_dashboard'] = 'Go to Dashboard';
$string['cta_btn_browse']    = 'Browse Catalogue';

// Call to action — Home Settings
$string['settings_cta_heading'] = 'Call to action (CTA)';
$string['settings_cta_heading_desc'] = 'Closing banner shown near the bottom of the front page. Toggle it, then edit the heading and description. The buttons are fixed: the first sends visitors to sign in (or to their dashboard when logged in), the second opens the course catalogue.';
$string['settings_cta_enabled'] = 'Show call to action';
$string['settings_cta_enabled_desc'] = 'Display the CTA banner on the front page.';
$string['settings_cta_title'] = 'Heading';
$string['settings_cta_description'] = 'Description';

// Browse by Category — dynamic list setting (replaces per-slot selects)
$string['settings_cats_max']        = 'Maximum categories';
$string['settings_cats_max_desc']   = 'How many category cards can be added and shown on the front page. Choose between {$a->min} and {$a->max} (default 5).';
$string['settings_cats_items']      = 'Category list';
$string['settings_cats_items_desc'] = 'Add categories up to the maximum set above. Drag ↑↓ to reorder. Each row picks a Moodle course category and an icon from the built-in library. Changes take effect immediately after save.';
$string['cats_add_label']           = 'Add category';
$string['cats_remove_label']        = 'Remove';
$string['cats_move_up']             = 'Move up';
$string['cats_move_down']           = 'Move down';
$string['cats_max_note']            = 'Maximum of {$a} categories reached.';

// Latest Courses — front-page section
$string['courses_title']                       = 'Latest Courses in Catalogue';
$string['courses_subtitle']                    = 'Freshly added learning content from the L&D team';
$string['courses_viewall']                     = 'View full catalogue';
$string['settings_courses_heading']            = 'Latest Courses';
$string['settings_courses_heading_desc']       = 'Front-page section showing a row of 3 course cards. Toggle it, edit the heading, then choose the data source below. Cards reuse the catalogue card style; fields without data (cover image, duration, modules) are hidden automatically.';
$string['settings_courses_enabled']            = 'Show Latest Courses';
$string['settings_courses_enabled_desc']       = 'Display the courses section on the front page.';
$string['settings_courses_title']              = 'Section title';
$string['settings_courses_subtitle']           = 'Section subtitle';
$string['settings_courses_viewall_label']      = '"View all" link label';
$string['settings_courses_viewall_label_desc'] = 'Leave blank to hide the link. The link always points to the course catalogue.';
$string['settings_courses_mode']               = 'Data source';
$string['settings_courses_mode_desc']          = 'How the 3 courses are chosen. Admin curated: pick exactly which courses to show, in order, using the list below. Automatic: the most popular courses, ranked by completion rate and voluntary (self) enrolment. Note: course rating is not yet part of the ranking.';
$string['settings_courses_mode_curated']       = 'Admin curated';
$string['settings_courses_mode_automatic']     = 'Automatic (most popular)';
$string['settings_courses_items']              = 'Curated course list';
$string['settings_courses_items_desc']         = 'Used only in Admin curated mode. Add up to {$a->max} courses and drag ↑↓ to reorder; that order is the display order. Hidden or deleted courses are skipped automatically. If no courses are picked, the section falls back to the newest courses.';
$string['settings_courses_slot_course']        = 'Course';
$string['courses_add_label']                   = 'Add course';
$string['courses_remove_label']                = 'Remove';
$string['courses_move_up']                     = 'Move up';
$string['courses_move_down']                   = 'Move down';
$string['courses_max_note']                    = 'Maximum of {$a} courses reached.';

// ── Course Catalogue (course/index.php) ────────────────────────────────────
$string['catalogue_title']           = 'Course Catalogue';
$string['catalogue_subtitle']        = 'Browse {$a} courses across all learning areas';
$string['catalogue_search_ph']       = 'Search by title, topic, or instructor…';
$string['catalogue_search_btn']      = 'Search';
$string['catalogue_results']         = 'Showing <strong>{$a->shown}</strong> of {$a->total} courses';
$string['catalogue_empty']           = 'No courses to show yet.';
$string['catalogue_filter_category'] = 'Category';
$string['catalogue_filter_duration'] = 'Duration';
$string['catalogue_filter_status']   = 'Status';
$string['catalogue_dur_under30']     = 'Under 30 minutes';
$string['catalogue_dur_30to2h']      = '30 min – 2 hours';
$string['catalogue_dur_2to5h']       = '2 – 5 hours';
$string['catalogue_dur_over5h']      = 'Over 5 hours';
$string['catalogue_status_inprogress'] = 'In Progress';
$string['catalogue_status_completed']  = 'Completed';
$string['catalogue_status_notstarted'] = 'Not Started';
$string['catalogue_all_categories']  = 'All Categories';
$string['catalogue_clear']           = 'Clear';
$string['catalogue_clear_all']       = 'Clear all';
$string['catalogue_sort']            = 'Sort by';
$string['catalogue_sort_relevant']   = 'Most Relevant';
$string['catalogue_sort_newest']     = 'Newest First';
$string['catalogue_sort_enrolled']   = 'Most Enrolled';
$string['catalogue_sort_az']         = 'A – Z';
$string['catalogue_modules']         = '{$a} Modules';
$string['catalogue_enrolled']        = '{$a} enrolled';
$string['catalogue_view']            = 'Mulai';
$string['catalogue_continue']        = 'Continue';
$string['catalogue_progress_note']   = '{$a}% complete';
$string['catalogue_completed_note']  = 'Completed';
$string['catalogue_enrolled_note']   = 'Enrolled';
$string['catalogue_stat_courses']    = 'Courses';
$string['catalogue_stat_categories'] = 'Categories';
$string['catalogue_stat_learners']   = 'Learners';

// In-catalogue free-text search.
$string['catalogue_search_chip'] = 'Search: {$a}';
$string['search_empty']          = 'No courses match "{$a}". Try a different keyword.';

// ── Course Landing (course/view.php) ────────────────────────────────────────
// Hero.
$string['cl_instructor']      = 'Instructor';
$string['cl_ratings']         = 'ratings';
$string['cl_enrolled']        = 'enrolled';
$string['cl_modules']         = 'Modules';
$string['cl_activities']      = 'Activities';

// Announcement band tag labels (per tier).
$string['cl_announcement']  = 'Announcement';
$string['cl_annc_urgent']   = 'Urgent';
$string['cl_annc_warning']  = 'Warning';
$string['cl_annc_info']     = 'Info';

// Tabs.
$string['cl_tab_content']    = 'Course Content';
$string['cl_tab_about']      = 'About';
$string['cl_tab_discussion'] = 'Discussion';
$string['cl_tab_grades']     = 'Grades';
$string['cl_tab_progress']   = 'Progress';
$string['cl_tab_more']       = 'More';

// "More" dropdown — labels for the native Moodle course menu items it surfaces.
// Visibility per item is decided by Moodle (capability-gated nav); these only
// re-word the labels to match the design.
$string['cl_more_reports']            = 'Reports';
$string['cl_more_questionbanks']      = 'Question banks';
$string['cl_more_contentbank']        = 'Content bank';
$string['cl_more_coursecompletion']   = 'Course completion';
$string['cl_more_badges']             = 'Badges';
$string['cl_more_competencies']       = 'Competencies';
$string['cl_more_filters']            = 'Filters';
$string['cl_more_unenrol']            = 'Unenrol me from this course';
$string['cl_more_accessibility']      = 'Accessibility toolkit';
$string['cl_more_resetcompletion']    = 'Reset my activity completion';
$string['cl_more_recompletion']       = 'Course recompletion';
$string['cl_more_modifycompletion']   = 'Modify course completion dates';
$string['cl_more_reuse']              = 'Course reuse';

// Course content tab.
$string['cl_section_meta']   = '{$a->count} Activities · {$a->done} completed';
$string['cl_status_done']     = 'Completed';
$string['cl_status_progress'] = 'In Progress';
$string['cl_status_locked']   = 'Locked';
$string['cl_continue']        = 'Continue';
$string['cl_content_empty']   = 'This course has no content yet.';

// About tab.
$string['cl_about_title']      = 'What You Will Learn';

// Discussion tab.
$string['cl_discussion_title'] = 'Course Discussion';
$string['cl_discussion_placeholder'] = 'Write a message…';
$string['cl_discussion_send'] = 'Post';
$string['cl_discussion_reply'] = 'Reply';
$string['cl_discussion_delete'] = 'Delete';
$string['cl_discussion_replyingto'] = 'Replying to';
$string['cl_discussion_readonly'] = 'Only enrolled participants can post in this discussion.';
$string['cl_discussion_empty'] = 'No messages yet. Be the first to start the conversation.';
$string['cl_discussion_deleteconfirm'] = 'Delete this post? This cannot be undone.';

// Grades tab.
$string['cl_grades_title']    = 'Your Grades';
$string['cl_grades_activity'] = 'Activity';
$string['cl_grades_score']    = 'Score';
$string['cl_grades_status']   = 'Status';
$string['cl_grade_na']        = '—';
$string['cl_grade_passed']    = 'Passed';
$string['cl_grade_graded']    = 'Graded';
$string['cl_grade_pending']   = 'Pending';
$string['cl_overall']         = 'Overall Progress';
// Grades tab — instructor (roster) view.
$string['cl_grades_roster_title']  = 'Participant Grades';
$string['cl_grades_participant']   = 'Participant';
$string['cl_grades_course_total']  = 'Course Total';
$string['cl_grades_full_gradebook'] = 'Open full gradebook';
// Progress tab — learner (ring + activity list) view.
$string['cl_prog_done']         = 'Done';
$string['cl_prog_completed']    = 'Completed';
$string['cl_prog_inprogress']   = 'In Progress';
$string['cl_prog_notstarted']   = 'Not Started';
$string['cl_prog_deadline']     = 'Deadline';
$string['cl_prog_summary']      = '{$a->done} of {$a->total} activities completed · {$a->modules} modules remaining';
$string['cl_prog_st_completed'] = 'Completed';
$string['cl_prog_st_inprogress'] = 'In Progress';
$string['cl_prog_st_todo']      = 'Not yet done';
$string['cl_prog_st_locked']    = 'Locked';

// Sidebar.
$string['cl_progress']      = 'Learning Progress';
$string['cl_cta_start']     = 'Start Learning';
$string['cl_cta_continue']  = 'Continue Learning';
$string['cl_cta_review']    = 'Review Course';
$string['cl_meta_duration'] = 'Duration';
$string['cl_meta_completed']= 'Completed';
$string['cl_meta_enrolled'] = 'Enrolled';
$string['cl_completed_of']  = '{$a->done} of {$a->total}';
$string['cl_continue_from'] = 'Continue From';
$string['cl_cert_pending']  = 'Complete this course to earn a certificate';
$string['cl_cert_earned']   = 'Certificate Earned';

// Placeholder cards (features pending).
$string['cl_gamification_title'] = 'Learnova Experience';
$string['cl_gamification_sub']   = 'Gamification — Coming Soon';
$string['cl_rate_title']         = 'Rate This Course';
$string['cl_rate_sub']           = 'Available after course completion';

// ── Edit mode ───────────────────────────────────────────────
$string['cl_edit_notice_label'] = 'Edit mode';
$string['cl_edit_notice_tag']   = '✎ Edit Mode On';
$string['cl_edit_notice_text']  = 'You are editing this course. Changes are saved automatically.';
$string['cl_edit_bulk']         = 'Bulk Actions';
// Per-item three-dot menu.
$string['cl_edit_rename']           = 'Rename';
$string['cl_edit_drag']             = 'Drag to move';
$string['cl_edit_menu_more']        = 'More actions';
$string['cl_edit_menu_view']        = 'View';
$string['cl_edit_menu_editsettings']= 'Edit settings';
$string['cl_edit_menu_highlight']   = 'Highlight';
$string['cl_edit_menu_unhighlight'] = 'Unhighlight';
$string['cl_edit_menu_duplicate']   = 'Duplicate';
$string['cl_edit_menu_hide']        = 'Hide';
$string['cl_edit_menu_show']        = 'Show';
$string['cl_edit_menu_move']        = 'Move';
$string['cl_edit_menu_permalink']   = 'Permalink';
$string['cl_edit_menu_delete']      = 'Delete';
// Edit Settings sliding panel.
$string['cl_edit_panel_section']  = 'Edit Section Settings';
$string['cl_edit_panel_activity'] = 'Edit Activity Settings';
$string['cl_edit_panel_section_badge']  = 'Section';
$string['cl_edit_panel_activity_badge'] = 'Activity';
// "Add to course" opens the new-activity form in the same sliding panel.
$string['cl_add_panel_title']  = 'Add {$a}';
$string['cl_add_panel_badge']  = 'New Activity';
// Add Activity/Resource panel.
$string['cl_edit_addact']            = 'Add activity or resource';
$string['cl_edit_subsection']        = '+ Subsection';
$string['cl_edit_addsection']        = 'Add section';
$string['cl_edit_addpanel_title']    = 'Add Activity or Resource';
$string['cl_edit_addpanel_badge']    = 'Course Content';
$string['cl_edit_addpanel_search']   = 'Search activities and resources…';
$string['cl_edit_addpanel_activities'] = 'Activities';
$string['cl_edit_addpanel_resources']  = 'Resources';
$string['cl_edit_addpanel_plugins']    = 'Plugins';
// Add-panel filter pills + footer.
$string['cl_addfilter_all']       = 'All';
$string['cl_addfilter_favorited'] = 'Favorited';
$string['cl_addcat_learn']        = 'Learn';
$string['cl_addcat_assess']       = 'Assess';
$string['cl_addcat_discuss']      = 'Discuss';
$string['cl_addcat_interactive']  = 'Interactive';
$string['cl_addcat_live']         = 'Live';
$string['cl_addcat_survey']       = 'Survey';
$string['cl_addpanel_hint']       = 'Select an activity to add to this section';
$string['cl_addpanel_selected']   = '<strong>{$a}</strong> selected';
$string['cl_addpanel_add']        = 'Add to course';
$string['cl_addpanel_favorite']   = 'Favorite';
$string['cl_addpanel_favorited']  = 'Favorited';
$string['cl_addpanel_empty_title'] = 'No results found';
$string['cl_addpanel_empty_desc']  = 'Try a different keyword or filter';
// Move-target modal.
$string['cl_edit_move_title'] = 'Move to section';
// Delete confirmation.
$string['cl_edit_deleteconfirm'] = 'Delete "{$a}"? This cannot be undone.';
// Bulk bar.
$string['cl_bulk_select']       = 'Select';
$string['cl_bulk_selectall']    = 'Select all';
$string['cl_bulk_selected']     = '{$a} selected';
$string['cl_bulk_availability'] = 'Availability';

// ── Site administration outline (/admin/search.php) ──────────────────────────
// Leaves that hang directly off a tab root have no category of their own, so
// the outline collects them into a card named after the tab. The suffix is
// appended to the tab name in the template: "Plugins" + " " + "Setting".
$string['sa_section_general'] = 'General Setting';
$string['sa_section_suffix']  = 'Setting';
// Bookmark bar: the current user's admin bookmarks, shown above the tabs.
$string['sa_bookmarks']       = 'Bookmarks';

// ── Focus mode (distraction-free activity view) ──────────────────────────────
// Topbar.
$string['fm_back']            = 'Back to Course';
$string['fm_back_title']      = 'Return to course overview';
$string['fm_label_continuing'] = 'Continuing';
$string['fm_label_completed']  = 'Completed';
$string['fm_progress_label']  = '{$a->done} of {$a->total} activities';
$string['fm_darkmode']        = 'Toggle dark mode';
$string['fm_fullscreen']      = 'Toggle fullscreen';
$string['fm_settings']        = 'Activity settings';
// Sidebar (table of contents).
$string['fm_toc_title']       = 'Course Content';
$string['fm_toc_sub']         = '{$a->modules} modules · {$a->activities} activities';
$string['fm_hide']            = 'Hide';
$string['fm_hide_title']      = 'Hide Course Content';
$string['fm_show']            = 'Show';
$string['fm_show_title']      = 'Show Course Content';
$string['fm_module_meta']     = '{$a->count} activities · {$a->status}';
$string['fm_status_completed'] = 'Completed';
$string['fm_status_progress'] = 'In progress';
$string['fm_status_locked']   = 'Locked';
$string['fm_status_open']     = 'Not started';
// Bottom navigation.
$string['fm_prev']            = 'Previous';
$string['fm_next']            = 'Next';
// Activity intro strip.
$string['fm_description']     = 'Description';
$string['fm_discussion']      = 'Discussion';
$string['fm_manage']          = 'Manage activity';
$string['fm_manage_open']     = 'Open activity actions';

// ── Focus mode: document-mode content (Page / Book / Assignment / Forum) ─────
// Book.
$string['fm_book_prev']       = 'Previous chapter';
$string['fm_book_next']       = 'Next chapter';
$string['fm_book_print']      = 'Print chapter';
$string['fm_book_toc']        = 'Chapters';
$string['fm_book_toc_open']   = 'Open the chapter list';
$string['fm_book_chapter_hidden'] = 'Hidden';
// Assignment.
$string['fm_assign_opened']   = 'Opened';
$string['fm_assign_due']      = 'Due';
$string['fm_assign_left']     = 'Time remaining';
$string['fm_assign_overdue']  = 'Overdue by {$a}';
$string['fm_assign_staff']    = '{$a->submitted} of {$a->participants} learners submitted, {$a->needgrading} need grading.';
$string['fm_assign_grading']  = 'Open grading view';
// Forum thread list.
$string['fm_thread_meta']     = 'Started by {$a->author} · {$a->time}';
$string['fm_thread_replies']  = '{$a} replies';
$string['fm_thread_reply']    = '1 reply';
$string['fm_thread_noreply']  = 'No replies';
$string['fm_thread_add']      = 'Add discussion topic';
$string['fm_thread_empty']    = 'No discussions yet';
$string['fm_thread_empty_desc'] = 'Start the first topic to get the conversation going.';
$string['fm_thread_delete']   = 'Delete discussion';
$string['fm_thread_confirm']  = 'Delete this discussion permanently?';
$string['fm_thread_confirm_yes'] = 'Delete';
$string['fm_thread_confirm_no']  = 'Cancel';
$string['fm_thread_deletefail']  = 'Could not delete the discussion. Please reload and try again.';

// ── Focus mode: Activity Discussion panel ────────────────────────────────────
$string['fm_disc_title']      = 'Activity Discussion';
$string['fm_disc_on']         = 'On: {$a}';
$string['fm_disc_placeholder'] = 'Ask a question about this activity…';
$string['fm_disc_post']       = 'Post';
$string['fm_disc_reply']      = 'Reply to this topic';
$string['fm_disc_replyingto'] = 'Replying to';
$string['fm_disc_readonly']   = 'Enrol in this course to join the discussion.';
$string['fm_disc_empty']      = 'No questions yet';
$string['fm_disc_empty_desc'] = 'Ask the first question about this activity.';
$string['fm_disc_loadfail']   = 'Could not load the discussion. Please reload and try again.';
$string['fm_disc_postfail']   = 'Could not post your message. Please try again.';
$string['fm_assign_addsubmission'] = 'Add submission';
// PDF viewer.
$string['fm_pdf_loading']       = 'Loading document…';
$string['fm_pdf_pagelabel']     = 'Page';
$string['fm_pdf_zoom']          = 'Zoom level';
$string['fm_pdf_zoomin']        = 'Zoom in';
$string['fm_pdf_zoomout']       = 'Zoom out';
$string['fm_pdf_fitwidth']      = 'Fit width';
$string['fm_pdf_fitpage']       = 'Fit page';
$string['fm_pdf_download']      = 'Download PDF';
$string['fm_pdf_print']         = 'Print PDF';
$string['fm_pdf_fallback']      = 'This document could not be displayed in the Learnova viewer, so your browser is showing it instead.';
// SCORM entry panel / player frame.
$string['fm_scorm_exit']        = 'Exit activity';
$string['fm_scorm_browsenote']  = 'Preview mode — your progress is not recorded.';
$string['fm_scorm_status']      = 'Attempt status';
// Quiz attempt progress indicator.
$string['fm_quiz_pagelabel']    = 'Page {$a->page} of {$a->total}';
$string['fm_quiz_dot']          = 'Question {$a}';
