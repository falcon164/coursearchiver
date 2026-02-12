<?php
// This file is part of Moodle.
//
// Enhancements:
// - Show only S3-synced archives (status = uploaded)
// - Term-based filtering (default: latest term)
// - Bulk delete per term (Select All behaviour)
// - Status updated to 'deleted' after local deletion
// - Confirmation summary shown after deletion
//
// @package    tool_coursearchiver
// @author     Matthew Davidson (original)
// @author     Mujahid (term filtering, S3 safety, bulk delete)

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolcoursearchiver_archivelist');

$context = context_system::instance();
require_capability('tool/coursearchiver:use', $context);

global $DB, $OUTPUT, $PAGE;

// ---------------------------------------------------------
// Page setup
// ---------------------------------------------------------
$PAGE->set_url('/admin/tool/coursearchiver/archivelist.php');
$PAGE->set_title('Course Archives');
$PAGE->set_heading('Course Archives (Safe to Delete)');

echo $OUTPUT->header();
echo $OUTPUT->heading('Course Archives');

// ---------------------------------------------------------
// Discover terms that have uploaded (S3-synced) files
// ---------------------------------------------------------
$termrecords = $DB->get_records_sql(
    "SELECT DISTINCT term
       FROM {tool_coursearchiver_s3files}
      WHERE status = 'uploaded'
   ORDER BY term DESC"
);

if (empty($termrecords)) {
    echo $OUTPUT->notification(
        'No archives are currently eligible for deletion.',
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

$terms = [];
foreach ($termrecords as $r) {
    $terms[(string)$r->term] = (string)$r->term;
}

// ---------------------------------------------------------
// Selected term (default = latest)
// ---------------------------------------------------------
$selectedterm = optional_param('term', '', PARAM_TEXT);
if ($selectedterm === '' || !isset($terms[$selectedterm])) {
    $selectedterm = array_key_first($terms);
}

// ---------------------------------------------------------
// Handle bulk delete action
// ---------------------------------------------------------
$deletedcount = 0;

if (optional_param('delete', false, PARAM_BOOL) && confirm_sesskey()) {

    $files = $DB->get_records('tool_coursearchiver_s3files', [
        'term'   => $selectedterm,
        'status' => 'uploaded',
    ]);

    foreach ($files as $file) {
        if (!empty($file->localpath) && file_exists($file->localpath)) {
            unlink($file->localpath);

            $DB->update_record('tool_coursearchiver_s3files', (object)[
                'id'     => $file->id,
                'status' => 'deleted',
            ]);

            $deletedcount++;
        }
    }

    echo $OUTPUT->notification(
        "{$deletedcount} archive file(s) deleted successfully for term {$selectedterm}.",
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ---------------------------------------------------------
// Fetch remaining uploaded files for selected term
// ---------------------------------------------------------
$archives = $DB->get_records_sql(
    "SELECT *
       FROM {tool_coursearchiver_s3files}
      WHERE status = 'uploaded'
        AND term = :term
   ORDER BY filename ASC",
    ['term' => $selectedterm]
);

// ---------------------------------------------------------
// Term filter form
// ---------------------------------------------------------
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::label('Select Term:', 'term');
echo html_writer::select($terms, 'term', $selectedterm);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'View']);
echo html_writer::end_tag('form');

echo html_writer::empty_tag('hr');

// ---------------------------------------------------------
// No files message
// ---------------------------------------------------------
if (empty($archives)) {
    echo $OUTPUT->notification(
        'No remaining uploaded archives for this term.',
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------
// Bulk delete form (Select All behaviour)
// ---------------------------------------------------------
echo html_writer::start_tag('form', [
    'method' => 'post',
    'onsubmit' => "return confirm('Are you sure you want to delete ALL archives for this term from local storage?');"
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'term',
    'value' => $selectedterm
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'delete',
    'value' => 1
]);

// ---------------------------------------------------------
// Table
// ---------------------------------------------------------
$table = new html_table();
$table->head = [
    'Filename',
    'Local Path',
    'Uploaded On'
];

foreach ($archives as $a) {
    $table->data[] = [
        s($a->filename),
        s($a->localpath),
        userdate($a->timeuploaded),
    ];
}

echo html_writer::table($table);

// ---------------------------------------------------------
// Delete button
// ---------------------------------------------------------
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Delete ALL above archives from local server',
    'class' => 'btn btn-danger'
]);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();

