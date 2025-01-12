<?php 
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

$PAGE->set_url('/gradereport/generate_report_table.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'report_gradereport'));
$PAGE->set_heading($course->fullname);

require_capability('report/gradereport:view', $context);

echo $OUTPUT->header();

// Get data from form submission
$report_name = required_param('report_name', PARAM_TEXT);
$start_date = required_param('start_date', PARAM_RAW);
$end_date = required_param('end_date', PARAM_RAW);
$users = json_decode(required_param('users', PARAM_RAW), true);
$all_grades = json_decode(required_param('all_grades', PARAM_RAW), true);

// Render the report table
render_report_table($users, $all_grades, $report_name, $start_date, $end_date);

echo $OUTPUT->footer();

/**
 * Renders the report table with quiz and SCORM grades.
 */
function render_report_table($users, $all_grades, $report_name, $start_date, $end_date) {
    global $DB;

    $formatted_start_date = date('Y-m-d', strtotime($start_date));
    $formatted_end_date = date('Y-m-d', strtotime($end_date));
    echo '<h2>' . htmlspecialchars($report_name) . '</h2>';
    echo '<p>From: ' . htmlspecialchars($formatted_start_date) . ' To: ' . htmlspecialchars($formatted_end_date) . '</p>';

    // Tạo form với phương thức POST để xuất dữ liệu ra Excel
    echo '<form method="post" action="export_excel.php">';
    echo '<input type="hidden" name="report_name" value="' . htmlspecialchars($report_name) . '">';
    echo '<input type="hidden" name="start_date" value="' . htmlspecialchars($formatted_start_date) . '">';
    echo '<input type="hidden" name="end_date" value="' . htmlspecialchars($formatted_end_date) . '">';
    echo '<input type="hidden" name="export_data" value="' . htmlspecialchars(json_encode(['users' => $users, 'all_grades' => $all_grades])) . '">';
    echo '<button type="submit">Export to Excel</button>';
    echo '</form>';

    echo '<table border="1">';
    echo '<tr><th>Username</th><th>Lastname</th><th>Firstname</th>';

    // Render headers cho các cột điểm quiz và SCORM
    if (!empty($all_grades['quiz'])) {
        foreach (array_keys($all_grades['quiz'][array_key_first($all_grades['quiz'])]) as $activity_id) {
            echo "<th>Quiz $activity_id</th>";
        }
    }
    if (!empty($all_grades['scorm'])) {
        foreach (array_keys($all_grades['scorm'][array_key_first($all_grades['scorm'])]) as $scorm_id) {
            echo "<th>SCORM $scorm_id</th>";
        }
    }
    echo '</tr>';

    // Lấy thông tin lastname và firstname từ cơ sở dữ liệu
    foreach ($users as $username) {
        $user = $DB->get_record('user', ['username' => $username], 'lastname, firstname');
        
        echo '<tr><td>' . htmlspecialchars($username) . '</td>';
        echo '<td>' . htmlspecialchars($user->lastname ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($user->firstname ?? 'N/A') . '</td>';

        // Hiển thị điểm cho các hoạt động Quiz
        if (!empty($all_grades['quiz']) && isset($all_grades['quiz'][$username])) {
            foreach ($all_grades['quiz'][$username] as $activity_id => $quiz_grade) {
                echo "<td>" . htmlspecialchars($quiz_grade) . "</td>";
            }
        }
        // Hiển thị điểm cho các hoạt động SCORM
        if (!empty($all_grades['scorm']) && isset($all_grades['scorm'][$username])) {
            foreach ($all_grades['scorm'][$username] as $scorm_id => $scorm_grade) {
                echo "<td>" . htmlspecialchars($scorm_grade) . "</td>";
            }
        }
        echo '</tr>';
    }
    echo '</table>';
}
