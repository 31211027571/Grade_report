<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once('../../lib/phpspreadsheet/vendor/autoload.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

$PAGE->set_url('/report/gradereport/index.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'report_gradereport'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/report/gradereport/style.css');


require_capability('report/gradereport:view', $context);

echo $OUTPUT->header();

$template_select = optional_param('template_select', '', PARAM_TEXT);
$template_data = null;

if ($template_select !== '') {
    $template_data = load_report_template($template_select);
}

if (isset($_POST['generate_report'])) {
    $report_name = required_param('report_name', PARAM_TEXT);
    $start_date = required_param('start_date', PARAM_RAW);
    $end_date = required_param('end_date', PARAM_RAW);
    $usernames = required_param('usernames', PARAM_TEXT);
    $activity_ids = required_param('activity_ids', PARAM_TEXT);
    $save_template = optional_param('save_template', 0, PARAM_BOOL);

    $username_list = array_filter(array_map('trim', explode("\n", $usernames)));
    $activity_id_list = array_filter(array_map('trim', explode("\n", $activity_ids)));

    if (!empty($username_list) && !empty($activity_id_list)) {
        $all_grades = get_grades_for_all_activities($courseid, $activity_id_list, $username_list, $start_date, $end_date);
        
        if ($save_template) {
            save_report_template($report_name, $usernames, $activity_ids, strtotime($start_date), strtotime($end_date));
        }

        // Sử dụng form ẩn để gửi dữ liệu qua POST
        echo '<form id="redirectForm" method="post" action="generate_report_table.php">';
        echo '<input type="hidden" name="id" value="' . htmlspecialchars($courseid) . '">';
        echo '<input type="hidden" name="report_name" value="' . htmlspecialchars($report_name) . '">';
        echo '<input type="hidden" name="start_date" value="' . htmlspecialchars($start_date) . '">';
        echo '<input type="hidden" name="end_date" value="' . htmlspecialchars($end_date) . '">';
        echo '<input type="hidden" name="users" value="' . htmlspecialchars(json_encode($username_list)) . '">';
        echo '<input type="hidden" name="all_grades" value="' . htmlspecialchars(json_encode($all_grades)) . '">';
        echo '</form>';
        echo '<script>document.getElementById("redirectForm").submit();</script>';
        exit();
    } else {
        echo $OUTPUT->notification('Vui lòng nhập danh sách usernames và activity IDs.', 'error');
    }
}

render_form($template_data);
echo $OUTPUT->footer();

function get_grades_for_all_activities($courseid, $activity_ids, $usernames, $start_date, $end_date) {
    global $DB;
    $grades = [
        'quiz' => [],
        'scorm' => []
    ];
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);

    // Lấy user IDs từ usernames
    list($usersql, $userparams) = $DB->get_in_or_equal($usernames);
    $users = $DB->get_records_select('user', "username $usersql", $userparams, '', 'id, username');

    foreach ($activity_ids as $activity_id) {
        // Kiểm tra xem activity là quiz hay SCORM
        $quiz = $DB->get_record('quiz', ['id' => $activity_id]);
        $scorm = $DB->get_record('scorm', ['id' => $activity_id]);

        if ($quiz) {
            // Xử lý điểm cho quiz
            $grade_items = grade_get_grades($courseid, 'mod', 'quiz', $activity_id, array_keys($users));
            if (!empty($grade_items->items)) {
                foreach ($users as $user) {
                    if (isset($grade_items->items[0]->grades[$user->id])) {
                        $grades['quiz'][$user->username][$activity_id] = $grade_items->items[0]->grades[$user->id]->str_grade;
                    } else {
                        $grades['quiz'][$user->username][$activity_id] = 'N/A';
                    }
                }
            }
        } elseif ($scorm) {
            // Xử lý điểm cho SCORM
            $grade_item = $DB->get_record('grade_items', ['iteminstance' => $activity_id, 'itemmodule' => 'scorm']);
            if ($grade_item) {
                foreach ($users as $user) {
                    $grade = $DB->get_record_select('grade_grades',
                        'itemid = :itemid AND userid = :userid AND timemodified BETWEEN :start AND :end',
                        [
                            'itemid' => $grade_item->id,
                            'userid' => $user->id,
                            'start' => $start_timestamp,
                            'end' => $end_timestamp
                        ]
                    );

                    if ($grade) {
                        $grades['scorm'][$user->username][$activity_id] = $grade->finalgrade !== null ? $grade->finalgrade : 'N/A';
                    } else {
                        $grades['scorm'][$user->username][$activity_id] = 'N/A';
                    }
                }
            }
        }
    }
    return $grades;
}

/**
 * Lưu template báo cáo vào database
 */
function save_report_template($report_name, $usernames, $activity_ids, $start_date, $end_date) {
    global $DB, $USER;

    // Kiểm tra xem template có tồn tại không
    $existing_template = $DB->get_record('gradereport_templates', ['name' => $report_name, 'userid' => $USER->id]);

    $template_data = [
        'name' => $report_name,
        'usernames' => $usernames,
        'activity_ids' => $activity_ids,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'timecreated' => time(),
        'timemodified' => time(),
        'userid' => $USER->id
    ];

    if ($existing_template) {
        // Ghi đè template cũ
        $sql = "UPDATE {gradereport_templates} 
                SET usernames = :usernames, activity_ids = :activity_ids, start_date = :start_date, 
                    end_date = :end_date, timemodified = :timemodified 
                WHERE id = :id";
        $template_data['id'] = $existing_template->id;
    } else {
        // Tạo template mới
        $sql = "INSERT INTO {gradereport_templates} (name, usernames, activity_ids, start_date, end_date, timecreated, timemodified, userid) 
                VALUES (:name, :usernames, :activity_ids, :start_date, :end_date, :timecreated, :timemodified, :userid)";
    }

    $DB->execute($sql, $template_data);
}
/**
 * Load template đã chọn từ database lên form 
 */
function load_report_template($template_id) {
    global $DB, $USER;

    $template = $DB->get_record('gradereport_templates', ['id' => $template_id, 'userid' => $USER->id]);
    if ($template) {
        return $template;
    }
    return null;
}

/**
 * Tạo form nhập liệu
 */
function render_form($template_data = null) {
    global $OUTPUT, $DB, $USER, $courseid;

    echo '<form method="post" class="grade-report-form">';
    
    echo '<div class="form-group">';
    echo '<label for="report_name">Tên báo cáo:</label>';
    echo '<input type="text" name="report_name" id="report_name" value="' . htmlspecialchars($template_data ? $template_data->name : '') . '" required>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="start_date">Ngày bắt đầu:</label>';
    echo '<input type="date" name="start_date" id="start_date" value="' . htmlspecialchars($template_data ? date('Y-m-d', $template_data->start_date) : '') . '" required>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="end_date">Ngày kết thúc:</label>';
    echo '<input type="date" name="end_date" id="end_date" value="' . htmlspecialchars($template_data ? date('Y-m-d', $template_data->end_date) : '') . '" required>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="usernames">Danh sách Usernames:</label>';
    echo '<textarea name="usernames" id="usernames" rows="5" placeholder="Mỗi dòng một username">' . htmlspecialchars($template_data ? $template_data->usernames : '') . '</textarea>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="activity_ids">Danh sách Activity IDs:</label>';
    echo '<textarea name="activity_ids" id="activity_ids" rows="5" placeholder="Mỗi dòng một ID">' . htmlspecialchars($template_data ? $template_data->activity_ids : '') . '</textarea>';
    echo '</div>';

    echo '<div class="form-group checkbox-container">';
    echo '<input type="checkbox" name="save_template" id="save_template" value="1">';
    echo '<label for="save_template">Lưu Template</label>';
    echo '</div>';

    // Lấy template từ database
    $templates = $DB->get_records('gradereport_templates', ['userid' => $USER->id], 'name ASC', 'id, name');

    echo '<div class="form-group">';
    echo '<label for="template_select">Chọn Template:</label>';
    echo '<select name="template_select" id="template_select" onchange="this.form.submit()">';
    echo '<option value="">Chọn Template</option>';

    foreach ($templates as $template) {
        $selected = ($template_data && $template_data->id == $template->id) ? 'selected' : '';
        echo "<option value='{$template->id}' {$selected}>" . htmlspecialchars($template->name) . "</option>";
    }

    echo '</select>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<input type="submit" name="generate_report" value="Tạo báo cáo" class="btn btn-primary">';
    echo '</div>';

    echo '</form>';
}
