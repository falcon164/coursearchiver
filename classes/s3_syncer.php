<?php
// This file is part of Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Service class to sync archived course backups to Amazon S3.
 *
 * @package    tool_coursearchiver
 */
class tool_coursearchiver_s3_syncer {

    /** @var string */
    protected $bucket;

    /** @var string */
    protected $rootpath;

    /** @var string */
    protected $archivepath;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->bucket = 'my-moodle-backups';

        $this->rootpath = rtrim(
            get_config('tool_coursearchiver', 'coursearchiverrootpath'),
            "/\\"
        );

        $this->archivepath = trim(
            str_replace(
                str_split(':*?"<>|'),
                '',
                get_config('tool_coursearchiver', 'coursearchiverpath')
            ),
            "/\\"
        );
    }

    /**
     * Sync all .mbz files for a given term to S3.
     *
     * @param string $term
     * @param int $syncid DB record id from tool_coursearchiver_s3sync
     * @return void
     * @throws Exception
     */
    public function sync_term(string $term, int $syncid): void {
        global $DB;

        $localdir = $this->rootpath . '/' . $this->archivepath . '/' . $term;

        if (!is_dir($localdir)) {
            throw new Exception("Local archive folder not found: {$term}");
        }

        // Initialise AWS S3 client (IAM role based).
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => $this->get_region(),
        ]);

        // Update DB: started.
        $DB->update_record('tool_coursearchiver_s3sync', (object)[
            'id'          => $syncid,
            'status'      => 'running',
            'timestarted' => time(),
        ]);

        try {
            $files = glob($localdir . '/*.mbz');

            foreach ($files as $filepath) {
                $filename = basename($filepath);

                $s3key = $term . '/' . $filename;

                $s3->putObject([
                    'Bucket'     => $this->bucket,
                    'Key'        => $s3key,
                    'SourceFile' => $filepath,
                ]);
            }

            // Update DB: success.
            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'           => $syncid,
                'status'       => 'success',
                'timefinished' => time(),
            ]);

        } catch (AwsException $e) {

            // Update DB: failure.
            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'           => $syncid,
                'status'       => 'failed',
                'timefinished' => time(),
                'error'        => $e->getAwsErrorMessage(),
            ]);

            throw $e;

        } catch (Exception $e) {

            $DB->update_record('tool_coursearchiver_s3sync', (object)[
                'id'           => $syncid,
                'status'       => 'failed',
                'timefinished' => time(),
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Detect AWS region automatically or fallback.
     *
     * @return string
     */
    protected function get_region(): string {
        // If EC2 metadata is available, SDK auto-detects region.
        // Fallback is required for CLI/testing.
        return getenv('AWS_REGION') ?: 'ap-south-1';
    }
}
