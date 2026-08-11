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
 * Slideshow - Upload a file directly to a Moodle draft area
 *
 * v1.6.8: Bypasses the repository system entirely by using Moodle's
 * file_storage API to write files directly into the user's draft area.
 * Used by pptximport.js to inject converted PPTX slide images.
 *
 * Accepts either cmid (editing existing activity) or courseid (creating new activity)
 * for capability checking.
 *
 * @package    mod_slideshow
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

if (class_exists('\core_external\external_api')) {
    if (!class_exists('\mod_slideshow\external\\udf_external_api')) {
        class_alias('\core_external\external_api', '\mod_slideshow\external\\udf_external_api');
        class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\\udf_external_function_parameters');
        class_alias('\core_external\external_single_structure', '\mod_slideshow\external\udf_external_single_structure');
        class_alias('\core_external\external_value', '\mod_slideshow\external\\udf_external_value');
    }
} else {
    if (!class_exists('\mod_slideshow\external\udf_external_api')) {
        class_alias('\external_api', '\mod_slideshow\external\\udf_external_api');
        class_alias('\external_function_parameters', '\mod_slideshow\external\udf_external_function_parameters');
        class_alias('\external_single_structure', '\mod_slideshow\external\\udf_external_single_structure');
        class_alias('\external_value', '\mod_slideshow\external\udf_external_value');
    }
}

use context_module;
use context_course;
use context_user;

class upload_draft_file extends udf_external_api {
    public static function execute_parameters(): udf_external_function_parameters {
        return new udf_external_function_parameters([
            'cmid' => new udf_external_value(PARAM_INT, 'Course module ID (0 if creating new activity)', VALUE_DEFAULT, 0),
            'courseid' => new udf_external_value(PARAM_INT, 'Course ID (used when cmid is 0)', VALUE_DEFAULT, 0),
            'draftitemid' => new udf_external_value(PARAM_INT, 'Draft area item ID from the file manager'),
            'filename' => new udf_external_value(PARAM_FILE, 'Filename for the uploaded file'),
            'filedata' => new udf_external_value(PARAM_RAW, 'Base64-encoded file content'),
        ]);
    }

    public static function execute(int $cmid, int $courseid, int $draftitemid, string $filename, string $filedata): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'draftitemid' => $draftitemid,
            'filename' => $filename,
            'filedata' => $filedata,
        ]);

        if ($params['cmid'] > 0) {
            $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
            $context = context_module::instance($cm->id);
            self::validate_context($context);
            require_capability('mod/slideshow:manage', $context);
        } elseif ($params['courseid'] > 0) {
            $context = context_course::instance($params['courseid']);
            self::validate_context($context);
            require_capability('moodle/course:manageactivities', $context);
        } else {
            return ['success' => false, 'error' => 'Either cmid or courseid must be provided'];
        }

        $decoded = base64_decode($params['filedata'], true);
        if ($decoded === false) {
            return ['success' => false, 'error' => 'Invalid base64 data'];
        }

        $maxsize = 10 * 1024 * 1024;
        if (strlen($decoded) > $maxsize) {
            return ['success' => false, 'error' => 'File exceeds 10 MB limit'];
        }

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();

        $existing = $fs->get_file(
            $usercontext->id,
            'user',
            'draft',
            $params['draftitemid'],
            '/',
            $params['filename']
        );
        if ($existing) {
            $existing->delete();
        }

        $filerecord = new \stdClass();
        $filerecord->contextid = $usercontext->id;
        $filerecord->component = 'user';
        $filerecord->filearea = 'draft';
        $filerecord->itemid = $params['draftitemid'];
        $filerecord->filepath = '/';
        $filerecord->filename = $params['filename'];
        $filerecord->userid = $USER->id;
        $filerecord->source = $params['filename'];
        $filerecord->author = fullname($USER);
        $filerecord->license = $CFG->sitedefaultlicense ?? 'unknown';
        $filerecord->timecreated = time();
        $filerecord->timemodified = time();

        try {
            $fs->create_file_from_string($filerecord, $decoded);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'File storage error: ' . $e->getMessage()];
        }

        return ['success' => true, 'error' => ''];
    }

    public static function execute_returns(): udf_external_single_structure {
        return new udf_external_single_structure([
            'success' => new udf_external_value(PARAM_BOOL, 'Success status'),
            'error' => new udf_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}