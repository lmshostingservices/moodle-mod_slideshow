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
 * Slideshow - Toggle voiceover disabled flag for a single slide
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
    if (!class_exists('\mod_slideshow\external\tvd_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\tvd_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\tvd_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\tvd_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\tvd_external_value');
    }
} else {
    if (!class_exists('\mod_slideshow\external\tvd_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\tvd_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\tvd_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\tvd_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\tvd_external_value');
    }
}

use context_module;

class toggle_voiceover_disabled extends tvd_external_api {
    public static function execute_parameters(): tvd_external_function_parameters {
        return new tvd_external_function_parameters([
            'cmid'     => new tvd_external_value(PARAM_INT, 'Course module ID'),
            'filename' => new tvd_external_value(PARAM_RAW, 'Slide image filename'),
            'disabled' => new tvd_external_value(PARAM_INT, '1 to disable voiceover for this slide, 0 to enable'),
        ]);
    }

    public static function execute(int $cmid, string $filename, int $disabled): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'     => $cmid,
            'filename' => $filename,
            'disabled' => $disabled,
        ]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);
        \core\session\manager::write_close();

        $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);
        $disabledVal = $params['disabled'] ? 1 : 0;

        $existing = $DB->get_record('slideshow_voiceovers', [
            'slideshowid' => $slideshow->id,
            'filename'    => $params['filename'],
        ]);

        if ($existing) {
            $existing->voiceover_disabled = $disabledVal;
            $existing->timemodified       = time();
            $DB->update_record('slideshow_voiceovers', $existing);
        } else {
            $record                    = new \stdClass();
            $record->slideshowid       = $slideshow->id;
            $record->filename          = $params['filename'];
            $record->voiceover_disabled = $disabledVal;
            $record->status            = 'pending';
            $record->creditscharged    = 0;
            $record->timecreated       = time();
            $record->timemodified      = time();
            $DB->insert_record('slideshow_voiceovers', $record);
        }

        return ['success' => true, 'disabled' => $disabledVal, 'error' => ''];
    }

    public static function execute_returns(): tvd_external_single_structure {
        return new tvd_external_single_structure([
            'success'  => new tvd_external_value(PARAM_BOOL, 'Success status'),
            'disabled' => new tvd_external_value(PARAM_INT, 'New disabled state (1 = disabled, 0 = enabled)'),
            'error'    => new tvd_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
