<?php
namespace tool_coursearchiver\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;

class s3_sync_task extends adhoc_task {

    public function execute() {
        global $CFG, $DB;

        $data   = $this->get_custom_data();
        $term   = $data->term ?? null;
        $syncid = $data->syncid ?? null;

        if (empty($term)) {
            throw new \coding_exception('S3 sync task called without term');
        }

        // ---------------------------------------------------------
        // Mark sync as RUNNING
        // ---------------------------------------------------------
        if ($syncid) {
            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'          => $syncid,
                'status'      => 'running',
                'timestarted' => time(),
            ]);
        }

        try {
            $bucket = 'my-moodle-backups';

            $config   = get_config('tool_coursearchiver');
            $rootpath = rtrim($config->coursearchiverrootpath, '/');
            $archivebase = $rootpath . '/CourseArchives/' . $term;

            if (!is_dir($archivebase)) {
                throw new \moodle_exception('Archive directory not found: ' . $archivebase);
            }

            mtrace("S3 sync started for term {$term}");

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($archivebase, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileinfo) {

                if (!$fileinfo->isFile() ||
                    strtolower($fileinfo->getExtension()) !== 'mbz') {
                    continue;
                }

                $fullpath = $fileinfo->getPathname();
                $filename = basename($fullpath);
                $s3key    = "course-archives/{$term}/{$filename}";

                // Check existing log
                $existing = $DB->get_record(
                    'tool_coursearchiver_s3files',
                    ['s3key' => $s3key]
                );

                if ($existing && $existing->status === 'uploaded') {
                    mtrace("Skipping already uploaded: {$s3key}");
                    continue;
                }

                // Base log record
                $record = (object)[
                    'term'         => $term,
                    'filename'     => $filename,
                    'localpath'    => $fullpath,
                    's3key'        => $s3key,
                    'timeuploaded' => time(),
                ];

                // -------------------------------------------------
                // PER-FILE upload + failure capture
                // -------------------------------------------------
                try {
                    $command = 'aws s3 cp '
                        . escapeshellarg($fullpath) . ' '
                        . escapeshellarg("s3://{$bucket}/{$s3key}")
                        . ' --only-show-errors';

                    $output = [];
                    $exitcode = 0;
                    exec($command . ' 2>&1', $output, $exitcode);

                    if ($exitcode !== 0) {
                        throw new \runtime_exception(implode("\n", $output));
                    }

                    $record->status = 'uploaded';
                    $record->error  = null;
                    mtrace("UPLOADED: {$s3key}");

                } catch (\Throwable $e) {
                    // ✅ FAILURE STILL LOGGED
                    $record->status = 'failed';
                    $record->error  = $e->getMessage();
                    mtrace("FAILED: {$s3key}");
                    mtrace($e->getMessage());
                }

                // -------------------------------------------------
                // ALWAYS write DB row
                // -------------------------------------------------
                if ($existing) {
                    $record->id = $existing->id;
                    $DB->update_record('tool_coursearchiver_s3files', $record);
                } else {
                    $DB->insert_record('tool_coursearchiver_s3files', $record);
                }
            }

            // -----------------------------------------------------
            // Mark sync as COMPLETED
            // -----------------------------------------------------
            if ($syncid) {
                $DB->update_record('tool_coursearchiver_s3sync', (object)[
                    'id'           => $syncid,
                    'status'       => 'completed',
                    'timefinished' => time(),
                ]);
            }

            mtrace("S3 sync completed for term {$term}");

        } catch (\Throwable $e) {

            // -----------------------------------------------------
            // Mark sync as FAILED (true fatal)
            // -----------------------------------------------------
            if ($syncid) {
                $DB->update_record('tool_coursearchiver_s3sync', (object)[
                    'id'           => $syncid,
                    'status'       => 'failed',
                    'timefinished' => time(),
                    'error'        => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}

