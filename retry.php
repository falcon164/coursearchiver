<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/coursearchiver/classes/processor.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$runid = required_param('runid', PARAM_INT);

global $DB;

// Fetch failed courses from the selected run
$failed = $DB->get_records(
    'tool_coursearchiver_log',
    ['runid' => $runid, 'status' => 'failed']
);

if (!$failed) {
    redirect(
        new moodle_url('/admin/tool/coursearchiver/runreport.php'),
        'No failed courses to retry.',
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$courses = [];
foreach ($failed as $f) {
    $courses[] = $f->courseid;
}

// Run archive again (backup only, no delete)
$processor = new tool_coursearchiver_processor([
    'mode' => tool_coursearchiver_processor::MODE_BACKUP,
    'data' => $courses
]);

$processor->execute();

redirect(
    new moodle_url('/admin/tool/coursearchiver/runreport.php'),
    'Retry completed. Check the latest run report.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
