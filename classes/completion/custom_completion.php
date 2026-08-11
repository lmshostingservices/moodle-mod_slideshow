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

namespace mod_slideshow\completion;

use core_completion\activity_custom_completion;

class custom_completion extends activity_custom_completion {
    public function get_state(string $rule): int {
        global $DB, $USER;

        $this->validate_rule($rule);

        switch ($rule) {
            case 'completionallslides':
                $enabled = (int)($this->cm->customdata['customcompletionrules']['completionallslides'] ?? 0);
                if (!$enabled) {
                    return COMPLETION_UNKNOWN;
                }

                $userid = $this->userid ?? $USER->id;
                $progress = $DB->get_record('slideshow_completion',
                    ['instanceid' => $this->cm->instance, 'userid' => $userid],
                    'viewed, slidecount, completion', IGNORE_MISSING);

                if (!$progress) {
                    return COMPLETION_INCOMPLETE;
                }

                $complete = ((int)$progress->completion === 1)
                    || ((int)$progress->slidecount > 0 && (int)$progress->viewed >= (int)$progress->slidecount);

                return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

            default:
                return COMPLETION_UNKNOWN;
        }
    }


    public static function get_defined_custom_rules(): array {
        return ['completionallslides'];
    }

    public function get_custom_rule_descriptions(): array {
        return [
            'completionallslides' => get_string('completiondetail:reachend', 'slideshow'),
        ];
    }

    public function get_sort_order(): array {
        return [
            'completionview',
            'completionallslides',
        ];
    }
}
