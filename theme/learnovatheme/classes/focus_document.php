<?php
// This file is part of the Learnova theme.

/**
 * Document-mode content data for focus mode.
 *
 * Builds the per-type extras that sit inside the centered reading card for the
 * text/form-heavy activities: Page, Book, Assignment and Forum. Everything here
 * is additive chrome around Moodle's own output — the only type that replaces
 * core rendering is Forum, and only when the forum is plain enough for a card
 * list to represent it faithfully (see {@see self::forum()}).
 *
 * @package    theme_learnovatheme
 */

namespace theme_learnovatheme;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use context_module;
use moodle_page;
use moodle_url;
use stdClass;

/**
 * Per-activity-type data for the document-mode reading card.
 */
class focus_document {

    /**
     * Activity types that read as documents: prose and forms rather than an
     * immersive player. Anything else keeps the plain full-width treatment.
     */
    private const DOCUMENT_MODS = ['page', 'book', 'assign', 'forum'];

    /**
     * Activity types that are a player rather than a page: the content fills the
     * available width but keeps its own proportions instead of being cropped.
     */
    private const MEDIA_MODS = ['resource', 'scorm'];

    /**
     * Forum types a card list can represent without dropping behaviour. Q&A
     * hides posts until you answer, blog renders full posts inline and single
     * has no list at all — those keep Moodle's own rendering.
     */
    private const CARD_FORUM_TYPES = ['general', 'news'];

    /** Above this many discussions the native list wins, for its pagination. */
    private const MAX_CARD_THREADS = 50;

    /**
     * Whether this activity gets the centered reading card.
     *
     * @param cm_info $cm
     * @return bool
     */
    public static function applies(cm_info $cm): bool {
        return in_array($cm->modname, self::DOCUMENT_MODS, true)
            || in_array($cm->modname, self::MEDIA_MODS, true);
    }

    /**
     * Document-mode context for the current activity.
     *
     * @param moodle_page $page
     * @return array Template context; 'suppressnative' asks the layout not to
     *               emit main_content because we replaced it.
     */
    public static function context(moodle_page $page): array {
        $cm = $page->cm;

        if (in_array($cm->modname, self::MEDIA_MODS, true)) {
            $media = self::media($cm);
            // The embedded player replaces the module's entry page; without
            // this the launch button would sit under a player already running.
            // Our entry panel and the framed player each stand in for the
            // module's own page, so neither wants it rendered underneath.
            // The PDF viewer likewise stands in for core's <object>, which stays
            // in the DOM hidden so the JS can fall back to it (see self::pdf()).
            $media['suppressnative'] = isset($media['scormplaying']) || isset($media['pdf']);
            return $media + ['isdocument' => false];
        }

        $ctx = [
            'isdocument'     => true,
            'ismedia'        => false,
            'suppressnative' => false,
        ];

        switch ($cm->modname) {
            case 'book':
                $ctx['book'] = self::book($page, $cm);
                break;
            case 'assign':
                $ctx['assign'] = self::assign($page, $cm);
                break;
            case 'forum':
                $forum = self::forum($page, $cm);
                if ($forum !== null) {
                    $ctx['forum'] = $forum;
                    $ctx['suppressnative'] = true;
                }
                break;
        }

        return $ctx;
    }

    /**
     * Media mode: which kind of player is on the page.
     *
     * The kind only picks the sizing rule — the player itself is always
     * Moodle's. A File resource can be a video, a PDF or something the browser
     * is left to handle, and only the first two have proportions worth
     * preserving, so the mimetype decides. SCORM is treated as video for now:
     * its real launch and tracking behaviour is not scoped yet.
     *
     * @param cm_info $cm
     * @return array
     */
    private static function media(cm_info $cm): array {
        global $CFG, $DB;

        $kind = 'other';
        $extra = [];

        if ($cm->modname === 'scorm') {
            $kind = 'scorm';
            $extra = self::scorm($cm);
            if (!empty($extra['scormplaying'])) {
                // Playing: there is a real frame to fit.
                $kind = 'video';
            }
        } else {
            require_once($CFG->dirroot . '/mod/resource/locallib.php');

            $resource = $DB->get_record('resource', ['id' => $cm->instance]);
            $file = self::resource_main_file($cm);
            if ($resource && $file) {
                // view.php fills this in before asking for the display type;
                // without it the answer is always "download".
                $resource->mainfile = $file->get_filename();
                $display = (int) resource_get_final_display_type($resource);
                $mimetype = $file->get_mimetype();

                if ($mimetype === 'application/pdf') {
                    // The viewer stands in for whatever core would have done
                    // with the file, so the display option only has to rule out
                    // the one intent it cannot honour: a forced download. Note
                    // that "Automatic" resolves to *open*, not embed, for a PDF
                    // — requiring embed here would leave the default setting,
                    // which is what most File activities are on, without a
                    // viewer.
                    if ($display !== RESOURCELIB_DISPLAY_DOWNLOAD) {
                        $kind = 'pdf';
                        $extra = ['pdf' => self::pdf($cm, $resource, $file)];
                    }
                } else if ($display === RESOURCELIB_DISPLAY_EMBED
                        && file_mimetype_in_typegroup($mimetype, ['web_video', 'web_audio'])) {
                    $kind = 'video';
                }
            }
        }

        return $extra + [
            'ismedia'   => true,
            'mediakind' => $kind,
            // Only these two have an aspect ratio the JS helper should hold.
            'mediafit'  => $kind === 'video',
        ];
    }

    /**
     * The file a File resource actually shows.
     *
     * @param cm_info $cm
     * @return \stored_file|null
     */
    private static function resource_main_file(cm_info $cm) {
        $files = get_file_storage()->get_area_files(
            context_module::instance($cm->id)->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );
        $file = reset($files);
        return $file ?: null;
    }

    /**
     * PDF: everything the in-page viewer needs to draw the document itself.
     *
     * Handing the file to an <object> leaves the rendering — and the whole
     * toolbar with it — to the browser, which is why paging looked like Chrome
     * and not like Learnova. Instead the pages are rendered onto canvases by
     * PDF.js (vendored in js/pdfjs), inside our own chrome.
     *
     * The file URL is the module's own pluginfile URL, so access control,
     * revisions and byte-serving are unchanged: the viewer reads exactly what
     * core's <object> would have read, over the same session.
     *
     * @param cm_info $cm
     * @param stdClass $resource The resource instance record.
     * @param \stored_file $file The file being shown.
     * @return array
     */
    private static function pdf(cm_info $cm, stdClass $resource, \stored_file $file): array {
        $path = '/' . context_module::instance($cm->id)->id . '/mod_resource/content/'
            . $resource->revision . $file->get_filepath() . $file->get_filename();

        // Bust the browser cache when the library itself is replaced. Not on the
        // font directory: PDF.js appends a filename to it, so a query string
        // there would land in the middle of the URL it builds.
        $rev = (int) get_config('theme_learnovatheme', 'version');
        $asset = function(string $name) use ($rev): string {
            return (new moodle_url('/theme/learnovatheme/js/pdfjs/' . $name, ['v' => $rev]))->out(false);
        };

        return [
            'url'         => moodle_url::make_file_url('/pluginfile.php', $path, false)->out(false),
            'downloadurl' => moodle_url::make_file_url('/pluginfile.php', $path, true)->out(false),
            'filename'    => $file->get_filename(),
            'liburl'      => $asset('pdf.min.mjs'),
            'workerurl'   => $asset('pdf.worker.min.mjs'),
            // Trailing slash matters — PDF.js concatenates a filename onto it.
            'fontsurl'    => (new moodle_url('/theme/learnovatheme/js/pdfjs/standard_fonts/'))->out(false),
        ];
    }

    /**
     * SCORM: our own entry panel, and the player framed on demand.
     *
     * mod_scorm runs the SCO in player.php under the chrome-less "embedded"
     * layout, so it can never appear in this layout directly. Framing that page
     * keeps the focus chrome around it while leaving the player itself entirely
     * untouched — it still owns the API, the tracking and the navigation, and
     * the SCO's parent is still the player window, so API discovery is
     * unchanged.
     *
     * The frame is only built once the learner asks for it. Loading player.php
     * writes an x.start.time track, so embedding it on arrival would start an
     * attempt for anyone who merely opened the activity; the entry panel keeps
     * that behind Enter, exactly as the module's own launch form does.
     *
     * @param cm_info $cm
     * @return array Empty when the module's own page should stand.
     */
    private static function scorm(cm_info $cm): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        // The delete-attempts flow is the module's, confirmation page included.
        if (optional_param('action', '', PARAM_ALPHA) !== '') {
            return [];
        }

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance]);
        if (!$scorm) {
            return [];
        }
        // A popup SCORM is a deliberate choice to open elsewhere; honour it.
        if (!empty($scorm->popup)) {
            return [];
        }
        list($available) = scorm_get_availability_status($scorm);
        if (!$available) {
            return [];
        }
        // Same guard mod_scorm applies before loading the datamodel.
        $version = strtolower(clean_param($scorm->version, PARAM_SAFEDIR));
        if (!file_exists($CFG->dirroot . '/mod/scorm/datamodels/' . $version . 'lib.php')) {
            return [];
        }
        require_once($CFG->dirroot . '/mod/scorm/datamodels/' . $version . 'lib.php');

        // Resume where the learner left off unless the package forces a restart.
        $toc = scorm_get_toc($USER, $scorm, $cm->id, TOCFULLURL, '');
        $launchsco = (!empty($toc->sco->id) && $scorm->forcenewattempt != SCORM_FORCEATTEMPT_ALWAYS)
            ? $toc->sco->id
            : $scorm->launch;
        if (empty($launchsco)) {
            return [];
        }

        $viewurl = new moodle_url('/mod/scorm/view.php', ['id' => $cm->id]);
        $mode = optional_param('fmplay', '', PARAM_ALPHA);

        // Frame proportions follow the activity's own stage size when it is set
        // in pixels. Packages built to a fixed stage do not scale to their
        // container, so a frame of the wrong shape just surrounds the content
        // with the package's background colour. Percentage widths (the default
        // 100) carry no ratio, so those fall back to 16:9.
        // A package built to a fixed stage cannot grow into its container, so
        // when the activity states that stage in pixels we render the frame at
        // exactly that size and scale it up to fit — the same result a video
        // gives, without asking the package to do anything it cannot do.
        $stagew = (int) $scorm->width;
        $stageh = (int) $scorm->height;
        // Width <= 100 is a percentage (the default is 100), which carries no
        // pixel size and therefore nothing to scale from. Rather than make every
        // teacher fill it in, look the real stage up in the package itself.
        $hasstage = $stagew > 100 && $stageh > 0;
        if (!$hasstage) {
            $detected = self::scorm_package_stage($cm, $scorm);
            if ($detected !== null) {
                [$stagew, $stageh] = $detected;
                $hasstage = true;
            }
        }
        $ratio = $hasstage ? round($stagew / $stageh, 4) : null;

        // Playing: hand the whole area over to the framed player.
        if ($mode === 'normal' || $mode === 'browse') {
            return [
                'scormplaying' => true,
                'scormbrowse'  => $mode === 'browse',
                // display=popup is how mod_scorm is told it is being shown
                // without page chrome: player.php switches to the "embedded"
                // layout and drops its own exit bar, which the frame below
                // replaces. Without it the player renders a full themed page
                // — navbar and all — inside the frame.
                'scormurl'     => (new moodle_url('/mod/scorm/player.php', [
                    'cm'      => $cm->id,
                    'scoid'   => $launchsco,
                    'mode'    => $mode,
                    'display' => 'popup',
                ]))->out(false),
                'scormexiturl' => $viewurl->out(false),
                'scormratio'    => $ratio,
                'hasscormratio' => $ratio !== null,
                'scormstagew'   => $hasstage ? $stagew : null,
                'scormstageh'   => $hasstage ? $stageh : null,
                'hasscormstage' => $hasstage,
            ];
        }

        return ['scormplaying' => false, 'scormentry' => self::scorm_entry($cm, $scorm, $toc, $viewurl)];
    }

    /**
     * The stage a SCORM package was authored at, read from the package itself.
     *
     * Authoring tools write the stage into the launch file as a fixed pixel box
     * and, in the exports seen so far, ship no code to scale it. The activity's
     * own "stage size" setting is meant to carry those numbers but defaults to a
     * percentage, so reading them here means the frame fits the package without
     * anyone having to transcribe them.
     *
     * Only the head of the file is read: the declaration sits in the opening
     * markup, and these files run to tens of kilobytes.
     *
     * @param cm_info $cm
     * @param stdClass $scorm
     * @return array|null [width, height], or null when nothing was declared.
     */
    private static function scorm_package_stage(cm_info $cm, stdClass $scorm): ?array {
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION,
            'theme_learnovatheme', 'scormstage');
        $key = $cm->id . '_' . $scorm->revision;
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $stage = null;
        $files = get_file_storage()->get_area_files(
            context_module::instance($cm->id)->id, 'mod_scorm', 'content', 0, 'filepath, filename', false);
        foreach ($files as $file) {
            if (substr($file->get_filename(), -5) !== '.html') {
                continue;
            }
            $handle = $file->get_content_file_handle();
            $head = $handle ? fread($handle, 65536) : '';
            if ($handle) {
                fclose($handle);
            }
            if (preg_match("/id='project'[^>]*style='width:(\\d+)px\\s*;height:(\\d+)px/", $head, $m)) {
                $stage = [(int) $m[1], (int) $m[2]];
                break;
            }
        }

        $cache->set($key, $stage ?: false);
        return $stage;
    }

    /**
     * Entry panel data: what the learner needs before starting, in our own
     * components rather than mod_scorm's stacked paragraphs.
     *
     * Every figure is read through the module's own helpers, so the panel says
     * the same thing its native page would.
     *
     * @param cm_info $cm
     * @param stdClass $scorm
     * @param stdClass $toc Result of scorm_get_toc().
     * @param moodle_url $viewurl
     * @return array
     */
    private static function scorm_entry(cm_info $cm, stdClass $scorm, stdClass $toc, moodle_url $viewurl): array {
        global $DB, $USER;

        $attempts = scorm_get_attempt_count($USER->id, $scorm, true);
        $attemptcount = empty($attempts) ? 0 : count($attempts);

        static $grademethods = [
            GRADESCOES   => 'gradescoes',
            GRADEHIGHEST => 'gradehighest',
            '2'          => 'gradeaverage',
            '3'          => 'gradesum',
        ];
        $grademethod = get_string($grademethods[(string) $scorm->grademethod] ?? 'gradehighest', 'scorm');

        $reported = get_string('none');
        if (!empty($attempts)) {
            $grade = scorm_grade_user($scorm, $USER->id);
            if ($scorm->grademethod !== GRADESCOES && !empty($scorm->maxgrade)) {
                $grade = number_format(($grade / $scorm->maxgrade) * 100, 0) . '%';
            }
            $reported = $grade;
        }

        $exceeded = $scorm->maxattempt > 0 && $attemptcount >= $scorm->maxattempt;
        // The module allows another run while the lock is off or attempts remain.
        $canenter = empty($scorm->lastattemptlock) || !empty($toc->attemptleft);

        $context = context_module::instance($cm->id);
        $candelete = has_capability('mod/scorm:deleteownresponses', $context)
            && $DB->record_exists('scorm_attempt', ['userid' => $USER->id, 'scormid' => $scorm->id]);

        return [
            'allowed'      => $scorm->maxattempt > 0 ? $scorm->maxattempt : get_string('unlimited'),
            'made'         => $attemptcount,
            'grademethod'  => $grademethod,
            'reported'     => $reported,
            'exceeded'     => $exceeded,
            'canenter'     => $canenter,
            'canpreview'   => empty($scorm->hidebrowse),
            'enterurl'     => (new moodle_url($viewurl, ['fmplay' => 'normal']))->out(false),
            'previewurl'   => (new moodle_url($viewurl, ['fmplay' => 'browse']))->out(false),
            'candelete'    => $candelete,
            'deleteurl'    => (new moodle_url($viewurl, ['action' => 'delete', 'sesskey' => sesskey()]))->out(false),
        ];
    }

    /**
     * Book: current chapter label, prev/next chapter links and the print action.
     *
     * A book is a sequence of chapters, optionally two levels deep — a chapter
     * with subchapter = 1 hangs off the one before it. Ordering, the parent
     * relationship and the visibility of hidden chapters all come from
     * mod_book's own helper rather than being re-derived here; only the chapter
     * label is built locally, so the numbering prefix stays out of the nav.
     *
     * @param moodle_page $page
     * @param cm_info $cm
     * @return array
     */
    private static function book(moodle_page $page, cm_info $cm): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $context = context_module::instance($cm->id);
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $canseehidden = has_capability('mod/book:viewhiddenchapters', $context);

        // Gives each chapter its ->number and ->parent, exactly as the module
        // sees them; book_get_chapter_title() needs that shape.
        $chapters = book_preload_chapters($book);
        if (empty($chapters)) {
            return ['has' => false];
        }

        $visible = [];
        foreach ($chapters as $chapter) {
            if ($chapter->hidden && !$canseehidden) {
                continue;
            }
            $visible[] = $chapter;
        }
        if (empty($visible)) {
            return ['has' => false];
        }

        // The chapter being read; the module defaults to the first one.
        $currentid = (int) $page->url->param('chapterid');
        $index = 0;
        foreach ($visible as $i => $chapter) {
            if ((int) $chapter->id === $currentid) {
                $index = $i;
                break;
            }
        }
        $current = $visible[$index];

        $cmid = (int) $cm->id;
        $chapterurl = function(stdClass $chapter) use ($cmid): string {
            return (new moodle_url('/mod/book/view.php', [
                'id' => $cmid,
                'chapterid' => $chapter->id,
            ]))->out(false);
        };

        // Titles are taken raw rather than through book_get_chapter_title(),
        // which prefixes "1." / "2.1." when the book's numbering style is set to
        // numbers. Focus mode shows the chapter name on its own; position is
        // already carried by the prev/next arrows, so the counter is noise here.
        $title = function(stdClass $chapter) use ($context): string {
            return format_string($chapter->title, true, ['context' => $context]);
        };

        // A subchapter reads out of context on its own, so carry its parent's
        // name alongside it — that is what the module's own heading pair does.
        $parenttitle = '';
        if (!empty($current->subchapter) && !empty($current->parent)
                && isset($chapters[$current->parent])) {
            $parenttitle = $title($chapters[$current->parent]);
        }

        $data = [
            'has'         => true,
            'title'       => $title($current),
            'hasparent'   => $parenttitle !== '',
            'parenttitle' => $parenttitle,
            'hasprev'     => $index > 0,
            'hasnext'     => $index < count($visible) - 1,
            'prevurl'     => $index > 0 ? $chapterurl($visible[$index - 1]) : '',
            'nexturl'     => $index < count($visible) - 1 ? $chapterurl($visible[$index + 1]) : '',
            // With custom titles off the module prints the chapter heading into
            // its own content, which would repeat what the nav row already says.
            // The template flags it so the SCSS can suppress just that case.
            'nativetitles' => empty($book->customtitles),
        ];

        // The book's own table of contents. mod_book prints one under the
        // content (#mod_book-chaptersnavigation), which focus mode hides; this
        // is the same list rebuilt as a menu hanging off the chapter label, so
        // the reader can jump instead of stepping chapter by chapter. A
        // one-chapter book has nothing to jump between, so it keeps the plain
        // label. Hidden chapters are already filtered above for everyone
        // without the capability; those that survive are flagged so staff can
        // tell them apart, the way the module's own list dims them.
        $toc = [];
        foreach ($visible as $i => $chapter) {
            $toc[] = [
                'title'     => $title($chapter),
                'url'       => $chapterurl($chapter),
                'iscurrent' => $i === $index,
                'issub'     => !empty($chapter->subchapter),
                'ishidden'  => !empty($chapter->hidden),
            ];
        }
        $data['haschapters'] = count($toc) > 1;
        $data['chapters'] = $toc;

        // Print is a subplugin — only offer it when installed and permitted.
        $data['hasprint'] = false;
        if (get_config('booktool_print', 'version') && has_capability('booktool/print:print', $context)) {
            $data['hasprint'] = true;
            $data['printurl'] = (new moodle_url('/mod/book/tool/print/index.php', [
                'id' => $cm->id,
                'chapterid' => $current->id,
            ]))->out(false);
        }

        return $data;
    }

    /**
     * Assignment: the dates banner every learner sees, plus a grading summary
     * for staff. The submission form itself stays native — reimplementing it
     * would mean reimplementing drafts, attempts, group submissions and
     * plagiarism hooks, so it is only restyled inside the card.
     *
     * @param moodle_page $page
     * @param cm_info $cm
     * @return array
     */
    private static function assign(moodle_page $page, cm_info $cm): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $context = context_module::instance($cm->id);
        $instance = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $now = time();

        $data = [
            'hasopened'    => !empty($instance->allowsubmissionsfromdate),
            'opened'       => !empty($instance->allowsubmissionsfromdate)
                ? userdate($instance->allowsubmissionsfromdate, get_string('strftimedatefullshort'))
                : '',
            'hasdue'       => !empty($instance->duedate),
            'due'          => !empty($instance->duedate)
                ? userdate($instance->duedate, get_string('strftimedatetimeshort'))
                : '',
            'hasremaining' => false,
            'remaining'    => '',
            'isoverdue'    => false,
        ];

        if (!empty($instance->duedate)) {
            $data['hasremaining'] = true;
            if ($instance->duedate > $now) {
                $data['remaining'] = format_time($instance->duedate - $now);
            } else {
                $data['isoverdue'] = true;
                $data['remaining'] = format_time($now - $instance->duedate);
            }
        }

        $assign = new \assign($context, $cm, $cm->get_course());

        // Staff banner — submission counts straight off mod_assign's own API.
        $data['isstaff'] = has_capability('mod/assign:grade', $context);
        if ($data['isstaff']) {
            $data['submitted'] = $assign->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED);
            $data['needgrading'] = $assign->count_submissions_need_grading();
            $data['participants'] = $assign->count_participants(0);
            // Grading lives in Course Management, not here — this is only a link out.
            $data['gradingurl'] = (new moodle_url('/mod/assign/view.php', [
                'id' => $cm->id,
                'action' => 'grading',
            ]))->out(false);
        }

        $data += self::assign_submission_form($assign, $instance);

        return $data;
    }

    /**
     * The inline "Add submission" block.
     *
     * mod_assign's own submission form, rendered straight into the reading card
     * so the learner writes and saves without leaving the page. Nothing about
     * saving changes: the form still carries its own hidden action, id and
     * sesskey, so the POST lands in mod_assign exactly as it would from the
     * module's own edit page — validation, submission plugins, events and
     * grading hooks all stay where they belong.
     *
     * Configurations whose flow the block cannot represent keep the module's
     * own button instead, rather than being quietly broken by an inline form
     * that skips a step they depend on.
     *
     * @param \assign $assign
     * @param stdClass $instance The assign instance record.
     * @return array
     */
    private static function assign_submission_form(\assign $assign, stdClass $instance): array {
        global $CFG, $USER;

        // Team submissions confirm against a group, blind marking swaps the
        // identity, and a time limit has to start its own timer first.
        $caninline = empty($instance->teamsubmission)
            && empty($instance->blindmarking)
            && empty($instance->timelimit);

        if (!$caninline
                || !$assign->submissions_open($USER->id)
                || !$assign->can_edit_submission($USER->id, $USER->id)) {
            return ['hassubmissionform' => false];
        }

        require_once($CFG->dirroot . '/mod/assign/submission_form.php');

        // Same two lines mod_assign uses on its own edit page.
        $formdata = new stdClass();
        $formdata->userid = $USER->id;
        $mform = new \mod_assign_submission_form(null, [$assign, $formdata]);

        return [
            'hassubmissionform' => true,
            'submissionform'    => $mform->render(),
        ];
    }

    /**
     * Forum: the discussion list as cards, or null to keep Moodle's own render.
     *
     * The card list deliberately covers only plain forums. Group mode, pinned
     * discussions, Q&A/blog/single types and long lists all carry behaviour the
     * cards cannot show, and silently dropping any of it would be worse than
     * looking less like the mockup — those fall through to the native list.
     *
     * @param moodle_page $page
     * @param cm_info $cm
     * @return array|null
     */
    private static function forum(moodle_page $page, cm_info $cm): ?array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        // The single-thread page renders posts, not a list.
        if ($page->pagetype !== 'mod-forum-view') {
            return null;
        }

        $instance = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        if (!in_array($instance->type, self::CARD_FORUM_TYPES, true)) {
            return null;
        }
        if (groups_get_activity_groupmode($cm) != NOGROUPS) {
            return null;
        }

        $context = context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context)) {
            return null;
        }

        // Pinned discussions carry an ordering the flat card list would lose.
        if ($DB->record_exists('forum_discussions', ['forum' => $instance->id, 'pinned' => FORUM_DISCUSSION_PINNED])) {
            return null;
        }

        $records = forum_get_discussions($cm, 'd.timemodified DESC', true, -1, self::MAX_CARD_THREADS + 1);
        if (count($records) > self::MAX_CARD_THREADS) {
            // Long lists need the native pagination.
            return null;
        }

        $replycounts = forum_count_discussion_replies($instance->id);
        $candelete = has_capability('mod/forum:deleteanypost', $context);

        $threads = [];
        foreach ($records as $record) {
            // forum_get_discussions() selects the full name-field set off the
            // discussion starter, so the row is already a valid fullname() input.
            $replies = isset($replycounts[$record->discussion]) ? (int) $replycounts[$record->discussion]->replies : 0;
            $author = fullname($record);

            $threads[] = [
                'id'       => (int) $record->discussion,
                'name'     => format_string($record->name, true, ['context' => $context]),
                'url'      => (new moodle_url('/mod/forum/discuss.php', ['d' => $record->discussion]))->out(false),
                'snippet'  => shorten_text(trim(preg_replace('/\s+/u', ' ',
                    html_to_text(format_text($record->message, $record->messageformat,
                        ['context' => $context, 'para' => false]), 0, false))), 180),
                'replies'  => $replies,
                'replylabel' => self::reply_label($replies),
                'meta'     => get_string('fm_thread_meta', 'theme_learnovatheme', (object) [
                    'author' => $author,
                    'time'   => userdate($record->timemodified, get_string('strftimedatefullshort')),
                ]),
                'candelete' => $candelete,
            ];
        }

        return [
            'cmid'      => (int) $cm->id,
            'threads'   => $threads,
            'hasthreads' => !empty($threads),
            'candelete' => $candelete,
            // Posting rules (news forums, cut-off dates, guests) are the module's
            // to decide, so ask it rather than checking a capability by hand.
            'canstart'  => forum_user_can_post_discussion($instance, null, -1, $cm, $context),
            'createurl' => (new moodle_url('/mod/forum/post.php', ['forum' => $instance->id]))->out(false),
        ];
    }

    /**
     * Reply count as a phrase, so the template does not have to branch on 0/1/n.
     *
     * @param int $replies
     * @return string
     */
    private static function reply_label(int $replies): string {
        if ($replies === 0) {
            return get_string('fm_thread_noreply', 'theme_learnovatheme');
        }
        if ($replies === 1) {
            return get_string('fm_thread_reply', 'theme_learnovatheme');
        }
        return get_string('fm_thread_replies', 'theme_learnovatheme', $replies);
    }
}
