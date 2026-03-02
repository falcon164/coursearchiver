<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolcoursearchiverruns');

$context = context_system::instance();
require_capability('tool/coursearchiver:use', $context);

$PAGE->set_title('Archive Run Report');
$PAGE->set_heading('Archive Run Report');

global $DB, $OUTPUT;

echo $OUTPUT->header();

// Fetch distinct runs
$runs = $DB->get_records_sql(
    "SELECT runid,
            MIN(timecreated) AS started,
            COUNT(*) AS total,
            SUM(status = 'success') AS successcount,
            SUM(status = 'failed') AS failcount
       FROM {tool_coursearchiver_log}
   GROUP BY runid
   ORDER BY runid DESC"
);

if (!$runs) {
    echo $OUTPUT->notification('No archive runs found.', 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    'Run ID',
    'Started',
    'Total',
    'Success',
    'Failed',
    'Actions'
];

foreach ($runs as $run) {
    $retryurl = new moodle_url(
        '/admin/tool/coursearchiver/retry.php',
        ['runid' => $run->runid, 'sesskey' => sesskey()]
    );

    $viewurl = new moodle_url(
        '/admin/tool/coursearchiver/runreport.php',
        ['runid' => $run->runid]
    );

    $actions = html_writer::link($viewurl, 'View details');
    if ($run->failcount > 0) {
        $actions .= ' | ' . html_writer::link($retryurl, 'Retry failed');
    }

    $table->data[] = [
        $run->runid,
        userdate($run->started),
        $run->total,
        $run->successcount,
        $run->failcount,
        $actions
    ];
}

echo html_writer::table($table);

// ---------------------------------------------------------------------
// Run details view
// ---------------------------------------------------------------------
$runid = optional_param('runid', 0, PARAM_INT);

if ($runid) {
    echo $OUTPUT->heading("Run details: {$runid}", 3);

    $records = $DB->get_records(
        'tool_coursearchiver_log',
        ['runid' => $runid],
        'timecreated ASC'
    );

    $detail = new html_table();
    $detail->head = [
        'Course ID',
        'Course Name',
        'Status',
        'Backup File',
        'Error',
        'Time'
    ];

    foreach ($records as $r) {
        $detail->data[] = [
            $r->courseid,
            format_string($r->coursename),
            $r->status === 'success'
                ? html_writer::span('Success', 'text-success')
                : html_writer::span('Failed', 'text-danger'),
            $r->backupfile ?? '-',
            $r->errormessage ?? '-',
            userdate($r->timecreated)
        ];
    }

    echo html_writer::table($detail);
}

echo $OUTPUT->footer();

