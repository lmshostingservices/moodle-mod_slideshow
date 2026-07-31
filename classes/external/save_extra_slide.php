<?php
/**
 * mod_slideshow - Save (create or update) an extra slide (video or poster).
 *
 * @package    mod_slideshow
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
if (class_exists('\core_external\external_api')) {
    if (!class_exists('\mod_slideshow\external\ses_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\ses_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\ses_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\ses_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\ses_external_value');
    }
} else {
    require_once($CFG->libdir . '/externallib.php');
    if (!class_exists('\mod_slideshow\external\ses_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\ses_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\ses_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\ses_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\ses_external_value');
    }
}

class save_extra_slide extends ses_external_api {

    public static function execute_parameters(): ses_external_function_parameters {
        return new ses_external_function_parameters([
            'cmid'          => new ses_external_value(PARAM_INT,  'Course module ID'),
            'id'            => new ses_external_value(PARAM_INT,  'Extra slide ID (0 = create new)', VALUE_DEFAULT, 0),
            'slidetype'     => new ses_external_value(PARAM_ALPHA,'Slide type: video or poster', VALUE_DEFAULT, 'video'),
            'title'         => new ses_external_value(PARAM_TEXT, 'Optional title', VALUE_DEFAULT, ''),
            'videosource'   => new ses_external_value(PARAM_ALPHA,'Source: youtube, url, or upload', VALUE_DEFAULT, 'youtube'),
            'videourl'      => new ses_external_value(PARAM_RAW,  'YouTube or direct video URL', VALUE_DEFAULT, ''),
            'videominwatch' => new ses_external_value(PARAM_INT,  '0=none, -1=full video, N=min seconds', VALUE_DEFAULT, 0),
            'position'      => new ses_external_value(PARAM_INT,  'Sortorder to insert at (-1=end)', VALUE_DEFAULT, -1),
            'imagebase64'   => new ses_external_value(PARAM_RAW,  'Base64-encoded poster image', VALUE_DEFAULT, ''),
            'imagefilename' => new ses_external_value(PARAM_FILE, 'Poster image filename', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int $cmid, int $id, string $slidetype, string $title,
        string $videosource, string $videourl, int $videominwatch,
        int $position, string $imagebase64, string $imagefilename
    ): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'          => $cmid,
            'id'            => $id,
            'slidetype'     => $slidetype,
            'title'         => $title,
            'videosource'   => $videosource,
            'videourl'      => $videourl,
            'videominwatch' => $videominwatch,
            'position'      => $position,
            'imagebase64'   => $imagebase64,
            'imagefilename' => $imagefilename,
        ]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        require_once(__DIR__ . '/../../lib.php');

        $slideshowid = $cm->instance;
        $now = time();
        $slidetype = in_array($params['slidetype'], ['video', 'poster']) ? $params['slidetype'] : 'video';
        $videosource = in_array($params['videosource'], ['youtube', 'url', 'upload']) ? $params['videosource'] : 'youtube';
        $videourl = clean_param($params['videourl'], PARAM_URL);
        $videominwatch = (int)$params['videominwatch'];
        $title = trim($params['title']);
        $position = (int)$params['position'];

        // Determine sortorder
        if ($params['id'] > 0) {
            // Editing: keep existing sortorder
            $existing = $DB->get_record('slideshow_extra_slides',
                ['id' => $params['id'], 'slideshowid' => $slideshowid], '*', MUST_EXIST);
            $sortorder = (int)$existing->sortorder;
        } else {
            // New slide — insert at position
            if ($position < 0) {
                // Append at end
                $sortorder = slideshow_get_next_sortorder($slideshowid, $context->id);
            } else {
                // Shift all slides at sortorder >= position up by 1
                slideshow_shift_slides_up($slideshowid, $context->id, $position);
                $sortorder = $position;
            }
        }

        // Handle poster image upload
        $storedImageFilename = '';
        if ($slidetype === 'poster' && !empty($params['imagebase64']) && !empty($params['imagefilename'])) {
            $decoded = base64_decode($params['imagebase64'], true);
            if ($decoded !== false && strlen($decoded) <= 20 * 1024 * 1024) {
                $safename = clean_filename($params['imagefilename']);
                $fs = get_file_storage();
                // Store in slideposterimages filearea
                // Use a temp itemid = 0 first, then update after insert
                $storedImageFilename = $safename;
            }
        }

        // Save or update DB record
        if ($params['id'] > 0) {
            $record = $existing;
            $record->slidetype     = $slidetype;
            $record->title         = $title;
            $record->videosource   = $slidetype === 'video' ? $videosource : '';
            $record->videourl      = $slidetype === 'video' ? $videourl : '';
            $record->videominwatch = $slidetype === 'video' ? $videominwatch : 0;
            $record->timemodified  = $now;
            if (!empty($storedImageFilename)) $record->imagefilename = $storedImageFilename;
            $DB->update_record('slideshow_extra_slides', $record);
            $newId = $record->id;
        } else {
            $record = new \stdClass();
            $record->slideshowid   = $slideshowid;
            $record->sortorder     = $sortorder;
            $record->slidetype     = $slidetype;
            $record->title         = $title;
            $record->videosource   = $slidetype === 'video' ? $videosource : '';
            $record->videourl      = $slidetype === 'video' ? $videourl : '';
            $record->videominwatch = $slidetype === 'video' ? $videominwatch : 0;
            $record->imagefilename = $storedImageFilename;
            $record->timecreated   = $now;
            $record->timemodified  = $now;
            $newId = $DB->insert_record('slideshow_extra_slides', $record);
        }

        // Save poster image file (now that we have the ID as itemid)
        if ($slidetype === 'poster' && !empty($params['imagebase64']) && !empty($params['imagefilename'])) {
            $decoded = base64_decode($params['imagebase64'], true);
            if ($decoded !== false && strlen($decoded) <= 20 * 1024 * 1024) {
                $safename = clean_filename($params['imagefilename']);
                $fs = get_file_storage();
                $existing_file = $fs->get_file($context->id, 'mod_slideshow', 'slideposterimages', $newId, '/', $safename);
                if ($existing_file) $existing_file->delete();
                $filerecord = (object)[
                    'contextid' => $context->id,
                    'component' => 'mod_slideshow',
                    'filearea'  => 'slideposterimages',
                    'itemid'    => $newId,
                    'filepath'  => '/',
                    'filename'  => $safename,
                    'userid'    => $USER->id,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ];
                try {
                    $fs->create_file_from_string((array)$filerecord, $decoded);
                    $DB->set_field('slideshow_extra_slides', 'imagefilename', $safename, ['id' => $newId]);
                } catch (\Exception $e) {
                    // Image save failed — not fatal
                }
            }
        }

        return ['success' => true, 'id' => $newId, 'message' => ''];
    }

    public static function execute_returns(): ses_external_single_structure {
        return new ses_external_single_structure([
            'success' => new ses_external_value(PARAM_BOOL, 'Success'),
            'id'      => new ses_external_value(PARAM_INT,  'ID of the created/updated extra slide'),
            'message' => new ses_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
