<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('toolcoursearchiverruns');

require_login();
require_capability('moodle/site:config', context_system::instance());

$runid = required_param('runid', PARAM_INT);

$PAGE->set_title('Archive Run Details');
$PAGE->set_heading('Archive Run Details');

echo $OUTPUT->header();

global $DB;

$records = $DB->get_records(
    'tool_coursearchiver_log',
    ['runid' => $runid],
    'timecreated ASC'
);

if (!$records) {
    echo $OUTPUT->notification('No records found for this run.', 'error');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = ['Course', 'Status', 'Backup file', 'Error'];

foreach ($records as $r) {
    $table->data[] = [
        format_string($r->coursename),
        $r->status === 'success'
            ? html_writer::tag('span', 'Success', ['style' => 'color: green'])
            : html_writer::tag('span', 'Failed', ['style' => 'color: red']),
        $r->backupfile ?? '-',
        $r->errormessage ?? '-'
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
