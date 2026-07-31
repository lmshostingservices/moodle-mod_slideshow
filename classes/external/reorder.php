<?php
namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

if (class_exists('\core_external\external_api')) {
    class_alias('\core_external\external_api', '\mod_slideshow\external\compat_external_api');
    class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\compat_external_function_parameters');
    class_alias('\core_external\external_multiple_structure', '\mod_slideshow\external\compat_external_multiple_structure');
    class_alias('\core_external\external_single_structure', '\mod_slideshow\external\compat_external_single_structure');
    class_alias('\core_external\external_value', '\mod_slideshow\external\compat_external_value');
} else {
    require_once($CFG->libdir . '/externallib.php');
    class_alias('\external_api', '\mod_slideshow\external\compat_external_api');
    class_alias('\external_function_parameters', '\mod_slideshow\external\compat_external_function_parameters');
    class_alias('\external_multiple_structure', '\mod_slideshow\external\compat_external_multiple_structure');
    class_alias('\external_single_structure', '\mod_slideshow\external\compat_external_single_structure');
    class_alias('\external_value', '\mod_slideshow\external\compat_external_value');
}

class reorder extends compat_external_api {

    public static function execute_parameters() {
        return new compat_external_function_parameters([
            'cmid'  => new compat_external_value(PARAM_INT, 'Course module id'),
            'order' => new compat_external_multiple_structure(
                new compat_external_value(PARAM_RAW, 'File key (pathnamehash preferred, filename fallback)'),
                'Ordered list of file keys', VALUE_REQUIRED
            ),
        ]);
    }

    public static function execute($cmid, $order) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'  => $cmid,
            'order' => $order,
        ]);

        $seq = $params['order'];
        if (empty($seq) || !is_array($seq)) {
            return ['status' => false, 'updated' => 0];
        }

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        require_once(__DIR__ . '/../../lib.php');
        slideshow_apply_order_ids($context->id, $cm->instance, $seq);

        return [
            'status'  => true,
            'updated' => count($seq),
        ];
    }

    public static function execute_returns() {
        return new compat_external_single_structure([
            'status'  => new compat_external_value(PARAM_BOOL, 'Success'),
            'updated' => new compat_external_value(PARAM_INT, 'How many files had sortorder changed'),
        ]);
    }
}
