<?php
/**
 * Upgrade script for tool_coursearchiver.
 *
 * Baseline schema is defined in install.xml.
 * This file contains incremental upgrades only.
 *
 * @package    tool_coursearchiver
 * @author     Matthew Davidson (original)
 * @author     Mujahid (schema consolidation & enhancements)
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_coursearchiver upgrades.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_coursearchiver_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // ---------------------------------------------------------------------
    // 2025121001 - File-level S3 sync audit table
    // ---------------------------------------------------------------------
    if ($oldversion < 2025121001) {

        $table = new xmldb_table('tool_coursearchiver_s3files');

        if (!$dbman->table_exists($table)) {

            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

            $table->add_field('term', XMLDB_TYPE_CHAR, '100', null,
                XMLDB_NOTNULL, null, null);

            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null,
                XMLDB_NOTNULL, null, null);

            $table->add_field('localpath', XMLDB_TYPE_TEXT, null,
                null, null, null, null);

            $table->add_field('s3key', XMLDB_TYPE_TEXT, null,
                null, null, null, null);

            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL, null, null);

            $table->add_field('error', XMLDB_TYPE_TEXT, null,
                null, null, null, null);

            $table->add_field('timeuploaded', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_term', XMLDB_INDEX_NOTUNIQUE, ['term']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025121001, 'tool', 'coursearchiver');
    }

    return true;
}

