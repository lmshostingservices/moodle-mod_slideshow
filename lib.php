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
 * Library of functions for the slideshow module.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Get ordered image slides for a slideshow — by stored_file::sortorder, id.
 *
 * @param int $slideshowid
 * @param int $contextid
 * @return stored_file[]
 */
function slideshow_get_ordered_slides($slideshowid, $contextid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_slideshow', 'slideimages', $slideshowid, 'sortorder, id', false);
    return $files ?: [];
}

/**
 * Apply an ordered list of identifiers to the slideshow filearea and extra slides table.
 *
 * Accepts a mixed array containing:
 *   - pathnamehash or filename strings for image files
 *   - "extra_NNN" strings for extra slides (video/poster)
 *
 * Each item's position in the array becomes its new sortorder.
 * Unmentioned image files are appended preserving current order.
 *
 * @param int   $contextid   Module context id
 * @param int   $slideshowid Slideshow instance id (filearea itemid)
 * @param array $ids         Ordered list of keys
 */
function slideshow_apply_order_ids(int $contextid, int $slideshowid, array $ids): void {
    global $DB;
    $fs = get_file_storage();

    // Separate extra_NNN keys from file keys, preserving full-array positions
    $extraUpdates  = []; // extraId => position_in_full_array
    $fileIdsByPos  = []; // position_in_full_array => key (hash or filename)
    foreach ($ids as $pos => $id) {
        if (!is_string($id) || $id === '') {
            continue;
        }
        if (strpos($id, 'extra_') === 0) {
            $extraId = (int)substr($id, 6);
            if ($extraId > 0) {
                $extraUpdates[$extraId] = $pos;
            }
        } else {
            $fileIdsByPos[$pos] = $id;
        }
    }

    // Update extra slide sortorders
    if (!empty($extraUpdates)) {
        foreach ($extraUpdates as $extraId => $sortorder) {
            $DB->set_field('slideshow_extra_slides', 'sortorder', $sortorder,
                ['id' => $extraId, 'slideshowid' => $slideshowid]);
        }
    }

    // Get all image files
    $files = $fs->get_area_files($contextid, 'mod_slideshow', 'slideimages', $slideshowid, 'sortorder, id', false);
    if (empty($files)) {
        return;
    }

    $byhash = [];
    $byname = [];
    foreach ($files as $f) {
        $byhash[$f->get_pathnamehash()] = $f;
        $byname[$f->get_filename()][]   = $f;
    }

    $seen          = [];
    $positionedFiles = []; // full-array position => stored_file
    foreach ($fileIdsByPos as $pos => $id) {
        if (isset($byhash[$id])) {
            $sf = $byhash[$id];
            $h  = $sf->get_pathnamehash();
            if (!isset($seen[$h])) {
                $positionedFiles[$pos] = $sf;
                $seen[$h] = true;
            }
        } else if (isset($byname[$id]) && !empty($byname[$id])) {
            foreach ($byname[$id] as $sf) {
                $h = $sf->get_pathnamehash();
                if (!isset($seen[$h])) {
                    $positionedFiles[$pos] = $sf;
                    $seen[$h] = true;
                    break;
                }
            }
        }
    }

    // Append unmentioned files at the end
    $nextPos = count($ids);
    foreach ($files as $f) {
        $h = $f->get_pathnamehash();
        if (!isset($seen[$h])) {
            $positionedFiles[$nextPos++] = $f;
            $seen[$h] = true;
        }
    }

    // Apply sortorders using full-array positions
    $transaction = $DB->start_delegated_transaction();
    foreach ($positionedFiles as $pos => $sf) {
        if ((int)$sf->get_sortorder() !== $pos) {
            $sf->set_sortorder($pos);
        }
    }
    $transaction->allow_commit();
}

/**
 * Return the next available sortorder for a new slide (max + 1 across all slide types).
 *
 * @param int $slideshowid
 * @param int $contextid
 * @return int
 */
function slideshow_get_next_sortorder(int $slideshowid, int $contextid): int {
    global $DB;
    $fs = get_file_storage();

    $maxfile = -1;
    $files = $fs->get_area_files($contextid, 'mod_slideshow', 'slideimages', $slideshowid, 'sortorder DESC', false);
    if (!empty($files)) {
        $first   = reset($files);
        $maxfile = (int)$first->get_sortorder();
    }

    $maxextra = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(sortorder), -1) FROM {slideshow_extra_slides} WHERE slideshowid = :sid',
        ['sid' => $slideshowid]
    );

    return max($maxfile, $maxextra) + 1;
}

/**
 * Shift all slides (image files + extra slides) at sortorder >= $atposition up by 1.
 * Call before inserting a new slide at $atposition.
 *
 * @param int $slideshowid
 * @param int $contextid
 * @param int $atposition   Slides at this sortorder and above get incremented
 */
function slideshow_shift_slides_up(int $slideshowid, int $contextid, int $atposition): void {
    global $DB;
    $fs = get_file_storage();

    // Shift image files
    $files = $fs->get_area_files($contextid, 'mod_slideshow', 'slideimages', $slideshowid, 'sortorder DESC, id', false);
    if (!empty($files)) {
        $tx = $DB->start_delegated_transaction();
        foreach ($files as $f) {
            if ((int)$f->get_sortorder() >= $atposition) {
                $f->set_sortorder((int)$f->get_sortorder() + 1);
            }
        }
        $tx->allow_commit();
    }

    // Shift extra slides
    $DB->execute(
        'UPDATE {slideshow_extra_slides} SET sortorder = sortorder + 1 WHERE slideshowid = :sid AND sortorder >= :pos',
        ['sid' => $slideshowid, 'pos' => $atposition]
    );
}

/**
 * Convert a YouTube watch/short URL to an embeddable URL with JS API enabled.
 * Returns '' if the URL cannot be parsed.
 *
 * @param string $url
 * @return string
 */
function slideshow_youtube_embed_url(string $url): string {
    $id = '';
    if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
    } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
    } elseif (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
    } elseif (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id = $m[1];
    }
    if (empty($id)) {
        return '';
    }
    return 'https://www.youtube.com/embed/' . $id . '?enablejsapi=1&rel=0';
}

/**
 * Return a MIME type for a video filename based on extension.
 *
 * @param string $filename
 * @return string
 */
function slideshow_video_mime_type(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = [
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogg'  => 'video/ogg',
        'ogv'  => 'video/ogg',
        'mov'  => 'video/quicktime',
    ];
    return $types[$ext] ?? 'video/mp4';
}


function slideshow_add_instance(stdClass $slideshow, $mform) {
    global $DB;

    $slideshow->timemodified = time();

    $slideshow->id = $DB->insert_record('slideshow', $slideshow);

    $context = \context_module::instance($slideshow->coursemodule);

    file_save_draft_area_files(
        $slideshow->slideimages,
        $context->id,
        'mod_slideshow',
        'slideimages',
        $slideshow->id,
        ['subdirs' => 0, 'maxfiles' => -1, 'preservetimestamps' => true]
    );

    $ids = [];
    if (!empty($slideshow->orderjson)) {
        $raw = json_decode($slideshow->orderjson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($raw)) {
            foreach ($raw as $entry) {
                if (is_array($entry) && !empty($entry['filename'])) {
                    $ids[] = (string)$entry['filename'];
                } else if (is_string($entry)) {
                    $ids[] = $entry;
                }
            }
        }
    }

    if (empty($ids) && !empty($slideshow->slideseq) && is_array($slideshow->slideseq)) {
        asort($slideshow->slideseq, SORT_NUMERIC);
        $ids = array_keys($slideshow->slideseq);
    }

    if (!empty($ids)) {
        slideshow_apply_order_ids($context->id, $slideshow->id, $ids);
    }

    return $slideshow->id;
}


function slideshow_update_instance(stdClass $slideshow, $mform) {
    global $DB;
    $slideshow->timemodified = time();
    $slideshow->id = (int)$slideshow->instance;

    $context = \context_module::instance($slideshow->coursemodule);
    $fs = get_file_storage();

    $beforefiles = $fs->get_area_files(
        $context->id, 'mod_slideshow', 'slideimages', $slideshow->id, 'sortorder, id', false
    );
    $ajaxorder = array_map(static fn($f) => $f->get_filename(), $beforefiles);

    file_save_draft_area_files(
        $slideshow->slideimages,
        $context->id,
        'mod_slideshow',
        'slideimages',
        $slideshow->id,
        ['subdirs' => 0, 'maxfiles' => -1, 'preservetimestamps' => true]
    );

    $seq = [];
    if (!empty($slideshow->orderjson)) {
        $decoded = json_decode($slideshow->orderjson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $k) { if (is_string($k) && $k !== '') $seq[] = $k; }
        }
    }
    if (empty($seq) && !empty($ajaxorder)) {
        $seq = $ajaxorder;
    }

    if (!empty($seq)) {
        slideshow_apply_order_ids($context->id, $slideshow->id, $seq);
    }

    $DB->update_record('slideshow', $slideshow);
    return true;
}


function slideshow_delete_instance($id) {
    global $DB;
    if (!$slideshow = $DB->get_record('slideshow', ['id' => $id])) {
        return false;
    }
    $cm = get_coursemodule_from_instance('slideshow', $id, $slideshow->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();

    // Delete image and voiceover files
    $fs->delete_area_files($context->id, 'mod_slideshow', 'slideimages', $id);
    $fs->delete_area_files($context->id, 'mod_slideshow', 'slidevoiceovers', $id);

    // Delete extra slide associated files
    $extraslides = $DB->get_records('slideshow_extra_slides', ['slideshowid' => $id]);
    foreach ($extraslides as $extra) {
        $fs->delete_area_files($context->id, 'mod_slideshow', 'slideposterimages', $extra->id);
        $fs->delete_area_files($context->id, 'mod_slideshow', 'slidevideofiles', $extra->id);
    }

    $DB->delete_records('slideshow_extra_slides', ['slideshowid' => $id]);
    $DB->delete_records('slideshow_voiceovers', ['slideshowid' => $id]);
    $DB->delete_records('slideshow_completion', ['instanceid' => $id]);
    $DB->delete_records('slideshow', ['id' => $id]);
    return true;
}

function slideshow_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    require_login();

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $allowed = ['slideimages', 'slidevoiceovers', 'slideposterimages', 'slidevideofiles'];
    if (!in_array($filearea, $allowed)) {
        return false;
    }

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_slideshow', $filearea, $args[0], '/', $args[1]);

    if (!$file) {
        return false;
    }

    // For videos, allow byte-range requests so seeking works in browsers
    if ($filearea === 'slidevideofiles') {
        send_stored_file($file, 0, 0, false, $options);
    } else {
        send_stored_file($file, 0, 0, $forcedownload, $options);
    }
}

/**
 * List of features supported in slideshow module
 */
function slideshow_supports($feature) {
    if (defined('FEATURE_MOD_PURPOSE') && $feature === FEATURE_MOD_PURPOSE) {
        return MOD_PURPOSE_CONTENT;
    }

    switch ($feature) {
        case FEATURE_GROUPS:                    return false;
        case FEATURE_GROUPINGS:                 return false;
        case FEATURE_MOD_INTRO:                 return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:   return false;
        case FEATURE_COMPLETION_HAS_RULES:      return true;
        case FEATURE_GRADE_HAS_GRADE:           return false;
        case FEATURE_GRADE_OUTCOMES:            return false;
        case FEATURE_BACKUP_MOODLE2:            return true;
        case FEATURE_SHOW_DESCRIPTION:          return true;
        default:                                return null;
    }
}

function slideshow_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, containerheight, completionallslides, enablevoiceover, requirevoiceover, timemodified';
    if (!$slideshow = $DB->get_record('slideshow', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $slideshow->name;

    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('slideshow', $slideshow, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionallslides'] = $slideshow->completionallslides;
    }
    return $result;
}

function mod_slideshow_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']['completionallslides'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    if (!empty($cm->customdata['customcompletionrules']['completionallslides'])) {
        $descriptions[] = get_string('completionallslides', 'slideshow');
    }
    return $descriptions;
}

function slideshow_get_completion_state($course, $cm, $userid, $type, $slideshow = null, $completion = null, $modinfo = null) {
    global $DB;

    if ($slideshow === null) {
        $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    if (empty($slideshow->completionallslides)) {
        return $type;
    }

    $progress = $DB->get_record('slideshow_completion',
        ['instanceid' => $slideshow->id, 'userid' => $userid],
        'viewed, slidecount, completion', IGNORE_MISSING);

    if (!$progress) {
        return false;
    }

    $complete = ((int)$progress->completion === 1)
        || ((int)$progress->slidecount > 0 && (int)$progress->viewed >= (int)$progress->slidecount);

    return $complete;
}

function mod_slideshow_core_calendar_provide_event_action(calendar_event $event,
        \core_calendar\action_factory $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['slideshow'][$event->instance];

    $completion = new \completion_info($cm->get_course());
    $cdata = $completion->get_data($cm, false, $userid);

    if ((int)$cdata->completionstate !== COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/slideshow/view.php', ['id' => $cm->id]),
        1,
        true
    );
}
