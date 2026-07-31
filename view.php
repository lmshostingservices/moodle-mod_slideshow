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
 * Slideshow v1.6.26 - Main view page
 * Supports image slides, video slides (YouTube / direct URL) and poster image slides.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 */

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);

if (!$cm = get_coursemodule_from_id('slideshow', $id)) {
    throw new moodle_exception('invalidcoursemodule', 'slideshow');
}

if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
    throw new moodle_exception('invalidcourse', 'core');
}

$slideshow = $DB->get_record('slideshow', ['id' => $cm->instance]);
if (!$slideshow) {
    throw new moodle_exception('moduleinstancemissing', 'slideshow');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/slideshow:view', $context);

$completion = new completion_info($course);

$viewed_count = 0;
if ($completiondata = $DB->get_record('slideshow_completion', ['instanceid' => $slideshow->id, 'userid' => $USER->id])) {
    $viewed_count = (int)$completiondata->viewed;
}

$completionenabled = ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && $slideshow->completionallslides);

$fs = get_file_storage();

// ---- IMAGE FILES ----
$imagefiles = $fs->get_area_files(
    $context->id, 'mod_slideshow', 'slideimages', $slideshow->id, 'sortorder, id', false
);

// ---- VOICEOVERS ----
$voiceovers = [];
if (!empty($slideshow->enablevoiceover)) {
    $vorecords = $DB->get_records('slideshow_voiceovers', ['slideshowid' => $slideshow->id]);
    foreach ($vorecords as $vor) {
        $voiceovers[$vor->filename] = $vor;
    }
}

// ---- EXTRA SLIDES (video + poster) ----
$extraslides_db = $DB->get_records('slideshow_extra_slides', ['slideshowid' => $slideshow->id], 'sortorder, id');

// ---- MERGE ALL SLIDES into one list sorted by sortorder ----
$allslides_raw = [];
foreach ($imagefiles as $file) {
    $allslides_raw[] = (object)[
        'type'      => 'imagefile',
        'sortorder' => (int)$file->get_sortorder(),
        'data'      => $file,
    ];
}
foreach ($extraslides_db as $extra) {
    $allslides_raw[] = (object)[
        'type'      => 'extra',
        'sortorder' => (int)$extra->sortorder,
        'data'      => $extra,
    ];
}
usort($allslides_raw, function ($a, $b) {
    if ($a->sortorder !== $b->sortorder) {
        return $a->sortorder - $b->sortorder;
    }
    // Tiebreaker: image files before extra slides
    return ($a->type === 'imagefile') ? -1 : 1;
});

// ---- BUILD UNIFIED SLIDES ARRAY ----
$slides        = [];
$slideindex    = 0;
$imageslidecount = 0;

foreach ($allslides_raw as $slideraw) {

    // --- Image file slide ---
    if ($slideraw->type === 'imagefile') {
        $file = $slideraw->data;
        $imageslidecount++;

        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename()
        );
        $fname = $file->get_filename();

        $slidedata = (object)[
            'itemid'          => 'slide_item_' . $slideindex . '_' . uniqid(),
            'slidetype'       => 'image',
            'isimage'         => true,
            'isposter'        => false,
            'isvideo'         => false,
            'isvideoyt'       => false,
            'isvideodirect'   => false,
            'videohaswatchreq'=> false,
            'showvoiceover'   => !empty($slideshow->enablevoiceover),
            'imageurl'        => $url->out(false),
            'alttext'         => get_string('slideimage_alt', 'slideshow') . ' - ' . $fname,
            'active'          => ($slideindex === 0),
            'index'           => $slideindex,
            'displayindex'    => $slideindex + 1,
            'isviewed'        => ($slideindex + 1) <= $viewed_count,
            'hasvoiceover'    => false,
            'audiourl'        => '',
            'audioduration'   => 0,
            'filename'        => $fname,
            'votext'          => '',
            'vostatus'        => 'none',
            'vodisabled'      => false,
            'videominwatch'   => 0,
            'videosource'     => '',
            'videourl'        => '',
            'videomimetype'   => '',
            'videoembedurl'   => '',
            'videowatchprompt'=> '',
            'videotitle'      => '',
        ];

        if (!empty($slideshow->enablevoiceover) && isset($voiceovers[$fname])) {
            $vo = $voiceovers[$fname];
            $slidedata->vostatus  = $vo->status;
            $slidedata->votext    = !empty($vo->customtext) ? $vo->customtext : ($vo->ocrtext ?? '');
            $slidedata->vodisabled = !empty($vo->voiceover_disabled);

            if (!$slidedata->vodisabled && $vo->status === 'ready') {
                $audiofile = $fs->get_file($context->id, 'mod_slideshow', 'slidevoiceovers', $slideshow->id, '/', $fname . '.ogg');
                if ($audiofile) {
                    $audiourl = \moodle_url::make_pluginfile_url(
                        $context->id, 'mod_slideshow', 'slidevoiceovers', $slideshow->id, '/', $fname . '.ogg'
                    );
                    $slidedata->hasvoiceover  = true;
                    $slidedata->audiourl      = $audiourl->out(false);
                    $slidedata->audioduration = (float)$vo->duration;
                }
            }
        }

        $slides[] = $slidedata;
        $slideindex++;
        continue;
    }

    // --- Extra slide (video or poster) ---
    $extra = $slideraw->data;

    if ($extra->slidetype === 'video') {
        $videominwatch = (int)$extra->videominwatch;
        $isyt          = ($extra->videosource === 'youtube');
        $isupload      = ($extra->videosource === 'upload');
        $isdirect      = (!$isyt);

        $embedurl   = '';
        $directurl  = '';
        $mimetype   = 'video/mp4';

        if ($isyt) {
            $embedurl = slideshow_youtube_embed_url($extra->videourl ?? '');
        } elseif ($isupload) {
            // Uploaded file stored in slidevideofiles filearea
            $vfile = null;
            if (!empty($extra->imagefilename)) {
                $vfile = $fs->get_file($context->id, 'mod_slideshow', 'slidevideofiles', $extra->id, '/', $extra->imagefilename);
            }
            if ($vfile) {
                $vurlobj  = \moodle_url::make_pluginfile_url($context->id, 'mod_slideshow', 'slidevideofiles', $extra->id, '/', $vfile->get_filename());
                $directurl = $vurlobj->out(false);
                $mimetype  = slideshow_video_mime_type($vfile->get_filename());
            }
        } else {
            // Direct external URL
            $directurl = $extra->videourl ?? '';
            // Guess mime from URL path
            $mimetype = slideshow_video_mime_type($extra->videourl ?? '');
        }

        // Watch prompt text
        if ($videominwatch === 0) {
            $watchprompt = '';
        } elseif ($videominwatch === -1) {
            $watchprompt = get_string('video_watchprompt_full', 'slideshow');
        } else {
            $watchprompt = get_string('video_watchprompt_seconds', 'slideshow', $videominwatch);
        }

        $slidedata = (object)[
            'itemid'          => 'slide_item_' . $slideindex . '_' . uniqid(),
            'slidetype'       => 'video',
            'isimage'         => false,
            'isposter'        => false,
            'isvideo'         => true,
            'isvideoyt'       => $isyt,
            'isvideodirect'   => $isdirect,
            'videohaswatchreq'=> ($videominwatch !== 0),
            'showvoiceover'   => false,
            'imageurl'        => '',
            'alttext'         => '',
            'active'          => ($slideindex === 0),
            'index'           => $slideindex,
            'displayindex'    => $slideindex + 1,
            'isviewed'        => ($slideindex + 1) <= $viewed_count,
            'hasvoiceover'    => false,
            'audiourl'        => '',
            'audioduration'   => 0,
            'filename'        => '',
            'votext'          => '',
            'vostatus'        => 'none',
            'vodisabled'      => false,
            'videominwatch'   => $videominwatch,
            'videosource'     => $extra->videosource,
            'videourl'        => $directurl,
            'videomimetype'   => $mimetype,
            'videoembedurl'   => $embedurl,
            'videowatchprompt'=> $watchprompt,
            'videotitle'      => $extra->title ?: '',
        ];

        $slides[] = $slidedata;
        $slideindex++;
        continue;
    }

    if ($extra->slidetype === 'poster') {
        $posterurl = '';
        if (!empty($extra->imagefilename)) {
            $posterfile = $fs->get_file($context->id, 'mod_slideshow', 'slideposterimages', $extra->id, '/', $extra->imagefilename);
            if ($posterfile) {
                $purlobj   = \moodle_url::make_pluginfile_url($context->id, 'mod_slideshow', 'slideposterimages', $extra->id, '/', $extra->imagefilename);
                $posterurl = $purlobj->out(false);
            }
        }

        $slidedata = (object)[
            'itemid'          => 'slide_item_' . $slideindex . '_' . uniqid(),
            'slidetype'       => 'poster',
            'isimage'         => false,
            'isposter'        => true,
            'isvideo'         => false,
            'isvideoyt'       => false,
            'isvideodirect'   => false,
            'videohaswatchreq'=> false,
            'showvoiceover'   => false,
            'imageurl'        => $posterurl,
            'alttext'         => $extra->title ?: ('Poster slide ' . ($slideindex + 1)),
            'active'          => ($slideindex === 0),
            'index'           => $slideindex,
            'displayindex'    => $slideindex + 1,
            'isviewed'        => ($slideindex + 1) <= $viewed_count,
            'hasvoiceover'    => false,
            'audiourl'        => '',
            'audioduration'   => 0,
            'filename'        => '',
            'votext'          => '',
            'vostatus'        => 'none',
            'vodisabled'      => false,
            'videominwatch'   => 0,
            'videosource'     => '',
            'videourl'        => '',
            'videomimetype'   => '',
            'videoembedurl'   => '',
            'videowatchprompt'=> '',
            'videotitle'      => $extra->title ?: '',
        ];

        $slides[] = $slidedata;
        $slideindex++;
        continue;
    }
}

// ---- SINGLE-SLIDE COMPLETION SHORTCUT ----
if (count($slides) == 1 && !$viewed_count && $completionenabled) {
    $params = ['instanceid' => $slideshow->id, 'userid' => $USER->id];
    $completionrecord = $DB->get_record('slideshow_completion', $params);

    if ($completionrecord) {
        $completionrecord->viewed      = 1;
        $completionrecord->completion  = 1;
        $completionrecord->timemodified = time();
        $DB->update_record('slideshow_completion', $completionrecord);
    } else {
        $record             = (object)$params;
        $record->viewed     = 1;
        $record->completion = 1;
        $record->slidecount = 1;
        $record->timecreated  = time();
        $record->timemodified = time();
        $DB->insert_record('slideshow_completion', $record);
    }

    if ($completion->is_enabled() && $completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }
}

// ---- ASPECT RATIO ----
$slideratio    = !empty($slideshow->slideratio) ? $slideshow->slideratio : '16:9';
$allowedratios = ['16:9', '16:10', '4:3', '3:2', '1:1', '2:1', '21:9'];
if (!in_array($slideratio, $allowedratios)) {
    $slideratio = '16:9';
}
$ratioparts  = explode(':', $slideratio);
$ratioW      = (int)$ratioparts[0];
$ratioH      = (int)$ratioparts[1];
if ($ratioW <= 0 || $ratioH <= 0) { $ratioW = 16; $ratioH = 9; }
$aspectvalue = $ratioW . ' / ' . $ratioH;

// ---- VOICEOVER STATUS ----
$canmanage = has_capability('mod/slideshow:manage', $context);

$voiceoverreadycount = 0;
if (!empty($slideshow->enablevoiceover)) {
    foreach ($voiceovers as $vo) {
        if ($vo->status === 'ready') $voiceoverreadycount++;
    }
}

// ---- TEMPLATE CONTEXT ----
$templatecontext = [
    'slides'              => $slides,
    'slidecount'          => count($slides),
    'imageslidecount'     => $imageslidecount,
    'displaynav'          => count($slides) > 1,
    'str'                 => [
        'previous' => get_string('previous', 'slideshow'),
        'next'     => get_string('next', 'slideshow'),
        'noimages' => get_string('noimages', 'slideshow'),
    ],
    'completionstatus'    => $completionenabled,
    'containerheight'     => $slideshow->containerheight ?: 0,
    'slideratio'          => $slideratio,
    'aspectvalue'         => $aspectvalue,
    'enablevoiceover'     => !empty($slideshow->enablevoiceover),
    'requirevoiceover'    => !empty($slideshow->requirevoiceover),
    'canmanage'           => $canmanage,
    'cmid'                => $cm->id,
    'voiceoverstatus'     => $slideshow->voiceoverstatus ?? 'none',
    'voiceoverreadycount' => $voiceoverreadycount,
    'voiceoverhasready'   => ($slideshow->voiceoverstatus ?? 'none') === 'ready',
];

$PAGE->set_url('/mod/slideshow/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($slideshow->name));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->requires->css('/mod/slideshow/styles.css');

$PAGE->requires->js_call_amd('mod_slideshow/completion', 'init', [
    'cmid'          => $cm->id,
    'slideinstance' => $cm->instance,
    'initialviewed' => $viewed_count,
]);

echo $OUTPUT->header();

if (empty($slides)) {
    echo $OUTPUT->notification(get_string('noimages', 'slideshow'));
} else {
    echo $OUTPUT->render_from_template('mod_slideshow/slideshow', $templatecontext);
}

echo $OUTPUT->footer();
