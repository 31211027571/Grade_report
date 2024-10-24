<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'report/gradereport:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,  // Permission to view the report is set at the course level.
        'archetypes' => array(
            'teacher' => CAP_ALLOW,  // Teachers can view the report.
            'manager' => CAP_ALLOW,  // Managers can view the report.
        ),
    ),
);