<?php
/**
 * Slideshow - Generate voiceovers for all slides in a slideshow
 * Bulk endpoint to avoid N individual HTTP calls from the browser.
 *
 * @package    mod_slideshow
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

if (class_exists('\core_external\external_api')) {
    if (!class_exists('\mod_slideshow\external\vo_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\vo_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\vo_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\vo_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\vo_external_value');
    }
} else {
    if (!class_exists('\mod_slideshow\external\vo_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\vo_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\vo_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\vo_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\vo_external_value');
    }
}

if (!class_exists('\core_external\external_multiple_structure')) {
    if (class_exists('\external_multiple_structure')) {
        class_alias('\external_multiple_structure', '\mod_slideshow\external\vo_external_multiple_structure');
    }
} else {
    class_alias('\core_external\external_multiple_structure', '\mod_slideshow\external\vo_external_multiple_structure');
}

use context_module;

class generate_all_voiceovers extends vo_external_api {

    public static function execute_parameters(): vo_external_function_parameters {
        return new vo_external_function_parameters([
            'cmid' => new vo_external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);

        if (empty($slideshow->enablevoiceover)) {
            return ['success' => false, 'generated' => 0, 'skipped' => 0, 'errors' => 0, 'totalcredits' => 0, 'error' => 'Voiceover is not enabled'];
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_slideshow', 'slideimages', $slideshow->id, 'sortorder, id', false);

        $generated = 0;
        $skipped = 0;
        $errors = 0;
        $totalcredits = 0;

        $DB->set_field('slideshow', 'voiceoverstatus', 'generating', ['id' => $slideshow->id]);

        $voiceovers = $DB->get_records('slideshow_voiceovers', ['slideshowid' => $slideshow->id], '', 'filename, customtext, voiceover_disabled');
        $vomap = [];
        $vodisabledmap = [];
        foreach ($voiceovers as $vo) {
            $vomap[$vo->filename] = $vo->customtext ?? '';
            $vodisabledmap[$vo->filename] = !empty($vo->voiceover_disabled);
        }

        foreach ($files as $file) {
            $fname = $file->get_filename();
            if (!empty($vodisabledmap[$fname])) {
                $skipped++;
                continue;
            }
            $customtext = isset($vomap[$fname]) ? trim($vomap[$fname]) : '';
            $result = generate_voiceover::execute($params['cmid'], $fname, $customtext);

            if ($result['success'] && !empty($result['audioContent'])) {
                $generated++;
                $totalcredits += generate_voiceover::CREDITS_PER_VOICEOVER;
            } else if ($result['success'] && empty($result['audioContent'])) {
                $skipped++;
            } else {
                $errors++;
            }
        }

        $status = ($errors > 0) ? 'error' : 'ready';
        $DB->set_field('slideshow', 'voiceoverstatus', $status, ['id' => $slideshow->id]);

        return [
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
            'totalcredits' => $totalcredits,
            'error' => '',
        ];
    }

    public static function execute_returns(): vo_external_single_structure {
        return new vo_external_single_structure([
            'success' => new vo_external_value(PARAM_BOOL, 'Success status'),
            'generated' => new vo_external_value(PARAM_INT, 'Number of voiceovers generated'),
            'skipped' => new vo_external_value(PARAM_INT, 'Number of slides skipped (no text)'),
            'errors' => new vo_external_value(PARAM_INT, 'Number of errors'),
            'totalcredits' => new vo_external_value(PARAM_INT, 'Total credits consumed'),
            'error' => new vo_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
