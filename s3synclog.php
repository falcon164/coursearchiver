<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolcoursearchiver_s3synclog');

$context = context_system::instance();
require_capability('tool/coursearchiver:views3synclog', $context);

global $DB, $OUTPUT, $PAGE;

// ---------------------------------------------------------
// Parameters (sticky filters)
// ---------------------------------------------------------
$page    = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$term    = optional_param('term', '', PARAM_TEXT);

// ---------------------------------------------------------
// Fetch available terms (exclude coldstorage-only terms)
// ---------------------------------------------------------
$termsql = "
    SELECT DISTINCT term
      FROM {tool_coursearchiver_s3files}
     WHERE status <> 'coldstorage'
  ORDER BY term DESC
";

$termrecords = $DB->get_records_sql($termsql);

$terms = [];
foreach ($termrecords as $r) {
    $terms[(string)$r->term] = (string)$r->term;
}

// Default to latest term
if ($term === '' && !empty($terms)) {
    $term = array_key_first($terms);
}

// ---------------------------------------------------------
// Page setup
// ---------------------------------------------------------
$PAGE->set_url(new moodle_url('/admin/tool/coursearchiver/s3synclog.php', [
    'term'    => $term,
    'perpage' => $perpage,
    'page'    => $page,
]));

$PAGE->set_title('S3 Sync Logs');
$PAGE->set_heading('S3 Archive Sync Log');

echo $OUTPUT->header();
echo $OUTPUT->heading('S3 Archive Sync Log');

// ---------------------------------------------------------
// Filter form (sticky)
// ---------------------------------------------------------
echo html_writer::start_tag('form', ['method' => 'get']);

echo html_writer::label('Term:', 'term');
echo html_writer::select($terms, 'term', $term);

echo ' ';

echo html_writer::label('Per page:', 'perpage');
echo html_writer::select(
    [10 => 10, 25 => 25, 50 => 50, 100 => 100],
    'perpage',
    $perpage
);

echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Apply']);

echo html_writer::end_tag('form');

// ---------------------------------------------------------
// SQL conditions
// ---------------------------------------------------------
$params = ['term' => $term];

$where = "
    WHERE term = :term
      AND status <> 'coldstorage'
";

// ---------------------------------------------------------
// TERM-WIDE SUMMARY (independent of pagination)
// ---------------------------------------------------------
$summary = [
    'uploaded' => 0,
    'failed'   => 0,
    'deleted'  => 0,
];

$summaryrecords = $DB->get_records_sql(
    "SELECT status, COUNT(1) AS cnt
       FROM {tool_coursearchiver_s3files}
       $where
   GROUP BY status",
    $params
);

foreach ($summaryrecords as $sr) {
    if (isset($summary[$sr->status])) {
        $summary[$sr->status] = $sr->cnt;
    }
}

echo $OUTPUT->box(
    'Uploaded: <strong>' . $summary['uploaded'] .
    '</strong> | Failed: <strong>' . $summary['failed'] .
    '</strong> | Deleted: <strong>' . $summary['deleted'] . '</strong>',
    'generalbox'
);

// ---------------------------------------------------------
// Retry button (only if failures exist for this term)
// ---------------------------------------------------------
if ($summary['failed'] > 0) {
    $retryurl = new moodle_url('/admin/tool/coursearchiver/s3synclog.php', [
        'term'    => $term,
        'retry'   => 1,
        'sesskey' => sesskey(),
    ]);

    echo $OUTPUT->single_button($retryurl, 'Retry Failed Uploads');
}

// ---------------------------------------------------------
// Pagination data
// ---------------------------------------------------------
$totalcount = $DB->count_records_sql(
    "SELECT COUNT(1)
       FROM {tool_coursearchiver_s3files}
       $where",
    $params
);

$offset = $page * $perpage;

// ---------------------------------------------------------
// Fetch paginated records
// ---------------------------------------------------------
$records = $DB->get_records_sql(
    "SELECT *
       FROM {tool_coursearchiver_s3files}
       $where
   ORDER BY timeuploaded DESC",
    $params,
    $offset,
    $perpage
);

// ---------------------------------------------------------
// Table
// ---------------------------------------------------------
$table = new html_table();
$table->head = [
    'Term',
    'Filename',
    'Status',
    'Error',
    'Time',
    'Actions',
];

foreach ($records as $r) {

    $download = '';
    if (in_array($r->status, ['uploaded', 'deleted'], true)) {
        $downloadurl = new moodle_url(
            '/admin/tool/coursearchiver/s3download.php',
            ['id' => $r->id, 'sesskey' => sesskey()]
        );
        $download = html_writer::link($downloadurl, 'Download');
    }

    $table->data[] = [
        s($r->term),
        s($r->filename),
        s($r->status),
        s($r->error),
        $r->timeuploaded ? userdate($r->timeuploaded) : '-',
        $download,
    ];
}

echo html_writer::table($table);

// ---------------------------------------------------------
// Pagination (state preserved)
// ---------------------------------------------------------
$baseurl = new moodle_url('/admin/tool/coursearchiver/s3synclog.php', [
    'term'    => $term,
    'perpage' => $perpage,
]);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);

echo $OUTPUT->footer();

