<?php
/**
 * mod_slideshow - Delete an extra slide (video or poster).
 *
 * @package    mod_slideshow
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
if (class_exists('\core_external\external_api')) {
    if (!class_exists('\mod_slideshow\external\des_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\des_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\des_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\des_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\des_external_value');
    }
} else {
    require_once($CFG->libdir . '/externallib.php');
    if (!class_exists('\mod_slideshow\external\des_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\des_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\des_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\des_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\des_external_value');
    }
}

class delete_extra_slide extends des_external_api {

    public static function execute_parameters(): des_external_function_parameters {
        return new des_external_function_parameters([
            'cmid' => new des_external_value(PARAM_INT, 'Course module ID'),
            'id'   => new des_external_value(PARAM_INT, 'Extra slide ID to delete'),
        ]);
    }

    public static function execute(int $cmid, int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'id'   => $id,
        ]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        $record = $DB->get_record('slideshow_extra_slides',
            ['id' => $params['id'], 'slideshowid' => $cm->instance]);
        if (!$record) {
            return ['success' => false, 'message' => 'Slide not found'];
        }

        // Delete associated files
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_slideshow', 'slideposterimages', $record->id);
        $fs->delete_area_files($context->id, 'mod_slideshow', 'slidevideofiles', $record->id);

        // Delete DB record
        $DB->delete_records('slideshow_extra_slides', ['id' => $record->id]);

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): des_external_single_structure {
        return new des_external_single_structure([
            'success' => new des_external_value(PARAM_BOOL, 'Success'),
            'message' => new des_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
