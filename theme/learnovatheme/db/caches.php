<?php
// This file is part of the Learnova theme.

/**
 * Cache definitions for theme_learnovatheme.
 *
 * @package    theme_learnovatheme
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Stage size read out of a SCORM package's launch file, so focus mode can
    // size its frame to the package. Keyed by cmid + package revision, so a
    // re-uploaded package is re-read rather than served stale.
    'scormstage' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
    ],
];
