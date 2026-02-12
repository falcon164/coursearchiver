<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB;

$id = required_param('id', PARAM_INT);
$record = $DB->get_record('tool_coursearchiver_s3files', ['id' => $id], '*', MUST_EXIST);

$bucket = 'my-moodle-backups';
$key = $record->s3key;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$record->filename.'"');

passthru("aws s3 cp s3://{$bucket}/{$key} -");
exit;
