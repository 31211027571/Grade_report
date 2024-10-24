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

        // Get report name and dates from the form
        $report_name = required_param('report_name', PARAM_TEXT);
        $start_date = required_param('start_date', PARAM_RAW);
        $end_date = required_param('end_date', PARAM_RAW);
      
        // Create a new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the report title
        $sheet->setCellValue('A1', 'Report Name: ' . $report_name);
        $sheet->setCellValue('A2', 'From: ' . $start_date . ' To: ' . $end_date);
        
        // Set header row for usernames and activities
        $row = 4; // Start from row 4 to leave space for report name and date range
        $column = 'A';
        $sheet->setCellValue($column . $row, 'Username');
        
        // Collect all activity IDs
        $allActivityIds = [];
        if (!empty($allGrades['quiz'])) {
            $allActivityIds['quiz'] = array_keys($allGrades['quiz'][array_key_first($allGrades['quiz'])]);
        }
        if (!empty($allGrades['scorm'])) {
            $allActivityIds['scorm'] = array_keys($allGrades['scorm'][array_key_first($allGrades['scorm'])]);
        }

        // Render headers for all activities
        foreach ($allActivityIds as $type => $ids) {
            foreach ($ids as $activityId) {
                $column++;
                $sheet->setCellValue($column . $row, ucfirst($type) . " $activityId");
            }
        }

        // Set user data in rows
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

        // Set the filename for the Excel file
        $fileName = 'grade_report_' . date('YmdHis') . '.xlsx';

        // Clear the output buffer to avoid any output before headers
        if (ob_get_length()) {
            ob_clean();
        }

        // Send headers for downloading the file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        // Write and output the Excel file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } else {
        echo 'Invalid data received.';
    }
} else {
    echo 'No POST data received.';
}
