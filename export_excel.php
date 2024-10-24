<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once('../../lib/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_data'])) {
    $exportData = json_decode($_POST['export_data'], true);
    
    if ($exportData) {
        $users = $exportData['users'];
        $allGrades = $exportData['all_grades'];

        // Lấy dữ liệu từ form
        $report_name = required_param('report_name', PARAM_TEXT);
        $start_date = required_param('start_date', PARAM_RAW);
        $end_date = required_param('end_date', PARAM_RAW);
      
        // Tạo 1 spreadsheet mới
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cài đặt tiêu đề của báo cáo
        $sheet->setCellValue('A1', 'Report Name: ' . $report_name);
        $sheet->setCellValue('A2', 'From: ' . $start_date . ' To: ' . $end_date);
        
        // Tạo header cột cho usernames and activities
        $row = 4; // Bắt đầu từ hàng thứ 4 của sheet để ngăn cách bảng báo cáo và tiêu đề
        $column = 'A';
        $sheet->setCellValue($column . $row, 'Username');
        
        // Tạo tất cả các activities
        $allActivityIds = [];
        if (!empty($allGrades['quiz'])) {
            $allActivityIds['quiz'] = array_keys($allGrades['quiz'][array_key_first($allGrades['quiz'])]);
        }
        if (!empty($allGrades['scorm'])) {
            $allActivityIds['scorm'] = array_keys($allGrades['scorm'][array_key_first($allGrades['scorm'])]);
        }

        // Tạo header cho tất cả activities
        foreach ($allActivityIds as $type => $ids) {
            foreach ($ids as $activityId) {
                $column++;
                $sheet->setCellValue($column . $row, ucfirst($type) . " $activityId");
            }
        }

        // Tạo cột username
        $row++;
        foreach ($users as $username) {
            $column = 'A';
            $sheet->setCellValue($column . $row, $username);

            foreach ($allActivityIds as $type => $ids) {
                foreach ($ids as $activityId) {
                    $column++;
                    if (!empty($allGrades[$type]) && isset($allGrades[$type][$username][$activityId])) {
                        $grade = $allGrades[$type][$username][$activityId];
                        $sheet->setCellValue($column . $row, $grade);
                    } else {
                        $sheet->setCellValue($column . $row, 'N/A');
                    }
                }
            }

            $row++;
        }

        // Cài đặt file name 
        $fileName = 'grade_report_' . date('YmdHis') . '.xlsx';

        // Làm sạch đầu ra tất cả các header của báo cáo trước
        if (ob_get_length()) {
            ob_clean();
        }

        // Gửi lời mời dowload file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        // Viết và xuất file excel.
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } else {
        echo 'Invalid data received.';
    }
} else {
    echo 'No POST data received.';
}
