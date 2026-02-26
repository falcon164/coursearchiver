<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolcoursearchiver_s3sync');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $CFG, $DB, $OUTPUT, $PAGE, $USER;

// ---------------------------------------------------------
// Page setup
// ---------------------------------------------------------
$PAGE->set_url('/admin/tool/coursearchiver/s3sync.php');
$PAGE->set_title('Sync Archives to S3');
$PAGE->set_heading('Sync Archives to Amazon S3');

echo $OUTPUT->header();
echo $OUTPUT->heading('Sync Archived Courses to Amazon S3');

// ---------------------------------------------------------
// Selected term (optional initially)
// ---------------------------------------------------------
$term = optional_param('term', '', PARAM_TEXT);

// ---------------------------------------------------------
// Discover available terms from archive filesystem
// ---------------------------------------------------------
$config = get_config('tool_coursearchiver');
$rootpath = rtrim($config->coursearchiverrootpath, '/');
$archivebase = $rootpath . '/CourseArchives';

$terms = [];

if (is_dir($archivebase)) {
    foreach (scandir($archivebase) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($archivebase . '/' . $entry)) {
            $terms[$entry] = $entry;
        }
    }
}

// ---------------------------------------------------------
// No archived terms found
// ---------------------------------------------------------
if (empty($terms)) {
    echo $OUTPUT->notification(
        'No archived terms found. Please archive courses before syncing to S3.',
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------
// If no term selected yet → show selection form
// ---------------------------------------------------------
if (empty($term)) {

    echo html_writer::start_tag('form', ['method' => 'get']);

    echo html_writer::label('Select Term:', 'term');
    echo html_writer::select($terms, 'term', '', ['' => 'Choose a term']);

    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => 'Start S3 Sync'
    ]);

    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------
// Validate selected term
// ---------------------------------------------------------
if (!array_key_exists($term, $terms)) {
    echo $OUTPUT->notification(
        'Invalid term selected.',
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------
// Insert sync run record (THIS WAS THE BUG EARLIER)
// ---------------------------------------------------------
$record = new stdClass();
$record->term        = $term;
$record->status      = 'queued';       // REQUIRED
$record->userid      = $USER->id;       // REQUIRED
$record->timecreated = time();          // REQUIRED

$syncid = $DB->insert_record('tool_coursearchiver_s3sync', $record);

// ---------------------------------------------------------
// Queue adhoc task
// ---------------------------------------------------------
$task = new \tool_coursearchiver\task\s3_sync_task();
$task->set_custom_data([
    'syncid' => $syncid,
    'term'   => $term,
]);

\core\task\manager::queue_adhoc_task($task);

// ---------------------------------------------------------
// Redirect to sync log page
// ---------------------------------------------------------
redirect(
    new moodle_url('/admin/tool/coursearchiver/s3synclog.php', ['term' => $term]),
    'S3 sync task queued successfully.'
);

