<?php
// This file is part of Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to trigger S3 archive sync by term.
 *
 * @package    tool_coursearchiver
 */
class tool_coursearchiver_s3sync_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Get available term folders.
        $terms = $this->get_term_folders();

        $mform->addElement(
            'select',
            'term',
            get_string('term', 'tool_coursearchiver'),
            $terms
        );
        $mform->setType('term', PARAM_TEXT);
        $mform->addRule('term', null, 'required', null, 'client');

        // Action buttons.
        $this->add_action_buttons(true, get_string('sync', 'tool_coursearchiver'));
    }

    /**
     * Get available archive term folders.
     *
     * @return array
     */
    protected function get_term_folders(): array {
        $config = get_config('tool_coursearchiver');

        $rootpath = rtrim($config->coursearchiverrootpath, "/\\");
        $archivepath = trim(
            str_replace(str_split(':*?"<>|'), '', $config->coursearchiverpath),
            "/\\"
        );

        $basedir = $rootpath . '/' . $archivepath;

        $terms = [];

        if (is_dir($basedir)) {
            foreach (scandir($basedir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (is_dir($basedir . '/' . $item)) {
                    $terms[$item] = $item;
                }
            }
        }

        if (empty($terms)) {
            $terms = ['' => get_string('notermsfound', 'tool_coursearchiver')];
        }

        return $terms;
    }
}
