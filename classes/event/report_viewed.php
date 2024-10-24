<?php
namespace report_gradereport\event;

defined('MOODLE_INTERNAL') || die();

class report_viewed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r';  // This is a read event.
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'course';  // The event relates to a course.
    }

    public static function get_name() {
        return get_string('eventreportviewed', 'report_gradereport');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' viewed the grade report for the course with id '{$this->courseid}'.";
    }

    public function get_url() {
        return new \moodle_url('/report/gradereport/index.php', array('id' => $this->courseid));
    }

    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->courseid)) {
            throw new \coding_exception('The courseid must be set.');
        }
    }
}
