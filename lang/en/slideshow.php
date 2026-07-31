<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Slideshow with Voiceover';
$string['modulenameplural'] = 'AI Slideshows with Voiceover';
$string['modulename'] = 'AI Slideshow with Voiceover';
$string['modulename_help'] = 'AI Slideshow with Voiceover creates narrated slide presentations from uploaded images, PowerPoint files, YouTube videos, or direct video links, with optional AI-generated spoken narration.

Teachers upload images (PNG, JPG, JPEG, GIF, WebP, SVG) or import PowerPoint files. Video slides can embed a YouTube URL or a direct MP4/WebM link. Poster slides are standalone image slides inserted at any position. All slide types are reordered by drag-and-drop or arrow buttons in the slide manager.

Video slides support a watch requirement: None (student can skip), Full video (must watch to end), or a minimum number of seconds. The Next button is disabled until the requirement is met.

AI voiceover narration is available for image slides only and uses Google Chirp 3 HD text-to-speech.';
$string['modulename_link'] = 'mod/slideshow/view';

$string['images'] = 'Slideshow Images';
$string['images_help'] = 'Upload the images for the slideshow. You can drag and drop multiple files at once. The order of images can be managed in the file manager by dragging them. All images are assumed to be 16:9 aspect ratio.';
$string['intro'] = 'Description';

$string['slideshow:view'] = 'View slideshow';
$string['slideshow:addinstance'] = 'Add a new slideshow';
$string['slideshow:manage'] = 'Manage slideshow';

$string['activitycompleted'] = 'Activity completed';
$string['completionerrormarking'] = 'Error updating completion status';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['noimages'] = 'No images uploaded yet.';
$string['noinstances'] = 'There are no slideshows in this course yet.';
$string['slideimage_alt'] = 'Slide image';
$string['completionallslides'] = 'Student must view all slides';
$string['completionallslides_help'] = 'User should reach the end of slide to finish this activity.';
$string['invalidcoursemodule'] = 'Invalid course module id used.';
$string['moduleinstancemissing'] = 'Module instance is missing.';
$string['pluginadministration'] = 'Slideshow administration';
$string['completiondetail:reachend'] = 'Reach the end of the slideshow to complete.';
$string['displaysettings'] = 'Display Settings';
$string['slideratio'] = 'Slide aspect ratio';
$string['slideratio_help'] = 'Choose the aspect ratio that matches your slide images. The slideshow container will resize to exactly match the selected ratio. Common ratios: 16:9 (widescreen presentations), 4:3 (classic slides), 3:2 (photos), 1:1 (square images). If your images don\'t match the selected ratio, they will be fitted inside the container without cropping.';
$string['containerheight'] = 'Maximum height (optional)';
$string['containerheight_help'] = 'Set an optional maximum height in pixels to cap the slideshow size on large screens. Leave empty or 0 for no limit. The aspect ratio will still be maintained.';
$string['reorderslides'] = 'Re-order slides: {$a}';
$string['dragreorderhelp'] = 'Drag the tiles to set the order. Changes are saved when you submit.';
$string['movetotop'] = 'Move to top';
$string['movetobottom'] = 'Move to bottom';

$string['voiceoverheading'] = 'AI Voiceover Settings';
$string['enablevoiceover'] = 'Enable AI voiceover';
$string['enablevoiceover_help'] = 'When enabled, AI will read the text on each slide image aloud using Google Chirp 3 HD voices. Text is automatically extracted from slide images using OCR. Each slide voiceover costs 5 AI credits to generate.';
$string['voicelanguage'] = 'Voiceover language';
$string['voicelanguage_help'] = 'Select the language for the AI voiceover narration. The voice will speak in the selected language using Google Chirp 3 HD technology.';
$string['voicegender'] = 'Voice gender';
$string['voicegender_help'] = 'Choose whether the AI voiceover uses a male or female voice.';
$string['voicefemale'] = 'Female';
$string['voicemale'] = 'Male';
$string['voicestyle'] = 'Voice style';
$string['voicestyle_help'] = 'Choose a specific voice character for the voiceover. Each voice has a unique tone and style. Female voices: Aoede (warm), Kore (clear), Leda (gentle), Zephyr (bright). Male voices: Charon (deep), Fenrir (strong), Orus (smooth), Puck (friendly).';
$string['requirevoiceover'] = 'Require voiceover to finish';
$string['requirevoiceover_help'] = 'When enabled, students must listen to the entire voiceover narration on each slide before the Next button becomes available. This ensures students engage with the audio content.';
$string['voiceovercreditinfo'] = 'Credit usage: 5 credits per slide voiceover. This slideshow has {$a->slidecount} slides, so generating voiceovers will use {$a->totalcredits} credits total.';
$string['voiceovercreditinfo_new'] = 'Credit usage: 5 credits per slide voiceover. Credits will be charged when you save the activity with voiceover enabled.';
$string['voiceovergenerating'] = 'Generating voiceovers...';
$string['voiceoverready'] = 'Voiceovers ready';
$string['voiceovererror'] = 'Voiceover generation error';
$string['voiceoverskipped'] = 'No text found on slide';
$string['voiceoverlistening'] = 'Listen to voiceover to continue';
$string['voiceoverplaying'] = 'Playing voiceover...';
$string['voiceovercomplete'] = 'Voiceover complete';

$string['generatevoiceovers'] = 'Generate Voiceovers';
$string['regeneratevoiceovers'] = 'Regenerate Voiceovers';
$string['voiceoverpaneltitle'] = 'AI Voiceover Status';
$string['voiceoverstatusnone'] = 'Voiceovers not yet generated. Click Generate to create voiceovers for all slides.';
$string['voiceoverstatusready'] = 'All voiceovers are ready. Students will hear narration on each slide.';
$string['voiceoverstatusgenerating'] = 'Generating voiceovers, please wait...';
$string['voiceoverstatuserror'] = 'Some voiceovers failed to generate. Try regenerating.';
$string['voiceoverprogress'] = '{$a->done} of {$a->total} slides processed';
$string['voiceoverresult'] = 'Generated: {$a->generated}, Skipped (no text): {$a->skipped}, Errors: {$a->errors}. Credits used: {$a->credits}.';

$string['importpptx'] = 'Import PowerPoint';
$string['importpptx_help'] = 'Upload a PowerPoint (.pptx) file to automatically convert each slide into an image. The images will be added to the slideshow. You can also drag and drop image files directly into the drop zone.';
$string['importpptxbutton'] = 'Upload PowerPoint File';
$string['importpptxdrag'] = 'Drag & drop a PowerPoint file or images here, or click to browse';
$string['importpptxconverting'] = 'Converting slides...';
$string['importpptxsuccess'] = '{$a} slides imported successfully';
$string['importpptxerror'] = 'Error converting PowerPoint file';
$string['importpptxuploading'] = 'Uploading images to file manager...';
$string['importpptxformats'] = 'Accepted: .pptx, .ppt, .png, .jpg, .jpeg, .gif, .webp, .svg';
$string['importpptxsizelimit'] = 'Maximum file size: 30 MB';
$string['importpptxcompress'] = 'File too large? In PowerPoint, click any image > Picture Format > Compress Pictures > uncheck "Apply only to this picture" > select Web (150 ppi) > OK, then save.';

$string['aisettings'] = 'AI Settings';
$string['aisettingsdesc'] = 'Configure AI Grader connection for voiceover generation.';
$string['siteid'] = 'Site ID';
$string['siteiddesc'] = 'Your AI Grader Site ID for credit-based voiceover generation.';
$string['apikey'] = 'API Key';
$string['apikeydesc'] = 'Your AI Grader API Key for authentication.';

// === Extra Slides (Video + Poster) ===
$string['extraslideheader'] = 'Video & Poster Slides';
$string['extraslideheader_help'] = 'Add video slides (YouTube, direct URL, or uploaded file) or poster image slides at any position in the slide sequence. Video slides can require students to watch a minimum amount before advancing.';
$string['extraslide_savenewfirst'] = 'Save this activity first, then re-open it to add video or poster slides.';
$string['extraslide_existing'] = 'Current Video & Poster Slides';
$string['extraslide_none'] = 'No video or poster slides yet.';
$string['extraslide_addvideo'] = 'Add Video Slide';
$string['extraslide_addposter'] = 'Add Poster Image Slide';
$string['extraslide_type_video'] = 'Video';
$string['extraslide_type_poster'] = 'Poster image';
$string['extraslide_source_youtube'] = 'YouTube URL';
$string['extraslide_source_url'] = 'Direct video URL (MP4/WebM)';
$string['extraslide_source_upload'] = 'Upload video file';
$string['extraslide_minwatch_none'] = 'No requirement — student can navigate away immediately';
$string['extraslide_minwatch_full'] = 'Must watch the full video';
$string['extraslide_minwatch_seconds'] = 'Must watch at least {$a} seconds';
$string['extraslide_position_beginning'] = 'At the beginning (before all slides)';
$string['extraslide_position_after'] = 'After slide {$a}';
$string['extraslide_position_end'] = 'At the end (after all slides)';
$string['extraslide_delete_confirm'] = 'Delete this slide?';

// === Video player strings ===
$string['video_watchprompt_full'] = 'Watch the full video to continue';
$string['video_watchprompt_seconds'] = 'Watch at least {$a} seconds to continue';
$string['video_watched'] = 'Video watched — you may now continue';
