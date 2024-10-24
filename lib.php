<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends course navigation to include the Grade Report.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param stdClass $context
 */
function report_gradereport_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/gradereport:view', $context)) {
        $url = new moodle_url('/report/gradereport/index.php', array('id' => $course->id));
        $name = get_string('pluginname', 'report_gradereport');
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}
