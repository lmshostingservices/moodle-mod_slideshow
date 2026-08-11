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
 * mod_slideshow file.
 *
 * @package    mod_slideshow
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_slideshow;

defined('MOODLE_INTERNAL') || die();

if (class_exists('\core_external\external_api')) {
    class_alias('\core_external\external_api', '\mod_slideshow\compat_external_api');
    class_alias('\core_external\external_function_parameters', '\mod_slideshow\compat_external_function_parameters');
    class_alias('\core_external\external_single_structure', '\mod_slideshow\compat_external_single_structure');
    class_alias('\core_external\external_value', '\mod_slideshow\compat_external_value');
} else {
    require_once($CFG->libdir . '/externallib.php');
    class_alias('\external_api', '\mod_slideshow\compat_external_api');
    class_alias('\external_function_parameters', '\mod_slideshow\compat_external_function_parameters');
    class_alias('\external_single_structure', '\mod_slideshow\compat_external_single_structure');
    class_alias('\external_value', '\mod_slideshow\compat_external_value');
}

class external extends compat_external_api {
    public static function view_slide_parameters() {
        return new compat_external_function_parameters([
            'slideinstance' => new compat_external_value(PARAM_INT, 'Slideshow instance ID'),
            'slideindex'    => new compat_external_value(PARAM_INT, '0-based index of the slide just viewed'),
        ]);
    }

    public static function view_slide($slideinstance, $slideindex) {
        global $DB, $USER;

        self::validate_parameters(self::view_slide_parameters(), [
            'slideinstance' => $slideinstance,
            'slideindex'    => $slideindex,
        ]);

        $slideshow = $DB->get_record('slideshow', ['id' => $slideinstance], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($slideshow->id, 'slideshow');

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:view', $context);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_slideshow', 'slideimages', $slideshow->id, 'sortorder, id', false);
        $realcount = count($files);

        $slideviewed = max(1, (int)$slideindex + 1);
        if ($realcount > 0) {
            $slideviewed = min($slideviewed, $realcount);
        }

        $iscompleted = ($realcount > 0 && $slideviewed >= $realcount);

        $params = ['instanceid' => $slideshow->id, 'userid' => $USER->id];
        if ($rec = $DB->get_record('slideshow_completion', $params)) {
            $needsupdate =
                $slideviewed > (int)$rec->viewed ||
                (int)$rec->slidecount !== $realcount ||
                ($iscompleted && !(int)$rec->completion);

            if ($needsupdate) {
                $rec->viewed       = max((int)$rec->viewed, $slideviewed);
                $rec->slidecount   = $realcount;
                $rec->completion   = $iscompleted ? 1 : 0;
                $rec->timemodified = time();
                $DB->update_record('slideshow_completion', $rec);
            }
        } else {
            $DB->insert_record('slideshow_completion', (object)[
                'instanceid'   => $slideshow->id,
                'userid'       => $USER->id,
                'viewed'       => $slideviewed,
                'slidecount'   => $realcount,
                'completion'   => $iscompleted ? 1 : 0,
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }

        $completion_enabled = ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && !empty($slideshow->completionallslides));
        if ($iscompleted && $completion_enabled) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled() && $completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }
        }

        return [
            'status'    => true,
            'completed' => $iscompleted,
            'viewed'    => $slideviewed,
        ];
    }

    public static function view_slide_returns() {
        return new compat_external_single_structure([
            'status' => new compat_external_value(PARAM_BOOL, 'True if the state was updated'),
            'completed' => new compat_external_value(PARAM_BOOL, 'True if activity is complete'),
            'viewed' => new compat_external_value(PARAM_INT, 'Highest slides viewed (1-based)'),
        ]);
    }
}
