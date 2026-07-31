<?php
/**
 * Slideshow - Save custom voiceover text for a slide
 *
 * @package    mod_slideshow
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

if (class_exists('\core_external\external_api')) {
    if (!class_exists('\mod_slideshow\external\st_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\st_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\st_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\st_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\st_external_value');
    }
} else {
    if (!class_exists('\mod_slideshow\external\st_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\st_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\st_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\st_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\st_external_value');
    }
}

use context_module;

class save_slide_text extends st_external_api {

    public static function execute_parameters(): st_external_function_parameters {
        return new st_external_function_parameters([
            'cmid' => new st_external_value(PARAM_INT, 'Course module ID'),
            'filename' => new st_external_value(PARAM_RAW, 'Slide image filename'),
            'customtext' => new st_external_value(PARAM_RAW, 'Custom voiceover text for this slide'),
        ]);
    }

    public static function execute(int $cmid, string $filename, string $customtext): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'filename' => $filename,
            'customtext' => $customtext,
        ]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);

        $existing = $DB->get_record('slideshow_voiceovers', [
            'slideshowid' => $slideshow->id,
            'filename' => $params['filename'],
        ]);

        $trimmed = trim($params['customtext']);

        if ($existing) {
            $existing->customtext = $trimmed;
            $existing->timemodified = time();
            $DB->update_record('slideshow_voiceovers', $existing);
        } else {
            $record = new \stdClass();
            $record->slideshowid = $slideshow->id;
            $record->filename = $params['filename'];
            $record->customtext = $trimmed;
            $record->status = 'pending';
            $record->creditscharged = 0;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('slideshow_voiceovers', $record);
        }

        return ['success' => true, 'error' => ''];
    }

    public static function execute_returns(): st_external_single_structure {
        return new st_external_single_structure([
            'success' => new st_external_value(PARAM_BOOL, 'Success status'),
            'error' => new st_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
