<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once('../../lib/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_data'])) {
    $exportData = json_decode($_POST['export_data'], true);
    
    if ($exportData) {
        $users = $exportData['users'];
        $allGrades = $exportData['all_grades'];

        // Lấy thông tin tên báo cáo và ngày tháng
        $report_name = required_param('report_name', PARAM_TEXT);
        $start_date = required_param('start_date', PARAM_RAW);
        $end_date = required_param('end_date', PARAM_RAW);
      
        // Tạo một bảng tính mới
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Đặt tiêu đề và thông tin báo cáo
        $sheet->setCellValue('A1', 'Report Name: ' . $report_name);
        $sheet->setCellValue('A2', 'From: ' . $start_date . ' To: ' . $end_date);

        // Bắt đầu từ hàng thứ 4 và thêm các tiêu đề cột cho tên người dùng, họ, tên và điểm
        $row = 4; // Để lại khoảng trống cho tiêu đề báo cáo
        $sheet->setCellValue('A4', 'Username');
        $sheet->setCellValue('B4', 'Họ');
        $sheet->setCellValue('C4', 'Tên');

        // Thu thập tất cả ID hoạt động từ quiz và scorm
        $allActivityIds = [];
        if (!empty($allGrades['quiz'])) {
            $allActivityIds['quiz'] = array_keys($allGrades['quiz'][array_key_first($allGrades['quiz'])]);
        }
        if (!empty($allGrades['scorm'])) {
            $allActivityIds['scorm'] = array_keys($allGrades['scorm'][array_key_first($allGrades['scorm'])]);
        }

        // Đặt lại `$column` thành 'D' trước khi thêm tiêu đề cho các hoạt động
        $column = 'D';
        foreach ($allActivityIds as $type => $ids) {
            foreach ($ids as $activityId) {
                $sheet->setCellValue($column . $row, ucfirst($type) . " $activityId");
                $column++;
            }
        }

        // Đặt dữ liệu người dùng vào các hàng tương ứng
        $row++;
        foreach ($users as $username) {
            $user = $DB->get_record('user', ['username' => $username], 'lastname, firstname');
            $sheet->setCellValue("A{$row}", $username);
            $sheet->setCellValue("B{$row}", $user->lastname ?? 'N/A');
            $sheet->setCellValue("C{$row}", $user->firstname ?? 'N/A');

            // Đặt lại `$column` thành 'D' trước khi thêm dữ liệu điểm
            $column = 'D';
            foreach ($allActivityIds as $type => $ids) {
                foreach ($ids as $activityId) {
                    $grade = $allGrades[$type][$username][$activityId] ?? 'N/A';
                    $sheet->setCellValue($column . $row, $grade);
                    $column++;
                }
            }
            $row++;
        }

        // Tên file báo cáo
        $fileName = 'grade_report_' . date('YmdHis') . '.xlsx';

        // Xóa bộ đệm nếu có thông tin trước đó tránh lỗi trong khi xuất file
        if (ob_get_length()) {
            ob_clean();
        }

        // Headers cho phép tải file Excel xuống
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        // Ghi và xuất file Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } else {
        echo 'Invalid data received.';
    }
} else {
    echo 'No POST data received.';
}
