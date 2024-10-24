<?php
namespace report_gradereport\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('gradereport_data', [
            'userid' => 'privacy:metadata:gradereport_data:userid',
            'quizid' => 'privacy:metadata:gradereport_data:quizid',
            'scormid' => 'privacy:metadata:gradereport_data:scormid',
            'grade' => 'privacy:metadata:gradereport_data:grade',
        ], 'privacy:metadata:gradereport_data');
        return $collection;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('gradereport_data', ['userid' => $userid]);
        
        if ($records) {
            writer::with_context($contextlist->get_contexts())->export_data([], (object) $records);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('gradereport_data', ['userid' => $userid]);
    }
}
