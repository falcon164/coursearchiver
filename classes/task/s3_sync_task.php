<?php
namespace tool_coursearchiver\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/vendor/autoload.php');

use Aws\S3\S3Client;
use core\task\adhoc_task;

class s3_sync_task extends adhoc_task {

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();

        if (empty($data->syncid) || empty($data->term)) {
            mtrace('S3 sync task aborted: missing data');
            return;
        }

        $syncid = $data->syncid;
        $term   = $data->term;

        // -------------------------------------------------
        // Mark sync as RUNNING
        // -------------------------------------------------
        $DB->update_record('tool_coursearchiver_s3sync', (object)[
            'id'           => $syncid,
            'status'       => 'running',
            'timestarted'  => time(),
        ]);

        try {
            $config    = get_config('tool_coursearchiver');
            $rootpath  = rtrim($config->coursearchiverrootpath, '/');
            $localpath = $rootpath . '/CourseArchives/' . $term;

            if (!is_dir($localpath)) {
                throw new \Exception("Local archive folder not found: {$localpath}");
            }

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'ap-south-1',
            ]);

            $bucket = 'my-moodle-backups';
            $prefix = "course-archives/{$term}/";

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($localpath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {

                if (!$file->isFile() || strtolower($file->getExtension()) !== 'mbz') {
                    continue;
                }

                $localfile = $file->getPathname();
                $filename  = $file->getFilename();
                $s3key     = $prefix . $filename;

                // -------------------------------------------------
                // ✅ Moodle-safe lookup for existing record (TEXT)
                // -------------------------------------------------
                $sql = "SELECT *
                          FROM {tool_coursearchiver_s3files}
                         WHERE " . $DB->sql_compare_text('s3key') . " = " . $DB->sql_compare_text(':s3key');

                $params   = ['s3key' => $s3key];
                $records  = $DB->get_records_sql($sql, $params);
                $existing = $records ? reset($records) : false;

                if ($existing && $existing->status === 'uploaded') {
                    mtrace("Skipping already uploaded: {$s3key}");
                    continue;
                }

                $record = (object)[
                    'term'         => $term,
                    'filename'     => $filename,
                    'localpath'    => $localfile,
                    's3key'        => $s3key,
                    'timeuploaded' => time(),
                ];

                try {
                    mtrace("Uploading {$localfile} → s3://{$bucket}/{$s3key}");

                    $s3->putObject([
                        'Bucket'     => $bucket,
                        'Key'        => $s3key,
                        'SourceFile' => $localfile,
                        'ACL'        => 'private',
                    ]);

                    $record->status = 'uploaded';
                    $record->error  = null;

                } catch (\Throwable $e) {

                    $record->status = 'failed';
                    $record->error  = $e->getMessage();

                    mtrace("FAILED: {$s3key}");
                    mtrace($e->getMessage());
                }

                if ($existing) {
                    $record->id = $existing->id;
                    $DB->update_record('tool_coursearchiver_s3files', $record);
                } else {
                    $DB->insert_record('tool_coursearchiver_s3files', $record);
                }
            }

            // -------------------------------------------------
            // Mark sync as COMPLETED
            // -------------------------------------------------
            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'           => $syncid,
                'status'       => 'completed',
                'timefinished' => time(),
            ]);

            mtrace("S3 sync completed for term {$term}");

        } catch (\Throwable $e) {

            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'           => $syncid,
                'status'       => 'failed',
                'timefinished' => time(),
                'error'        => $e->getMessage(),
            ]);

            mtrace("S3 sync failed: " . $e->getMessage());
        }
    }
}

