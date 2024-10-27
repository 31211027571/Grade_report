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
 * User update tool
 *
 * @package    tool_updateuser
 * @copyright  2023 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

admin_externalpage_setup('toolupdateuser');

$PAGE->set_url('/admin/tool/updateuser/update_user_csv.php');
$PAGE->set_context($context);
$PAGE->set_title("Cập nhật thông tin người dùng từ CSV");
$PAGE->set_heading("Cập nhật thông tin người dùng từ CSV");

echo $OUTPUT->header();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['usercsv']['tmp_name'])) {
    $file = $_FILES['usercsv']['tmp_name'];
    $handle = fopen($file, "r");
    $updated_users = 0;
    
    // Đọc dữ liệu từ file CSV
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        list($username, $lastname, $firstname) = $data;

        // Kiểm tra nếu dữ liệu hợp lệ
        if (!empty($username) && !empty($lastname) && !empty($firstname)) {
            // Cập nhật thông tin người dùng trong cơ sở dữ liệu
            $user = $DB->get_record('user', ['username' => $username], 'id');
            if ($user) {
                $user->lastname = $lastname;
                $user->firstname = $firstname;
                $DB->update_record('user', $user);
                $updated_users++;
            }
        }
    }
    fclose($handle);
    
    echo $OUTPUT->notification("Đã cập nhật $updated_users người dùng từ file CSV.", 'notifysuccess');
} else {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<label for="usercsv">Chọn file CSV để cập nhật thông tin người dùng:</label><br>';
    echo '<input type="file" name="usercsv" id="usercsv" accept=".csv" required><br><br>';
    echo '<input type="submit" value="Cập nhật thông tin">';
    echo '</form>';
}

echo $OUTPUT->footer();
?>
