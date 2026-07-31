<?php
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
